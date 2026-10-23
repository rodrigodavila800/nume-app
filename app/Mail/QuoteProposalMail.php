<?php

namespace App\Mail;

use App\Models\Quote;
use App\Services\Quotes\QuotePdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuoteProposalMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Quote $quote,
        public ?string $customSubject = null,
        public ?string $customBody = null,
        public ?string $pdfBinary = null
    ) {}

    public function envelope(): Envelope
    {
        $companyName = $this->quote->tenant?->name ?? 'NUME Creative';
        $proj = $this->quote->project_title ? " {$this->quote->project_title}" : '';
        $defaultSubject = "Presupuesto{$proj} · {$companyName} ({$this->quote->number})";

        return new Envelope(
            subject: $this->customSubject ?: $defaultSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.quotes.proposal',
            with: [
                'quote' => $this->quote,
                'tenant' => $this->quote->tenant,
                'customBody' => $this->customBody,
                'calc' => $this->quote->calculation ?? [],
                'inputs' => $this->quote->inputs ?? [],
            ],
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $cleanClient = preg_replace('/[^\p{L}\p{N}]+/u', '_', $this->quote->client_name ?: 'Cliente');
        $filename = "Presupuesto_{$cleanClient}_{$this->quote->number}.pdf";

        return [
            Attachment::fromData(function () {
                if ($this->pdfBinary) {
                    return $this->pdfBinary;
                }

                $pdfService = app(QuotePdfService::class);
                return $pdfService->renderPdf($this->quote)->output();
            }, $filename)->withMime('application/pdf'),
        ];
    }
}
