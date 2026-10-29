<?php

namespace App\Services\Notification;

use App\Mail\ProductionStatusChangedMail;
use App\Models\Production;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SofiProductionNotificationService
{
    /**
     * Envia o e-mail de atualização de status da produção para o cliente.
     */
    public function sendProductionStatusEmail(Production $production, string $newStatus, ?string $previousStatus = null): bool
    {
        $cliente = $production->cliente;

        if (! $cliente || empty($cliente->email)) {
            Log::warning("[SofiNotification] Produção #{$production->id} ({$production->uuid}) não possui cliente com e-mail válido.");

            return false;
        }

        try {
            Mail::to($cliente->email)->send(new ProductionStatusChangedMail($production, $newStatus, $previousStatus));

            Log::info("[SofiNotification] E-mail de status ({$newStatus}) enviado para {$cliente->email} (Produção #{$production->id}).");

            return true;
        } catch (\Throwable $e) {
            Log::error("[SofiNotification] Erro ao enviar e-mail de status para {$cliente->email}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Gera a mensagem personalizada da Sofi para envio via WhatsApp.
     */
    public function getWhatsAppMessage(Production $production, string $status): string
    {
        $cliente = $production->cliente;
        $tenantName = $production->tenant?->name ?? 'NUME Creative';
        $clienteName = $cliente?->name ?? 'Cliente';
        $title = $production->title;
        $portalUrl = route('portal.productions.show', $production->uuid);

        $statusText = match ($status) {
            'in_production' => '🎥 *Entrou em Gravação!* Nossa equipe está em campo gravando as cenas com padrão cinematográfico.',
            'editing' => '✂️ *Na Ilha de Edição!* Estamos aplicando cortes dinâmicos de alta retenção e color grading.',
            'ready', 'pending_review' => '✨ *Pronto para sua Revisão!* O corte final está disponível para você assistir e aprovar com 1 clique.',
            'approved' => '🎉 *Aprovado com Sucesso!* Todos os arquivos finais estão liberados para download e distribuição.',
            'published' => '🚀 *No Ar nas Redes Sociais!* Seu vídeo foi publicado com sucesso nas plataformas conectadas.',
            default => '📋 *Status Atualizado:* Projeto em andamento na esteira de produção.',
        };

        $lines = [
            "🎬 *Olá, {$clienteName}! Aqui é a Sofi da {$tenantName}.*",
            '',
            "Temos novidades fresquinhas sobre a sua produção *{$title}*:",
            '',
            $statusText,
            '',
            '👉 *Acompanhe todos os detalhes e assista às prévias no Portal:*',
            $portalUrl,
            '',
            '_Se tiver qualquer dúvida, pode me responder por aqui que eu e a equipe estamos à disposição!_',
        ];

        return implode("\n", $lines);
    }

    /**
     * Gera a URL completa para disparo direto no WhatsApp Web / Desktop / Mobile.
     */
    public function getWhatsAppShareUrl(Production $production, string $status): string
    {
        $cliente = $production->cliente;
        $phone = $cliente?->phone ? preg_replace('/[^0-9]/', '', $cliente->phone) : null;
        $message = $this->getWhatsAppMessage($production, $status);

        $baseUrl = 'https://api.whatsapp.com/send';
        $params = ['text' => $message];

        if (! empty($phone)) {
            $params['phone'] = $phone;
        }

        return $baseUrl.'?'.http_build_query($params);
    }
}
