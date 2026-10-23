<?php

namespace App\Mail;

use App\Models\Video;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VideoReadyForApprovalMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Video $video,
        public ?string $magicUrl = null
    ) {
        if (! $this->magicUrl) {
            $this->magicUrl = $video->getMagicApprovalUrl(14);
        }
    }

    /**
     * Define o envelope do e-mail com assunto persuasivo no tom da Sofi.
     */
    public function envelope(): Envelope
    {
        $tenantName = $this->video->tenant?->name ?? 'NUME Creative';
        $videoTitle = $this->video->title ?: ($this->video->production?->title ?? 'Novo Vídeo');

        return new Envelope(
            subject: "🎬 Corte Final Disponível: '{$videoTitle}' - {$tenantName}",
        );
    }

    /**
     * Define a view e os dados injetados no corpo do e-mail.
     */
    public function content(): Content
    {
        $production = $this->video->production;
        $cliente = $production?->cliente;
        $purchase = $production?->packPurchase;
        $isLocked = $this->video->isAssetLocked();
        $balanceAmount = $purchase ? (float) $purchase->balance_amount : 0.00;
        $currency = strtoupper($purchase?->pack?->currency ?? 'EUR');

        return new Content(
            view: 'emails.videos.ready_for_approval',
            with: [
                'video' => $this->video,
                'production' => $production,
                'cliente' => $cliente,
                'tenant' => $this->video->tenant,
                'magicUrl' => $this->magicUrl,
                'isLocked' => $isLocked,
                'balanceAmount' => $balanceAmount,
                'currency' => $currency,
            ],
        );
    }
}
