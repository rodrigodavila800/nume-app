<?php

namespace App\Services\WhatsApp;

use App\Ai\Agents\SofiWhatsAppAgent;
use App\Ai\SofiProvider;
use App\Models\Cliente;
use App\Models\SofiConversation;
use App\Models\SofiMessage;
use App\Models\Tenant;
use App\Services\Ai\SofiMemoryDistillationService;
use App\Services\Crm\LeadService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaWhatsAppService
{
    public function __construct(
        protected LeadService $leadService,
        protected SofiMemoryDistillationService $distillationService,
        protected SofiProvider $sofiProvider
    ) {}

    /**
     * Valida o desafio de verificação de webhook do Meta Cloud API.
     * Sem token configurado nada é verificado (token vazio nunca bate com token vazio).
     */
    public function verifyWebhook(string $mode, string $token, string $challenge): ?string
    {
        $expectedToken = (string) config('services.whatsapp.verify_token');

        if ($mode === 'subscribe' && $expectedToken !== '' && hash_equals($expectedToken, $token)) {
            Log::info('[MetaWhatsApp] Webhook verificado com sucesso.');

            return $challenge;
        }

        Log::warning('[MetaWhatsApp] Falha na verificação do token de webhook.');

        return null;
    }

    /**
     * Assinatura do POST (X-Hub-Signature-256 = "sha256=" + HMAC SHA-256 do corpo bruto com o
     * App Secret). Sem App Secret configurado, recusa tudo: o corpo traz o telefone que identifica
     * o cliente e, sem assinatura, qualquer um poderia se passar por ele.
     */
    public function hasValidSignature(string $rawBody, ?string $header): bool
    {
        $secret = (string) config('services.whatsapp.app_secret');

        if ($secret === '' || ! is_string($header) || ! str_starts_with($header, 'sha256=')) {
            return false;
        }

        return hash_equals('sha256='.hash_hmac('sha256', $rawBody, $secret), $header);
    }

    /**
     * Produtora dona do número que recebeu a mensagem. Hoje só o número oficial configurado
     * (tenant de services.whatsapp.tenant_slug, a NUME); número desconhecido não é processado.
     */
    public function resolveTenant(?string $phoneNumberId): ?Tenant
    {
        $configured = (string) config('services.whatsapp.phone_number_id');

        if ($configured === '' || ! is_string($phoneNumberId) || ! hash_equals($configured, $phoneNumberId)) {
            return null;
        }

        return Tenant::where('slug', (string) config('services.whatsapp.tenant_slug', 'nume'))->first();
    }

    /**
     * Cliente dono do telefone. Como identifica quem pode ver produções, a regra é estrita:
     * número igual ou igual sem o código do país (no máximo 3 dígitos a mais), com pelo menos
     * 9 dígitos, e só se houver UM candidato. Na dúvida, não identifica.
     */
    public function findCliente(int $tenantId, string $from): ?Cliente
    {
        $digits = preg_replace('/\D/', '', $from);
        if (strlen($digits) < 9) {
            return null;
        }

        $matches = Cliente::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('phone')
            ->get()
            ->filter(function (Cliente $cliente) use ($digits) {
                $candidate = preg_replace('/\D/', '', (string) $cliente->phone);

                return strlen($candidate) >= 9
                    && str_ends_with($digits, $candidate)
                    && strlen($digits) - strlen($candidate) <= 3;
            });

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /**
     * Processa payload de mensagem recebida via Meta Cloud API.
     */
    public function processWebhookPayload(array $payload): ?array
    {
        $entry = $payload['entry'][0] ?? null;
        $changes = $entry['changes'][0]['value'] ?? null;

        if (! $changes || empty($changes['messages'])) {
            return null; // Pode ser atualização de status (delivered, read), que não requer resposta
        }

        $incomingMsg = $changes['messages'][0];
        $from = $incomingMsg['from'] ?? null; // Ex: 5511999999999
        $messageType = $incomingMsg['type'] ?? 'text';
        $messageBody = $incomingMsg['text']['body'] ?? null;
        $phoneNumberId = $changes['metadata']['phone_number_id'] ?? null;

        if (! $from || ! $messageBody || $messageType !== 'text') {
            return null;
        }

        // 1. Produtora dona do número que recebeu a mensagem (nunca "a primeira do banco")
        $tenant = $this->resolveTenant($phoneNumberId);
        if (! $tenant) {
            Log::warning('[MetaWhatsApp] Mensagem para phone_number_id não configurado: ignorada.');

            return null;
        }
        $tenantId = $tenant->id;

        // 2. Cliente cadastrado com esse telefone (regra estrita: ele passa a ver as próprias produções)
        $cliente = $this->findCliente($tenantId, $from);

        // Extrai nome do perfil do WhatsApp, se enviado pela Meta
        $contactName = $changes['contacts'][0]['profile']['name'] ?? null;

        // 3. Registra ou localiza o Lead no CRM (evita duplicatas e reaproveita lead existente)
        $lead = $this->leadService->findOrCreate(
            $tenantId,
            [
                'name' => $cliente?->name ?? ($contactName ? "{$contactName}" : "Contato WhatsApp {$from}"),
                'phone' => $from,
                'email' => $cliente?->email,
                'company' => $cliente?->company_name,
            ],
            'whatsapp',
            $cliente
        );

        // 4. Localiza ou cria a conversa da Sofi para este contato no canal WhatsApp
        $conversation = SofiConversation::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('channel', 'whatsapp')
            ->where('contact_phone', $from)
            ->latest('id')
            ->first();

        if (! $conversation) {
            $conversation = new SofiConversation([
                'tenant_id' => $tenantId,
                'cliente_id' => $cliente?->id,
                'title' => "Atendimento WhatsApp ({$from})",
                'channel' => 'whatsapp',
                'contact_phone' => $from,
                'status' => 'active',
            ]);
            $conversation->forceFill(['tenant_id' => $tenantId])->save();
        }

        // Salva a mensagem recebida
        SofiMessage::create([
            'sofi_conversation_id' => $conversation->id,
            'sender' => 'user',
            'content' => trim($messageBody),
        ]);

        // 5. Monta o contexto cognitivo e histórico para a Sofi
        $locale = $cliente?->locale ?? app()->getLocale();
        $isSpanish = str_starts_with($locale, 'es');

        $brandContext = 'PRODUTORA: '.($tenant->name ?? 'NUME Creative')."\n";
        if ($cliente) {
            $brandContext .= "CLIENTE IDENTIFICADO: {$cliente->name} ({$cliente->company_name})\n"
                           .'NICHO: '.($cliente->brand_niche ?: 'Serviços')."\n"
                           .'PÚBLICO: '.($cliente->target_audience ?: 'Geral')."\n";
        }

        $memoryPrompt = $this->distillationService->getMemoryPromptForCliente($cliente, $tenantId);
        if (! empty($memoryPrompt)) {
            $brandContext .= "\nMEMÓRIA COGNITIVA & HISTÓRICO APRENDIDO:\n{$memoryPrompt}\n";
        }

        $recentMessages = $conversation->messages()
            ->latest('id')
            ->take(6)
            ->get()
            ->reverse()
            ->map(fn ($m) => ['sender' => $m->sender, 'content' => $m->content])
            ->values()
            ->all();

        // 6. Executa a Sofi (Agent via Laravel AI SDK ou resposta heurística)
        $replyText = $this->generateSofiResponse($tenant, $cliente?->id, $brandContext, $isSpanish, $recentMessages, $messageBody, $lead->id, $from);

        // 7. Salva a resposta da Sofi
        SofiMessage::create([
            'sofi_conversation_id' => $conversation->id,
            'sender' => 'sofi',
            'content' => $replyText,
        ]);
        $conversation->touch();

        // 8. Registra a interação no CRM
        $this->leadService->log(
            $lead,
            'whatsapp',
            "Mensagem recebida: \"{$messageBody}\"\nResposta Sofi: \"{$replyText}\"",
            null,
            ['conversation_id' => $conversation->id, 'channel' => 'whatsapp']
        );

        // 9. Destila aprendizado da conversa
        try {
            $this->distillationService->distillFromChat($conversation);
        } catch (\Throwable) {
        }

        // 10. Despacha via Meta Cloud API
        $this->sendWhatsAppMessage($from, $replyText, $phoneNumberId);

        return [
            'status' => 'success',
            'conversation_id' => $conversation->id,
            'reply' => $replyText,
        ];
    }

    /**
     * Envia mensagem de texto via WhatsApp Cloud API.
     */
    public function sendWhatsAppMessage(string $to, string $message, ?string $phoneNumberId = null): bool
    {
        $accessToken = config('services.whatsapp.access_token');
        $phoneId = $phoneNumberId ?: config('services.whatsapp.phone_number_id');
        $version = config('services.whatsapp.api_version', 'v21.0');

        if (! $accessToken || ! $phoneId) {
            // Sem o texto nem o número completo no log (dados pessoais).
            Log::info('[MetaWhatsApp] Token ou PhoneID não configurados: envio simulado para ****'.substr($to, -4).'.');

            return true;
        }

        try {
            $url = "https://graph.facebook.com/{$version}/{$phoneId}/messages";
            $response = Http::withToken($accessToken)
                ->timeout(10)
                ->post($url, [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $to,
                    'type' => 'text',
                    'text' => [
                        'preview_url' => true,
                        'body' => $message,
                    ],
                ]);

            if ($response->successful()) {
                Log::info('[MetaWhatsApp] Mensagem enviada para ****'.substr($to, -4).'.');

                return true;
            }

            // Só o status e o código de erro da Meta: o corpo pode trazer dados da conversa.
            Log::error('[MetaWhatsApp] Erro na API do WhatsApp: HTTP '.$response->status().' código '.($response->json('error.code') ?? '?'));

            return false;
        } catch (\Throwable $e) {
            // Só a classe: a mensagem de exceção do HTTP client inclui a URL chamada.
            Log::error('[MetaWhatsApp] Exceção ao enviar mensagem: '.get_class($e));

            return false;
        }
    }

    protected function generateSofiResponse(
        Tenant $tenant,
        ?int $clienteId,
        string $context,
        bool $isSpanish,
        array $history,
        string $userMessage,
        ?int $leadId = null,
        ?string $contactPhone = null
    ): string {
        try {
            // Provedor + limite mensal de tokens (produtora e cliente). Antes chamava um
            // isConfigured() inexistente: o erro era engolido e a IA nunca respondia.
            $options = $this->sofiProvider->options($tenant, $clienteId);

            if ($options !== null) {
                // O histórico já termina na mensagem atual, que vai como prompt.
                $agent = new SofiWhatsAppAgent($tenant->id, $clienteId, $context, $isSpanish, array_slice($history, 0, -1), $leadId, $contactPhone);
                $result = $agent->prompt($userMessage, ...$options);

                if (filled((string) $result)) {
                    return trim((string) $result);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[MetaWhatsApp] Erro na execução do SofiWhatsAppAgent: '.get_class($e));
        }

        // Resposta padrão caso a IA não esteja disponível
        return $isSpanish
            ? '¡Hola! Soy Sofi de NUME Creative. He recibido tu mensaje y ya lo he registrado con nuestro equipo. En breve te daremos más detalles. ¡Gracias!'
            : 'Olá! Sou a Sofi da NUME Creative. Recebi sua mensagem e já registrei no nosso sistema. Em breve nossa equipe retornará com todos os detalhes!';
    }
}
