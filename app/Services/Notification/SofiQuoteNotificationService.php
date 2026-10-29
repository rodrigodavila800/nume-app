<?php

namespace App\Services\Notification;

use App\Models\Quote;
use App\Models\User;
use App\Services\Crm\LeadAutomation;
use App\Services\Crm\LeadService;
use Carbon\Carbon;
use Illuminate\Support\Str;

class SofiQuoteNotificationService
{
    public function __construct(
        protected LeadService $leadService,
        protected LeadAutomation $leadAutomation
    ) {}

    /**
     * Garante que o orçamento possui um token público de aprovação ativo.
     */
    public function ensureApprovalToken(Quote $quote): string
    {
        if (empty($quote->approval_token)) {
            $quote->approval_token = Str::random(48);
            $quote->approval_token_expires_at = $quote->valid_until
                ? Carbon::parse($quote->valid_until)->endOfDay()
                : now()->addDays(15);
            $quote->save();
        }

        return $quote->approval_token;
    }

    /**
     * Retorna a URL pública e segura para aprovação da proposta pelo cliente.
     */
    public function getPublicApprovalUrl(Quote $quote): string
    {
        $token = $this->ensureApprovalToken($quote);

        return route('quotes.public.show', $token);
    }

    /**
     * Gera a mensagem personalizada e persuasiva da Sofi para envio da proposta no WhatsApp.
     */
    public function getWhatsAppMessage(Quote $quote): string
    {
        $tenantName = $quote->tenant?->name ?? 'NUME Creative';
        $clientName = $quote->client_name ?: 'Cliente';
        $projectTitle = $quote->project_title ?: 'Vídeos Estratégicos';
        $totalFormatted = number_format((float) $quote->total_amount, 2, ',', '.').' €';
        $approvalUrl = $this->getPublicApprovalUrl($quote);

        $inputs = $quote->inputs ?? [];
        $videosCount = $inputs['videos'] ?? $inputs['video_quantity'] ?? null;
        $videoScopeText = $videosCount ? "• {$videosCount} vídeos em formato de alta retenção (0–3s)." : '• Pacote completo de produção audiovisual e estratégia.';

        $lines = [
            "🎬 *Olá, {$clientName}! Aqui é a Sofi da {$tenantName}.*",
            '',
            "A proposta sob medida para o seu projeto *{$projectTitle}* está pronta!",
            '',
            '✨ *Resumo do Escopo:*',
            $videoScopeText,
            '• Roteiro estratégico, gravação 4K e edição dinâmica.',
            "• Investimento total: *{$totalFormatted}*",
            '',
            '👉 *Revise o escopo completo e aprove online pelo link seguro:*',
            $approvalUrl,
            '',
            '_Ficou com alguma dúvida sobre os prazos de gravação ou formatos? Pode me responder por aqui que estou à disposição!_',
        ];

        return implode("\n", $lines);
    }

    /**
     * Gera o link direto para envio pelo WhatsApp Web / Desktop / Mobile.
     */
    public function getWhatsAppShareUrl(Quote $quote): string
    {
        $phone = $quote->client_phone ?: ($quote->cliente?->phone ?? null);
        $cleanPhone = $phone ? preg_replace('/[^0-9]/', '', $phone) : null;
        $message = $this->getWhatsAppMessage($quote);

        $baseUrl = 'https://api.whatsapp.com/send';
        $params = ['text' => $message];

        if (! empty($cleanPhone)) {
            $params['phone'] = $cleanPhone;
        }

        return $baseUrl.'?'.http_build_query($params);
    }

    /**
     * Registra o envio da proposta via WhatsApp, atualiza status do orçamento e avança o Lead no CRM.
     */
    public function recordWhatsAppDispatch(Quote $quote): void
    {
        $this->ensureApprovalToken($quote);

        if (! in_array($quote->status, ['sent', 'accepted'])) {
            $quote->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            // Dispara automação do CRM (avança o lead para 'proposta' e atualiza valor estimado)
            $this->leadAutomation->quoteChanged($quote);
        }

        // Registra atividade explícita de WhatsApp no lead
        $lead = $this->leadService->findOrCreate(
            $quote->tenant_id,
            [
                'name' => $quote->client_name,
                'email' => $quote->client_email,
                'company' => $quote->client_company,
                'phone' => $quote->client_phone,
            ],
            'quote',
            $quote->cliente
        );

        if ($lead) {
            $user = auth()->user() instanceof User ? auth()->user() : null;

            $this->leadService->log(
                $lead,
                'whatsapp',
                __('Proposta do orçamento :num compartilhada via WhatsApp.', ['num' => $quote->number]),
                $user,
                ['quote_id' => $quote->id, 'channel' => 'whatsapp']
            );
        }
    }
}
