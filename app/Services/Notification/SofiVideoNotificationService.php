<?php

namespace App\Services\Notification;

use App\Mail\ProducerVideoApprovedMail;
use App\Mail\VideoReadyForApprovalMail;
use App\Models\PackPurchase;
use App\Models\Video;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SofiVideoNotificationService
{
    /**
     * Envia o e-mail de notificação de corte final pronto para o cliente.
     */
    public function sendVideoReadyEmail(Video $video): bool
    {
        $cliente = $video->production?->cliente;

        if (! $cliente || empty($cliente->email)) {
            Log::warning("[SofiNotification] Vídeo #{$video->id} não possui cliente com e-mail válido.");

            return false;
        }

        try {
            $magicUrl = $video->getMagicApprovalUrl(14);
            Mail::to($cliente->email)->send(new VideoReadyForApprovalMail($video, $magicUrl));

            Log::info("[SofiNotification] E-mail de corte final enviado com sucesso para {$cliente->email} (Vídeo #{$video->id}).");

            return true;
        } catch (\Throwable $e) {
            Log::error("[SofiNotification] Erro ao enviar e-mail de corte final para {$cliente->email}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Gera a mensagem personalizada da Sofi para envio via WhatsApp.
     */
    public function getWhatsAppMessage(Video $video, int $validDays = 14): string
    {
        $production = $video->production;
        $cliente = $production?->cliente;
        $purchase = $production?->packPurchase;
        $clienteName = $cliente?->name ?? 'Cliente';
        $title = $video->title ?: ($production?->title ?? 'Vídeo Final');
        $magicUrl = $video->getMagicApprovalUrl($validDays);

        $isLocked = $video->isAssetLocked();
        $balanceAmount = $purchase ? (float) $purchase->balance_amount : 0.00;
        $currency = strtoupper($purchase?->pack?->currency ?? 'EUR');

        $lines = [
            "🎬 *Olá, {$clienteName}! Aqui é a Sofi da ".($video->tenant?->name ?? 'NUME Creative').'.*',
            '',
            "O corte final da sua produção *{$title}* acabou de sair da ilha de edição e está pronto para sua revisão!",
            '',
            '✨ *O que preparamos para você:*',
            '• Roteiro e montagem com gancho inicial (0-3s) de alta retenção.',
            '• Estratégia de SEO pronta para YouTube Shorts, Reels e TikTok.',
        ];

        if ($isLocked && $balanceAmount > 0) {
            $formattedBalance = number_format($balanceAmount, 2, ',', '.');
            $lines[] = '';
            $lines[] = '🔒 *Prévia de Aprovação:*';
            $lines[] = "Você pode assistir à prévia diretamente no link abaixo. Ao confirmar sua aprovação, o saldo restante ({$formattedBalance} {$currency}) é quitado com 1 clique e os arquivos em 4K e a publicação automática nas redes são liberados na hora!";
        } else {
            $lines[] = '';
            $lines[] = '🚀 *Aprovação Rápida:*';
            $lines[] = 'Basta acessar o link para aprovar e liberar o download original e o agendamento nas suas redes.';
        }

        $lines[] = '';
        $lines[] = '👉 *Toque no link seguro para assistir e aprovar (sem precisar de senha):*';
        $lines[] = $magicUrl;

        return implode("\n", $lines);
    }

    /**
     * Gera a URL completa para disparo direto no WhatsApp Web / Desktop / Mobile.
     */
    public function getWhatsAppShareUrl(Video $video, int $validDays = 14): string
    {
        $cliente = $video->production?->cliente;
        $phone = $cliente?->phone ? preg_replace('/[^0-9]/', '', $cliente->phone) : null;
        $message = $this->getWhatsAppMessage($video, $validDays);

        $baseUrl = 'https://api.whatsapp.com/send';
        $params = ['text' => $message];

        if (! empty($phone)) {
            $params['phone'] = $phone;
        }

        return $baseUrl.'?'.http_build_query($params);
    }

    /**
     * Dispara e-mail e retorna os detalhes e URLs prontas de WhatsApp para a interface.
     */
    public function notifyAll(Video $video): array
    {
        $emailSent = $this->sendVideoReadyEmail($video);
        $whatsAppMessage = $this->getWhatsAppMessage($video);
        $whatsAppUrl = $this->getWhatsAppShareUrl($video);

        return [
            'email_sent' => $emailSent,
            'whatsapp_message' => $whatsAppMessage,
            'whatsapp_url' => $whatsAppUrl,
        ];
    }

    /**
     * Notifica a equipe da produtora (criador da produção e administradores do tenant)
     * quando o cliente aprova o corte final e o saldo é liquidado.
     */
    public function notifyProducerVideoApproved(Video $video, ?PackPurchase $purchase = null): int
    {
        $producerEmails = collect();

        // 1. O editor / staff criador da produção
        if ($video->production?->creator?->email) {
            $producerEmails->push($video->production->creator->email);
        }

        // 2. Administradores e gerentes do tenant
        $tenantAdminEmails = $video->tenant?->users()
            ->whereIn('role', ['admin', 'manager'])
            ->pluck('email');

        if ($tenantAdminEmails && $tenantAdminEmails->isNotEmpty()) {
            $producerEmails = $producerEmails->merge($tenantAdminEmails);
        }

        // 3. Fallback: qualquer usuário associado ao tenant caso não haja admin explicitamente marcado
        if ($producerEmails->isEmpty() && $video->tenant) {
            $producerEmails = $video->tenant->users()->pluck('email');
        }

        $uniqueEmails = $producerEmails->unique()->filter()->values();

        $sentCount = 0;
        foreach ($uniqueEmails as $email) {
            try {
                Mail::to($email)->send(new ProducerVideoApprovedMail($video, $purchase));
                $sentCount++;
            } catch (\Throwable $e) {
                Log::error("[SofiNotification] Falha ao enviar alerta de vídeo aprovado para equipe ({$email}): ".$e->getMessage());
            }
        }

        Log::info("[SofiNotification] Alerta de corte aprovado enviado para {$sentCount} membros da produtora (Vídeo #{$video->id}).");

        return $sentCount;
    }
}
