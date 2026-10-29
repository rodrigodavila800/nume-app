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
     */
    public function verifyWebhook(string $mode, string $token, string $challenge): ?string
    {
        $expectedToken = (string) config('services.whatsapp.verify_token');

        if ($mode === 'subscribe' && hash_equals($expectedToken, $token)) {
            Log::info('[MetaWhatsApp] Webhook verificado com sucesso.');

            return $challenge;
        }

        Log::warning('[MetaWhatsApp] Falha na verificação do token de webhook.');

        return null;
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
        $phoneNumberId = $changes['metadata']['phone_number_id'] ?? config('services.whatsapp.phone_number_id');

        if (! $from || ! $messageBody || $messageType !== 'text') {
            return null;
        }

        // 1. Identifica o Tenant (produtora) associada
        $tenant = Tenant::first() ?? new Tenant(['id' => 1]);
        $tenantId = $tenant->id;

        // 2. Localiza se já existe um Cliente cadastrado com esse telefone
        $cleanPhone = preg_replace('/\D/', '', $from);
        $cliente = Cliente::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) use ($cleanPhone) {
                $q->where('phone', 'like', "%{$cleanPhone}%")
                    ->orWhere('whatsapp', 'like', "%{$cleanPhone}%");
            })
            ->first();

        // 3. Registra ou localiza o Lead no CRM
        $lead = $this->leadService->findOrCreate(
            $tenantId,
            [
                'name' => $cliente?->name ?? "Contato WhatsApp {$from}",
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
        $replyText = $this->generateSofiResponse($tenantId, $cliente?->id, $brandContext, $isSpanish, $recentMessages, $messageBody);

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
            Log::info("[MetaWhatsApp] Token ou PhoneID não configurados. Mensagem simulada para {$to}: \"{$message}\"");

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
                Log::info("[MetaWhatsApp] Mensagem enviada com sucesso para {$to}.");

                return true;
            }

            Log::error("[MetaWhatsApp] Erro na API do WhatsApp: {$response->status()} - {$response->body()}");

            return false;
        } catch (\Throwable $e) {
            Log::error("[MetaWhatsApp] Exceção ao enviar mensagem: {$e->getMessage()}");

            return false;
        }
    }

    protected function generateSofiResponse(
        int $tenantId,
        ?int $clienteId,
        string $context,
        bool $isSpanish,
        array $history,
        string $userMessage
    ): string {
        try {
            if ($this->sofiProvider->isConfigured()) {
                $agent = new SofiWhatsAppAgent($tenantId, $clienteId, $context, $isSpanish, $history);
                $result = $agent->prompt($userMessage);

                if (filled($result)) {
                    return trim((string) $result);
                }
            }
        } catch (\Throwable $e) {
            Log::warning("[MetaWhatsApp] Erro na execução do SofiWhatsAppAgent: {$e->getMessage()}");
        }

        // Resposta padrão caso a IA não esteja disponível
        return $isSpanish
            ? '¡Hola! Soy Sofi de NUME Creative. He recibido tu mensaje y ya lo he registrado con nuestro equipo. En breve te daremos más detalles. ¡Gracias!'
            : 'Olá! Sou a Sofi da NUME Creative. Recebi sua mensagem e já registrei no nosso sistema. Em breve nossa equipe retornará com todos os detalhes!';
    }
}

