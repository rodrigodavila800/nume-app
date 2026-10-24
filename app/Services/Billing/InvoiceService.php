<?php

namespace App\Services\Billing;

use App\Mail\InvoiceIssuedMail;
use App\Models\InvoiceSequence;
use App\Models\PackPurchase;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfInstance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class InvoiceService
{
    /**
     * Gera o próximo número sequencial de fatura da série contábil com trava atômica por tenant e série.
     */
    public function generateInvoiceNumber(PackPurchase $purchase): string
    {
        $fiscal = TenantFiscalSettings::for($purchase->tenant_id);
        $series = $fiscal->invoiceSeries;

        return DB::transaction(function () use ($purchase, $series) {
            $sequence = InvoiceSequence::withoutGlobalScope('tenant')
                ->where('tenant_id', $purchase->tenant_id)
                ->where('series', $series)
                ->lockForUpdate()
                ->first();

            if (! $sequence) {
                $existingCount = PackPurchase::withoutGlobalScope('tenant')
                    ->where('tenant_id', $purchase->tenant_id)
                    ->where('invoice_series', $series)
                    ->whereNotNull('invoice_number')
                    ->count();

                $sequence = InvoiceSequence::withoutGlobalScope('tenant')->create([
                    'tenant_id' => $purchase->tenant_id,
                    'series' => $series,
                    'last_number' => $existingCount,
                ]);
            }

            $sequence->last_number++;
            $sequence->save();

            return $series.str_pad((string) $sequence->last_number, 5, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Prepara todos os dados canônicos exigidos pela Agencia Tributaria (RD 1619/2012).
     * Se o snapshot já existir, usa-o diretamente para garantir imutabilidade da fatura.
     */
    public function prepareInvoiceData(PackPurchase $purchase): array
    {
        // Se já tiver snapshot gravado, renderiza os dados a partir do snapshot
        if (! empty($purchase->invoice_snapshot) && is_array($purchase->invoice_snapshot)) {
            return $this->formatDataFromSnapshot($purchase, $purchase->invoice_snapshot);
        }

        $fiscal = TenantFiscalSettings::for($purchase->tenant_id);

        // Atribui numeração atômica e snapshot se ainda não tiver
        if (empty($purchase->invoice_number)) {
            $series = $fiscal->invoiceSeries;
            $invoiceNumber = $this->generateInvoiceNumber($purchase);

            $snapshot = $this->buildSnapshotData($purchase, $fiscal, $invoiceNumber, $series);

            $purchase->update([
                'invoice_series' => $series,
                'invoice_number' => $invoiceNumber,
                'invoice_generated_at' => now(),
                'invoice_snapshot' => $snapshot,
            ]);
            $purchase->refresh();

            return $this->formatDataFromSnapshot($purchase, $snapshot);
        }

        // Caso tenha invoice_number mas ainda não tinha snapshot (ex: registro histórico), cria e persiste o snapshot
        $snapshot = $this->buildSnapshotData($purchase, $fiscal, $purchase->invoice_number, $purchase->invoice_series ?: $fiscal->invoiceSeries);
        $purchase->update(['invoice_snapshot' => $snapshot]);
        $purchase->refresh();

        return $this->formatDataFromSnapshot($purchase, $snapshot);
    }

    /**
     * Monta o snapshot imutável da fatura com dados do emissor da produtora e comprador.
     */
    protected function buildSnapshotData(PackPurchase $purchase, TenantFiscalSettings $fiscal, string $invoiceNumber, string $series): array
    {
        $paidAt = $purchase->paid_at ?? now();
        $cliente = $purchase->cliente;

        $clientLegalName = $purchase->billing_legal_name ?: ($cliente?->company_name ?: ($cliente?->name ?? 'Cliente'));
        $clientTaxId = $purchase->billing_tax_id ?: ($cliente?->tax_id ?? '');

        // Add-ons desmembrados
        $addonsList = [];
        $rawAddons = $purchase->addons_purchased ?? [];
        $effectiveAddons = collect($purchase->pack?->effective_addons ?? []);

        foreach ($rawAddons as $raw) {
            $matched = $effectiveAddons->first(function ($a) use ($raw) {
                return (isset($a['name']) && $a['name'] === $raw) || (isset($a['id']) && (string) $a['id'] === (string) $raw);
            });

            $addonsList[] = [
                'name' => is_array($matched) ? $matched['name'] : (string) $raw,
                'price' => is_array($matched) ? (float) ($matched['price'] ?? 0) : 0,
            ];
        }

        return [
            'invoice_number' => $invoiceNumber,
            'invoice_series' => $series,
            'issued_date' => $paidAt->format('d/m/Y H:i'),
            'operation_date' => $paidAt->format('d/m/Y'),
            'company' => [
                'name' => $fiscal->companyName,
                'cif' => $fiscal->companyCif,
                'address' => $fiscal->companyAddress,
                'postal_code' => $fiscal->companyPostalCode,
                'city' => $fiscal->companyCity,
                'email' => $fiscal->companyEmail,
            ],
            'client' => [
                'legal_name' => $clientLegalName,
                'tax_id' => $clientTaxId,
                'address' => $purchase->billing_address ?: '',
                'postal_code' => $purchase->billing_postal_code ?: '',
                'city' => $purchase->billing_city ?: '',
                'country' => $purchase->billing_country ?: 'ES',
                'email' => $cliente?->email ?? '',
            ],
            'financial' => [
                'subtotal' => (float) ($purchase->subtotal ?? $purchase->amount),
                'tax_rate' => (float) $purchase->tax_rate,
                'tax_amount' => (float) $purchase->tax_amount,
                'irpf_rate' => (float) $purchase->irpf_rate,
                'irpf_amount' => (float) $purchase->irpf_amount,
                'total_amount' => (float) ($purchase->total_amount ?? $purchase->amount),
                'payment_method' => $purchase->payment_gateway,
                'gateway_reference' => $purchase->gateway_order_reference ?: $purchase->stripe_payment_intent_id,
            ],
            'pack' => [
                'name' => $purchase->pack?->name ?? ($purchase->quote?->project_title ?? 'Proposta / Produção Audiovisual'),
                'price' => (float) ($purchase->pack?->price ?? $purchase->subtotal ?? $purchase->amount),
                'video_quantity' => (int) ($purchase->pack?->video_quantity ?? $purchase->video_quantity ?? 1),
            ],
            'addons' => $addonsList,
        ];
    }

    /**
     * Mapeia os dados do snapshot para as variáveis esperadas pela view da fatura.
     */
    protected function formatDataFromSnapshot(PackPurchase $purchase, array $snapshot): array
    {
        $company = $snapshot['company'] ?? [];
        $client = $snapshot['client'] ?? [];
        $financial = $snapshot['financial'] ?? [];
        $packData = $snapshot['pack'] ?? [];

        $taxRate = (float) ($financial['tax_rate'] ?? $purchase->tax_rate);
        $taxLabel = ($taxRate > 0) ? 'IVA' : 'Exento de IVA';

        $paymentMethod = $financial['payment_method'] ?? $purchase->payment_gateway;
        $paymentMethodName = match ($paymentMethod) {
            'bizum', 'redsys_bizum' => 'Bizum Móvil (BBVA)',
            'redsys_card' => 'Tarjeta de Crédito / Débito (BBVA Redsys)',
            'stripe' => 'Stripe / Apple Pay',
            default => 'Pasarela Bancaria Segura',
        };

        $packFallback = (object) [
            'name' => $packData['name'] ?? ($purchase->quote?->project_title ?? 'Proposta / Produção Audiovisual'),
            'video_quantity' => (int) ($packData['video_quantity'] ?? $purchase->video_quantity ?? 1),
            'price' => (float) ($packData['price'] ?? $purchase->subtotal ?? $purchase->amount),
        ];

        return [
            'purchase' => $purchase,
            'pack' => $purchase->pack ?: $packFallback,
            'packData' => $packData,
            'packBasePrice' => (float) ($packData['price'] ?? $purchase->pack?->price ?? $purchase->subtotal ?? $purchase->amount),
            'addonsList' => $snapshot['addons'] ?? [],
            'invoiceNumber' => $snapshot['invoice_number'] ?? $purchase->invoice_number,
            'issuedDate' => $snapshot['issued_date'] ?? ($purchase->paid_at ? $purchase->paid_at->format('d/m/Y H:i') : now()->format('d/m/Y H:i')),
            'operationDate' => $snapshot['operation_date'] ?? ($purchase->paid_at ? $purchase->paid_at->format('d/m/Y') : now()->format('d/m/Y')),
            'companyName' => $company['name'] ?? '',
            'companyCif' => $company['cif'] ?? '',
            'companyAddress' => $company['address'] ?? '',
            'companyPostalCode' => $company['postal_code'] ?? '',
            'companyCity' => $company['city'] ?? '',
            'companyEmail' => $company['email'] ?? '',
            'clientLegalName' => $client['legal_name'] ?? ($client['name'] ?? ''),
            'clientTaxId' => $client['tax_id'] ?? '',
            'clientAddress' => $client['address'] ?? '',
            'clientPostalCode' => $client['postal_code'] ?? '',
            'clientCity' => $client['city'] ?? '',
            'clientCountry' => $client['country'] ?? 'ES',
            'clientEmail' => $client['email'] ?? '',
            'subtotal' => (float) ($financial['subtotal'] ?? $purchase->subtotal ?? $purchase->amount),
            'taxRate' => $taxRate,
            'taxAmount' => (float) ($financial['tax_amount'] ?? $purchase->tax_amount),
            'taxLabel' => $taxLabel,
            'irpfRate' => (float) ($financial['irpf_rate'] ?? $purchase->irpf_rate),
            'irpfAmount' => (float) ($financial['irpf_amount'] ?? $purchase->irpf_amount),
            'totalAmount' => (float) ($financial['total_amount'] ?? $purchase->total_amount ?? $purchase->amount),
            'paymentMethodName' => $paymentMethodName,
            'gatewayReference' => $financial['gateway_reference'] ?? ($purchase->gateway_order_reference ?: $purchase->stripe_payment_intent_id),
        ];
    }

    /**
     * Renderiza o objeto PDF da fatura oficial.
     */
    public function renderPdf(PackPurchase $purchase): DomPdfInstance
    {
        $data = $this->prepareInvoiceData($purchase);

        return Pdf::loadView('invoices.factura', $data)
            ->setPaper('a4', 'portrait')
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isRemoteEnabled', true);
    }

    /**
     * Salva o PDF da fatura em disco local isolado por tenant e atualiza o caminho no banco.
     */
    public function saveInvoiceFile(PackPurchase $purchase): string
    {
        $pdf = $this->renderPdf($purchase);
        $fileName = 'invoices/'.$purchase->tenant_id.'/'.$purchase->invoice_number.'.pdf';

        Storage::disk('local')->put($fileName, $pdf->output());

        $purchase->update(['invoice_path' => $fileName]);

        return $fileName;
    }

    /**
     * Envia o e-mail com a fatura oficial em anexo para o cliente.
     */
    public function sendInvoiceEmail(PackPurchase $purchase): bool
    {
        $cliente = $purchase->cliente;
        if (! $cliente || empty($cliente->email)) {
            return false;
        }

        try {
            $pdf = $this->renderPdf($purchase);
            $pdfBinary = $pdf->output();

            Mail::to($cliente->email)
                ->send(new InvoiceIssuedMail($purchase, $pdfBinary));

            return true;
        } catch (\Throwable $e) {
            Log::error("InvoiceService: Falha no envio de e-mail da fatura para a compra #{$purchase->id}: ".get_class($e));

            return false;
        }
    }

    /**
     * Emite a fatura: persiste o PDF em disco e notifica o cliente por e-mail.
     */
    public function issueAndNotify(PackPurchase $purchase): string
    {
        $filePath = $this->saveInvoiceFile($purchase);
        $this->sendInvoiceEmail($purchase);

        return $filePath;
    }
}
