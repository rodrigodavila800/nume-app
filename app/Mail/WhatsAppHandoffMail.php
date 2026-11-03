<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Aviso interno à equipe: a Sofi passou uma conversa do WhatsApp para uma pessoa.
 * Só valores simples (nada de model) para a fila: o texto do resumo é escrito pela IA a partir da
 * conversa e entra no e-mail como texto puro.
 */
class WhatsAppHandoffMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $tenantName,
        public string $contactLabel,
        public string $contactPhone,
        public string $reasonLabel,
        public string $summary,
        public string $panelUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Sofi chamou a equipe no WhatsApp: {$this->reasonLabel} ({$this->tenantName})",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.whatsapp.handoff',
        );
    }
}
