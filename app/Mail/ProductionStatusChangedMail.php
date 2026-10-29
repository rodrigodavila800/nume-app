<?php

namespace App\Mail;

use App\Models\Production;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ProductionStatusChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Production $production,
        public string $newStatus,
        public ?string $previousStatus = null,
        public ?string $portalUrl = null
    ) {
        if (! $this->portalUrl) {
            $this->portalUrl = route('portal.productions.show', $production->uuid);
        }
    }

    /**
     * Define o envelope do e-mail com assunto persuasivo no tom da Sofi.
     */
    public function envelope(): Envelope
    {
        $tenantName = $this->production->tenant?->name ?? 'NUME Creative';
        $title = $this->production->title;

        $subjectPrefix = match ($this->newStatus) {
            'in_production' => '🎥 Bastidores e Gravação Iniciados',
            'editing' => '✂️ Ilha de Edição e Montagem',
            'ready', 'pending_review' => '✨ Pronta para sua Revisão',
            'approved' => '🎉 Produção Aprovada com Sucesso',
            'published' => '🚀 Seu Vídeo está no Ar nas Redes',
            default => '📋 Atualização de Andamento',
        };

        return new Envelope(
            subject: "{$subjectPrefix}: '{$title}' - {$tenantName}",
        );
    }

    /**
     * Define a view e os dados injetados no corpo do e-mail.
     */
    public function content(): Content
    {
        $cliente = $this->production->cliente;
        $tenant = $this->production->tenant;

        return new Content(
            view: 'emails.productions.status_changed',
            with: [
                'production' => $this->production,
                'cliente' => $cliente,
                'tenant' => $tenant,
                'newStatus' => $this->newStatus,
                'previousStatus' => $this->previousStatus,
                'portalUrl' => $this->portalUrl,
            ],
        );
    }
}

