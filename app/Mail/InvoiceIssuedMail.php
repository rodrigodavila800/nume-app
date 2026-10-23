<?php

namespace App\Mail;

use App\Models\PackPurchase;
use App\Services\Billing\InvoiceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceIssuedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public PackPurchase $purchase,
        public ?string $pdfBinary = null
    ) {
    }

    /**
     * Define o assunto e destinatário do e-mail.
     */
    public function envelope(): Envelope
    {
        $companyName = $this->purchase->tenant?->name ?? 'NUME Creative';
        $invoiceNum = $this->purchase->invoice_number ?? "REC-{$this->purchase->id}";

        return new Envelope(
            subject: "Factura Oficial {$invoiceNum} - {$companyName}",
        );
    }

    /**
     * Define a view e os dados do corpo do e-mail.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.invoices.issued',
            with: [
                'purchase' => $this->purchase,
                'cliente' => $this->purchase->cliente,
                'pack' => $this->purchase->pack,
                'tenant' => $this->purchase->tenant,
                'invoiceNumber' => $this->purchase->invoice_number,
                'totalAmount' => $this->purchase->total_amount ?? $this->purchase->amount,
                'subtotal' => $this->purchase->subtotal ?? $this->purchase->amount,
                'taxAmount' => $this->purchase->tax_amount ?? 0,
            ],
        );
    }

    /**
     * Anexa a fatura oficial em formato PDF.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        $filename = "Factura-{$this->purchase->invoice_number}.pdf";

        return [
            Attachment::fromData(function () {
                if ($this->pdfBinary) {
                    return $this->pdfBinary;
                }

                $invoiceService = app(InvoiceService::class);
                return $invoiceService->renderPdf($this->purchase)->output();
            }, $filename)->withMime('application/pdf'),
        ];
    }
}
