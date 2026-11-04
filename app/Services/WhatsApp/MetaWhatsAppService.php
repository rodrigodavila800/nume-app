<?php

namespace App\Services\WhatsApp;

use App\Ai\Agents\SofiWhatsAppAgent;
use App\Ai\SofiProvider;
use App\Ai\Tools\ListTenantPacksTool;
use App\Models\Cliente;
use App\Models\Lead;
use App\Models\SofiConversation;
use App\Models\SofiMessage;
use App\Models\Tenant;
use App\Services\Ai\SofiMemoryDistillationService;
use App\Services\Crm\LeadService;
use App\Services\Payment\PaymentMethodAvailability;
use App\Services\SofiSalesConsultantService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Exceptions\FailoverableException;

class MetaWhatsAppService
{
    /** A última resposta gerada foi a de falha da IA (fica marcada na mensagem salva). */
    protected bool $lastReplyWasFallback = false;

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
     * Processa payload de mensagem recebida via Meta Cloud API, uma única vez por mensagem.
     *
     * A Meta reenvia o evento quando a resposta demora (a IA pode levar segundos) ou falha; sem a
     * trava pelo id da mensagem (wamid) a Sofi responderia duas vezes ao mesmo cliente. A trava é
     * atômica (Cache::add) e solta se o processamento falhar, para o reenvio da Meta valer.
     */
    public function processWebhookPayload(array $payload): ?array
    {
        $message = $payload['entry'][0]['changes'][0]['value']['messages'][0] ?? null;
        $messageId = is_array($message) ? ($message['id'] ?? null) : null;

        if (! is_string($messageId) || $messageId === '') {
            return $this->handleIncoming($payload);
        }

        $lockKey = 'whatsapp:wamid:'.hash('sha256', $messageId);

        if (! Cache::add($lockKey, true, now()->addDay())) {
            Log::info('[MetaWhatsApp] Mensagem repetida pela Meta: ignorada.');

            return ['status' => 'duplicate'];
        }

        try {
            return $this->handleIncoming($payload);
        } catch (\Throwable $e) {
            Cache::forget($lockKey);

            throw $e;
        }
    }

    protected function handleIncoming(array $payload): ?array
    {
        $entry = $payload['entry'][0] ?? null;
        $changes = $entry['changes'][0]['value'] ?? null;

        if (! $changes || empty($changes['messages'])) {
            return null; // Pode ser atualização de status (delivered, read), que não requer resposta
        }

        $incomingMsg = $changes['messages'][0];
        $from = $incomingMsg['from'] ?? null; // Ex: 5511999999999
        $messageType = $incomingMsg['type'] ?? 'text';
        // Texto ou clique num botão das boas-vindas (o título do botão vira a mensagem do cliente).
        $messageBody = match ($messageType) {
            'text' => $incomingMsg['text']['body'] ?? null,
            'interactive' => ($incomingMsg['interactive']['type'] ?? null) === 'button_reply'
                ? mb_substr((string) ($incomingMsg['interactive']['button_reply']['title'] ?? ''), 0, 40)
                : null,
            default => null,
        };
        $phoneNumberId = $changes['metadata']['phone_number_id'] ?? null;

        if (! $from || ! is_string($messageBody) || trim($messageBody) === '') {
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
        $locale = $cliente?->locale ?? app()->getLocale();
        $isSpanish = str_starts_with($locale, 'es');

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

        $isNewConversation = ! $conversation;

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
        $conversation->touch();

        // Primeiro contato: boas-vindas com aviso de privacidade (RGPD) e botões. Só um cumprimento
        // ("oi", "bom dia") fica nas boas-vindas; uma pergunta de verdade a Sofi responde em seguida.
        if ($isNewConversation) {
            $this->sendWelcome($tenant, $conversation, $from, $phoneNumberId, $isSpanish);

            if ($this->isGreetingOnly($messageBody)) {
                $this->leadService->log($lead, 'whatsapp', 'Primeiro contato no WhatsApp: boas-vindas e aviso de privacidade enviados.', null, ['conversation_id' => $conversation->id, 'channel' => 'whatsapp']);

                return ['status' => 'welcomed', 'conversation_id' => $conversation->id, 'reply' => null];
            }
        }

        // Equipe respondendo pelo painel: a mensagem fica salva e no CRM, mas a Sofi não interrompe.
        if ($this->isHumanHandling($conversation)) {
            $this->leadService->log(
                $lead,
                'whatsapp',
                "Mensagem recebida durante o atendimento da equipe: \"{$messageBody}\"",
                null,
                ['conversation_id' => $conversation->id, 'channel' => 'whatsapp']
            );

            return ['status' => 'human_handling', 'conversation_id' => $conversation->id, 'reply' => null];
        }

        // 5. Monta o contexto cognitivo e histórico para a Sofi

        $brandContext = 'PRODUTORA: '.($tenant->name ?? 'NUME Creative')."\n";
        $methods = PaymentMethodAvailability::forTenant($tenant)->activeMethodLabels(null, $isSpanish);
        $brandContext .= 'FORMAS DE PAGAMENTO ATIVAS: '.($methods === [] ? 'a equipe informa' : implode(', ', $methods))."\n";
        if ($cliente) {
            $brandContext .= "CLIENTE IDENTIFICADO: {$cliente->name} ({$cliente->company_name})\n"
                           .'NICHO: '.($cliente->brand_niche ?: 'Serviços')."\n"
                           .'PÚBLICO: '.($cliente->target_audience ?: 'Geral')."\n";
        }

        if ($conversation->status === HandoffService::STATUS_NEEDS_HUMAN) {
            $brandContext .= "
ATENÇÃO: A EQUIPE JÁ FOI AVISADA sobre esta conversa e vai assumir. Não chame EscalateToTeamTool de novo para o mesmo assunto e não negocie esse ponto: diga que a equipe retorna por aqui. Continue atendendo e vendendo normalmente o que não depende da equipe.
";
        }

        $memoryPrompt = $this->distillationService->getMemoryPromptForCliente($cliente, $tenantId);
        if (! empty($memoryPrompt)) {
            $brandContext .= "\nMEMÓRIA COGNITIVA & HISTÓRICO APRENDIDO:\n{$memoryPrompt}\n";
        }

        $recentMessages = $conversation->messages()
            ->reorder('id', 'desc')
            ->take(12)
            ->get()
            ->reverse()
            ->map(fn ($m) => ['sender' => $m->sender, 'content' => $m->content])
            ->values()
            ->all();

        // 6. Executa a Sofi (Agent via Laravel AI SDK ou resposta heurística)
        $replyText = $this->generateSofiResponse($tenant, $cliente?->id, $brandContext, $isSpanish, $recentMessages, $messageBody, $lead->id, $from, $conversation->id);

        // 7. Salva a resposta da Sofi
        SofiMessage::create([
            'sofi_conversation_id' => $conversation->id,
            'sender' => 'sofi',
            'content' => $replyText,
            'metadata' => $this->lastReplyWasFallback ? ['fallback' => true] : null,
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

    /**
     * A equipe respondeu há pouco? Só nesse caso a Sofi se cala. Passada a pausa, a conversa
     * volta para a Sofi sozinha, para o cliente não ficar sem resposta se ninguém retomar.
     */
    protected function isHumanHandling(SofiConversation $conversation): bool
    {
        if ($conversation->status !== HandoffService::STATUS_HUMAN) {
            return false;
        }

        $pauseHours = max(1, (int) config('sofi.whatsapp_human_pause_hours', 4));
        $lastHumanReply = $conversation->messages()
            ->where('sender', 'sofi')
            ->where('metadata->is_human', true)
            ->reorder('id', 'desc')
            ->first();

        if ($lastHumanReply && $lastHumanReply->created_at->gt(now()->subHours($pauseHours))) {
            Log::info('[MetaWhatsApp] Conversa ****'.substr((string) $conversation->contact_phone, -4)
                .' em atendimento da equipe: Sofi calada até '.$lastHumanReply->created_at->copy()->addHours($pauseHours)->format('H:i').' (UTC).');

            return true;
        }

        $conversation->forceFill(['status' => 'active'])->save();

        return false;
    }

    /**
     * Voz comercial da Sofi, a mesma do chat da vitrine. Fica de fora a "missão" dela (diagnosticar
     * antes de recomendar e convidar ao cadastro), que no WhatsApp atrasa a venda: as regras do
     * canal ficam no SofiWhatsAppAgent.
     */
    protected function salesRules(Tenant $tenant, bool $isSpanish, string $userMessage): string
    {
        $parts = app(SofiSalesConsultantService::class)->salesPromptParts($tenant, $isSpanish, $userMessage);

        return $parts['persona']
            ."
".$parts['market_trends']
            ."
".$parts['anti_patterns']
            .$parts['niche_section']
            ."
".$parts['directives'];
    }

    /**
     * Envia um PDF como documento do WhatsApp: sobe o arquivo na Meta (/{phone}/media) e manda a
     * mensagem do tipo document com o id devolvido. Só funciona dentro da janela de 24 h da conversa
     * (a proposta sai logo depois de o cliente escrever). Log só com status e código da Meta.
     */
    public function sendWhatsAppDocument(string $to, string $pdfBinary, string $filename, string $caption, ?string $phoneNumberId = null): bool
    {
        $accessToken = config('services.whatsapp.access_token');
        $phoneId = $phoneNumberId ?: config('services.whatsapp.phone_number_id');
        $version = config('services.whatsapp.api_version', 'v21.0');

        if (! $accessToken || ! $phoneId) {
            Log::info('[MetaWhatsApp] Token ou PhoneID não configurados: documento simulado para ****'.substr($to, -4).'.');

            return true;
        }

        try {
            $upload = Http::withToken($accessToken)
                ->timeout(30)
                ->attach('file', $pdfBinary, $filename, ['Content-Type' => 'application/pdf'])
                ->post("https://graph.facebook.com/{$version}/{$phoneId}/media", [
                    'messaging_product' => 'whatsapp',
                    'type' => 'application/pdf',
                ]);

            $mediaId = $upload->successful() ? $upload->json('id') : null;
            if (! is_string($mediaId) || $mediaId === '') {
                Log::error('[MetaWhatsApp] Falha no upload do documento: HTTP '.$upload->status().' código '.($upload->json('error.code') ?? '?'));

                return false;
            }

            $response = Http::withToken($accessToken)
                ->timeout(10)
                ->post("https://graph.facebook.com/{$version}/{$phoneId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $to,
                    'type' => 'document',
                    'document' => ['id' => $mediaId, 'filename' => $filename, 'caption' => $caption],
                ]);

            if ($response->successful()) {
                Log::info('[MetaWhatsApp] Documento enviado para ****'.substr($to, -4).'.');

                return true;
            }

            Log::error('[MetaWhatsApp] Erro ao enviar documento: HTTP '.$response->status().' código '.($response->json('error.code') ?? '?'));

            return false;
        } catch (\Throwable $e) {
            // Só a classe: a mensagem do HTTP client inclui a URL chamada.
            Log::error('[MetaWhatsApp] Exceção ao enviar documento: '.get_class($e));

            return false;
        }
    }

    /**
     * Botões das boas-vindas (id => título). Título com no máximo 20 caracteres (limite da Meta).
     *
     * @return array<string, string>
     */
    public static function welcomeButtons(bool $isSpanish): array
    {
        return $isSpanish
            ? ['sofi_buy' => 'Quiero contratar', 'sofi_client' => 'Ya soy cliente', 'sofi_team' => 'Hablar con el equipo']
            : ['sofi_buy' => 'Quero contratar', 'sofi_client' => 'Já sou cliente', 'sofi_team' => 'Falar com a equipe'];
    }

    protected function sendWelcome(Tenant $tenant, SofiConversation $conversation, string $to, ?string $phoneNumberId, bool $isSpanish): void
    {
        $name = $tenant->name ?: 'NUME Creative';
        $privacy = route('legal.privacy');
        $text = $isSpanish
            ? "¡Hola! 👋 Soy Sofi, de {$name}. Producimos vídeos estratégicos para redes sociales y anuncios.\n\nElige una opción o cuéntame lo que necesitas.\n\n🔒 Usamos tus datos solo para atenderte y enviarte propuestas: {$privacy}"
            : "Olá! 👋 Aqui é a Sofi, da {$name}. Produzimos vídeos estratégicos para redes sociais e anúncios.\n\nEscolha uma opção ou me conte o que você precisa.\n\n🔒 Usamos seus dados só para te atender e enviar propostas: {$privacy}";
        $buttons = self::welcomeButtons($isSpanish);

        SofiMessage::create([
            'sofi_conversation_id' => $conversation->id,
            'sender' => 'sofi',
            'content' => $text,
            'metadata' => ['welcome' => true, 'buttons' => array_values($buttons)],
        ]);

        $this->sendWhatsAppButtons($to, $text, $buttons, $phoneNumberId);
    }

    /**
     * Só cumprimento, sem pedido (até 4 palavras, todas de saudação). Emoji ou pontuação sozinhos contam.
     */
    protected function isGreetingOnly(string $text): bool
    {
        $words = array_values(array_filter(explode(' ', (string) preg_replace('/[^\p{L}]+/u', ' ', mb_strtolower($text)))));

        if (count($words) > 4) {
            return false;
        }

        $greetings = ['oi', 'oie', 'olá', 'ola', 'opa', 'ei', 'eai', 'alô', 'alo', 'hola', 'holi', 'buenas', 'buenos', 'buen', 'bom', 'boa', 'dia', 'días', 'dias',
            'tarde', 'tardes', 'noite', 'noches', 'tudo', 'bem', 'bien', 'que', 'qué', 'tal', 'hello', 'hi', 'hey', 'sofi', 'nume'];

        return array_diff($words, $greetings) === [];
    }

    /**
     * Mensagem com até 3 botões de resposta (interactive/button), dentro da janela de 24 h da conversa.
     *
     * @param  array<string, string>  $buttons  id => título
     */
    public function sendWhatsAppButtons(string $to, string $body, array $buttons, ?string $phoneNumberId = null): bool
    {
        $accessToken = config('services.whatsapp.access_token');
        $phoneId = $phoneNumberId ?: config('services.whatsapp.phone_number_id');
        $version = config('services.whatsapp.api_version', 'v21.0');

        if (! $accessToken || ! $phoneId) {
            Log::info('[MetaWhatsApp] Token ou PhoneID não configurados: boas-vindas simuladas para ****'.substr($to, -4).'.');

            return true;
        }

        $buttons = array_slice($buttons, 0, 3, true);

        try {
            $response = Http::withToken($accessToken)
                ->timeout(10)
                ->post("https://graph.facebook.com/{$version}/{$phoneId}/messages", [
                    'messaging_product' => 'whatsapp',
                    'recipient_type' => 'individual',
                    'to' => $to,
                    'type' => 'interactive',
                    'interactive' => [
                        'type' => 'button',
                        'body' => ['text' => mb_substr($body, 0, 1024)],
                        'action' => ['buttons' => array_map(
                            fn (string $id, string $title): array => ['type' => 'reply', 'reply' => ['id' => $id, 'title' => mb_substr($title, 0, 20)]],
                            array_keys($buttons),
                            array_values($buttons),
                        )],
                    ],
                ]);

            if ($response->successful()) {
                Log::info('[MetaWhatsApp] Boas-vindas enviadas para ****'.substr($to, -4).'.');

                return true;
            }

            Log::error('[MetaWhatsApp] Erro ao enviar boas-vindas: HTTP '.$response->status().' código '.($response->json('error.code') ?? '?'));

            return false;
        } catch (\Throwable $e) {
            Log::error('[MetaWhatsApp] Exceção ao enviar boas-vindas: '.get_class($e));

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
        ?string $contactPhone = null,
        ?int $conversationId = null
    ): string {
        $this->lastReplyWasFallback = false;
        $started = microtime(true);

        // Provedor + limite mensal de tokens (produtora e cliente). Null = sem IA (sem chave ou sem orçamento).
        $options = $this->sofiProvider->options($tenant, $clienteId);

        if ($options !== null) {
            $options['timeout'] = max(1, (int) config('sofi.whatsapp_timeout', 30));

            foreach ($this->modelAttempts($options) as $attempt => $model) {
                try {
                    $agentOptions = $model === null ? $options : ['model' => $model] + $options;
                    // O histórico já termina na mensagem atual, que vai como prompt.
                    $agent = new SofiWhatsAppAgent($tenant->id, $clienteId, $context, $isSpanish, array_slice($history, 0, -1), $leadId, $contactPhone, $this->salesRules($tenant, $isSpanish, $userMessage), $conversationId);
                    $result = $agent->prompt($userMessage, ...$agentOptions);

                    if (filled((string) $result)) {
                        return trim((string) $result);
                    }

                    break;
                } catch (FailoverableException $e) {
                    // Gemini sobrecarregado (503), timeout ou limite: uma nova tentativa em outro modelo,
                    // só se ainda couber no prazo do job (ProcessWhatsAppMessage).
                    Log::warning('[MetaWhatsApp] Erro na execução do SofiWhatsAppAgent (tentativa '.($attempt + 1).'): '.get_class($e).$this->causeOf($e));

                    if (microtime(true) - $started > (float) config('sofi.whatsapp_retry_within_seconds', 45)) {
                        break;
                    }
                } catch (\Throwable $e) {
                    Log::warning('[MetaWhatsApp] Erro na execução do SofiWhatsAppAgent: '.get_class($e).$this->causeOf($e));

                    break;
                }
            }
        }

        $this->lastReplyWasFallback = true;

        return $this->fallbackReply($tenant, $isSpanish, $conversationId, $leadId);
    }

    /**
     * Modelos a tentar: o configurado e, no Gemini, o primeiro modelo de reserva diferente dele
     * (services.gemini.fallback_models). Null = o modelo padrão do provedor.
     *
     * @param  array<string, mixed>  $options
     * @return list<?string>
     */
    protected function modelAttempts(array $options): array
    {
        $primary = $options['model'] ?? null;

        if ($this->sofiProvider->driver() !== 'gemini') {
            return [$primary];
        }

        $reserve = collect((array) config('services.gemini.fallback_models', []))
            ->first(fn ($model) => filled($model) && $model !== $primary);

        return $reserve ? [$primary, $reserve] : [$primary];
    }

    /**
     * Causa encadeada de uma falha da IA, só para diagnóstico: classes, status HTTP e se foi timeout.
     * Nunca a mensagem da exceção, que traz a URL da chamada e pode trazer chave ou texto do cliente.
     */
    protected function causeOf(\Throwable $e): string
    {
        $parts = [];

        for ($previous = $e->getPrevious(), $depth = 0; $previous !== null && $depth < 3; $previous = $previous->getPrevious(), $depth++) {
            $part = get_class($previous);

            if ($previous instanceof RequestException) {
                $part .= ' HTTP '.$previous->response->status();
            }

            if (str_contains($previous->getMessage(), 'cURL error 28')) {
                $part .= ' timeout';
            }

            $parts[] = $part;
        }

        return $parts === [] ? '' : ' <- '.implode(' <- ', $parts);
    }

    /**
     * IA fora do ar, sem chave ou sem orçamento de tokens: a equipe é chamada e o cliente recebe uma
     * resposta humana. No primeiro contato, os pacotes reais (para não ficar sem nada); no meio da
     * conversa (ex.: fechando a proposta), um pedido de desculpas curto com o nome dele, sem repetir o
     * catálogo; se a falha se repete, uma frase diferente. Nada aqui pode quebrar o atendimento.
     */
    protected function fallbackReply(Tenant $tenant, bool $isSpanish, ?int $conversationId, ?int $leadId): string
    {
        $teamCalled = false;
        $conversation = null;
        $lead = null;

        try {
            $conversation = $conversationId ? SofiConversation::forTenant($tenant->id)->find($conversationId) : null;
            $lead = $leadId ? Lead::forTenant($tenant->id)->whereNull('anonymized_at')->find($leadId) : null;

            if ($conversation) {
                app(HandoffService::class)->request($conversation, $lead, 'ai_unavailable', 'A IA não respondeu; o cliente aguarda a equipe nesta conversa.');
                $teamCalled = true;
            }
        } catch (\Throwable $e) {
            Log::warning('[MetaWhatsApp] Falha ao chamar a equipe no fallback: '.get_class($e));
        }

        $sofiReplies = $conversation
            ? $conversation->messages()->where('sender', 'sofi')->reorder('id', 'desc')->get(['id', 'metadata'])
                ->reject(fn (SofiMessage $m) => ! empty($m->metadata['welcome']))
            : collect();
        $firstName = $this->contactFirstName($lead);
        $hi = $firstName !== '' ? ($isSpanish ? "Perdona, {$firstName}" : "Desculpe, {$firstName}") : ($isSpanish ? 'Perdona' : 'Desculpe');

        // Falha seguida: não repete o mesmo texto.
        if (! empty($sofiReplies->first()?->metadata['fallback'])) {
            return $isSpanish
                ? 'Sigo sin poder responderte yo misma, pero tu mensaje ya está con el equipo y te contestan por aquí.'
                : 'Ainda não consigo responder por aqui, mas a sua mensagem já está com a equipe e eles te respondem neste WhatsApp.';
        }

        // No meio da conversa: desculpa curta, sem catálogo.
        if ($sofiReplies->isNotEmpty()) {
            return $isSpanish
                ? "{$hi}, he tenido un problema técnico para responderte ahora.".($teamCalled ? ' Ya avisé al equipo y te contestan por aquí para seguir con tu propuesta.' : ' Inténtalo de nuevo en unos minutos.')
                : "{$hi}, tive um problema técnico para te responder agora.".($teamCalled ? ' Já avisei a equipe e eles te respondem por aqui para seguir com a sua proposta.' : ' Tente de novo em alguns minutos.');
        }

        $lines = [];

        try {
            foreach (array_slice((new ListTenantPacksTool($tenant->id))->packs(), 0, 3) as $pack) {
                $price = number_format($pack['price'], 2, ',', '.');
                $lines[] = "• *{$pack['name']}*: {$pack['video_quantity']} vídeos por {$price} € + IVA";
            }
        } catch (\Throwable $e) {
            Log::warning('[MetaWhatsApp] Falha ao listar pacotes no fallback: '.get_class($e));
        }

        $catalog = $lines === [] ? '' : "\n\n".implode("\n", $lines);

        if ($isSpanish) {
            return "{$hi}, ahora mismo no consigo responderte".($teamCalled ? '; ya avisé al equipo y te contestan por aquí' : '').'.'
                .($lines === [] ? '' : "\n\nMientras tanto, estos son nuestros paquetes:").$catalog;
        }

        return "{$hi}, agora não consigo te responder".($teamCalled ? '; já avisei a equipe e eles te respondem por aqui' : '').'.'
            .($lines === [] ? '' : "\n\nEnquanto isso, estes são os nossos pacotes:").$catalog;
    }

    /** Primeiro nome do contato, se for um nome de verdade (nunca "Contato WhatsApp 34..."). */
    protected function contactFirstName(?Lead $lead): string
    {
        $name = trim((string) $lead?->name);

        if ($name === '' || str_starts_with($name, 'Contato WhatsApp')) {
            return '';
        }

        return mb_substr((string) strtok($name, ' '), 0, 40);
    }
}
