<?php

namespace App\Jobs;

use App\Mail\QuoteProposalMail;
use App\Models\Quote;
use App\Services\Quotes\QuotePdfService;
use App\Services\WhatsApp\MetaWhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Proposta formal em PDF pelos dois canais depois que a Sofi cria a proposta no WhatsApp: e-mail
 * (QuoteProposalMail, com o PDF anexo e o botão de aprovação) e documento no próprio WhatsApp.
 *
 * Roda na fila, depois da resposta da Sofi, para o cliente ver primeiro o link e logo em seguida o
 * PDF. Tenant explícito na busca da proposta (fila não tem usuário logado). Sem retentativa: cada
 * canal falha sozinho e só deixa o log, para um erro no WhatsApp não reenviar o e-mail.
 */
class SendQuoteProposalDocuments implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(
        public readonly int $quoteId,
        public readonly int $tenantId,
        public readonly ?string $whatsAppTo = null,
    ) {}

    public function handle(QuotePdfService $pdfService, MetaWhatsAppService $whatsApp): void
    {
        $quote = Quote::forTenant($this->tenantId)->find($this->quoteId);

        if (! $quote || blank($quote->approval_token)) {
            return;
        }

        $pdf = $pdfService->renderPdf($quote)->output();
        $isSpanish = ($quote->language ?? 'es') !== 'pt_BR';
        $filename = ($isSpanish ? 'Presupuesto_' : 'Proposta_').preg_replace('/[^\p{L}\p{N}]+/u', '_', $quote->number).'.pdf';

        if (filled($quote->client_email)) {
            try {
                Mail::to($quote->client_email)->send(new QuoteProposalMail(quote: $quote, pdfBinary: $pdf));
            } catch (Throwable $e) {
                Log::warning('[MetaWhatsApp] Falha ao enviar a proposta por e-mail: '.get_class($e));
            }
        }

        if (filled($this->whatsAppTo)) {
            $tenantName = $quote->tenant?->name ?? 'NUME';
            $caption = $isSpanish
                ? "Propuesta {$quote->number} · {$tenantName}"
                : "Proposta {$quote->number} · {$tenantName}";

            $whatsApp->sendWhatsAppDocument((string) $this->whatsAppTo, $pdf, $filename, $caption);
        }

        // Registro do envio: a Sofi só diz "já foi enviada" quando isto existe (proposta reaproveitada).
        $quote->forceFill(['request_details' => array_merge($quote->request_details ?? [], ['documents_sent_at' => now()->toIso8601String()])])->save();
    }
}
