<?php

namespace App\Mail;

use App\Models\PackPurchase;
use App\Models\Video;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProducerVideoApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Video $video,
        public ?PackPurchase $purchase = null
    ) {}

    /**
     * Define o envelope do e-mail de notificação para a equipe da produtora.
     */
    public function envelope(): Envelope
    {
        $videoTitle = $this->video->title ?: ($this->video->production?->title ?? 'Vídeo Final');
        $clienteName = $this->video->production?->cliente?->name ?? 'Cliente';

        return new Envelope(
            subject: "🎉 Corte Aprovado e Saldo Quitado: '{$videoTitle}' - {$clienteName}",
        );
    }

    /**
     * Define a view e os dados injetados no corpo do e-mail.
     */
    public function content(): Content
    {
        $production = $this->video->production;
        $cliente = $production?->cliente;
        $purchase = $this->purchase ?: $production?->packPurchase;
        $balanceAmount = $purchase ? (float) $purchase->balance_amount : 0.00;
        $currency = strtoupper($purchase?->pack?->currency ?? 'EUR');
        $isSplit5050 = $purchase ? $purchase->isSplit5050() : false;
        $productionUrl = $production ? route('productions.show', $production) : url('/dashboard');

        return new Content(
            view: 'emails.producer.video_approved',
            with: [
                'video' => $this->video,
                'production' => $production,
                'cliente' => $cliente,
                'tenant' => $this->video->tenant,
                'purchase' => $purchase,
                'balanceAmount' => $balanceAmount,
                'currency' => $currency,
                'isSplit5050' => $isSplit5050,
                'productionUrl' => $productionUrl,
            ],
        );
    }
}
