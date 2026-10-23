<?php

namespace App\Services\Quotes;

use App\Models\Quote;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfInstance;

class QuotePdfService
{
    public function __construct(
        protected QuoteCalculatorService $calculator
    ) {}

    /**
     * Prepara os dados canônicos para a proposta formal do orçamento.
     */
    public function preparePdfData(Quote $quote): array
    {
        $tenant = $quote->tenant;
        $inputs = $quote->inputs ?? [];
        $calc = $quote->calculation ?? $this->calculator->calculate($inputs);
        $language = $quote->language ?? 'es';

        $deliverables = $this->calculator->getDeliverables(
            $inputs,
            $calc,
            $language,
            $quote->project_title
        );

        $issuedAt = $quote->created_at ?: now();
        $validUntil = $quote->valid_until ?: $issuedAt->copy()->addDays($inputs['prazo'] ?? 10);

        // Logo da NUME ou do Tenant
        $logoBase64 = null;
        $logoPath = public_path('assests/img/logo.png');
        if (file_exists($logoPath)) {
            $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
        }

        return [
            'quote' => $quote,
            'tenant' => $tenant,
            'inputs' => $inputs,
            'calc' => $calc,
            'deliverables' => $deliverables,
            'language' => $language,
            'issuedDate' => $issuedAt->format('d/m/Y'),
            'validUntilDate' => $validUntil->format('d/m/Y'),
            'logoBase64' => $logoBase64,
        ];
    }

    /**
     * Renderiza o PDF em A4 com suporte a CSS limpo e isolado.
     */
    public function renderPdf(Quote $quote): DomPdfInstance
    {
        $data = $this->preparePdfData($quote);

        return Pdf::loadView('quotes.pdf', $data)
            ->setPaper('a4', 'portrait')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);
    }
}
