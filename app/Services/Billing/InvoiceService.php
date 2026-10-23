<?php

namespace App\Services\Billing;

use App\Mail\InvoiceIssuedMail;
use App\Models\PackPurchase;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdfInstance;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class InvoiceService
{
    /**
     * Gera o próximo número sequencial de fatura da série contábil.
     */
    public function generateInvoiceNumber(PackPurchase $purchase): string
    {
        $series = Setting::get('fiscal_invoice_series', 'NUM-'.date('Y').'-');

        $count = PackPurchase::withoutGlobalScope('tenant')
            ->whereNotNull('invoice_number')
            ->where('invoice_series', $series)
            ->count() + 1;

        return $series.str_pad((string) $count, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Prepara todos os dados canônicos exigidos pela Agencia Tributaria (RD 1619/2012).
     */
    public function prepareInvoiceData(PackPurchase $purchase): array
    {
        // Atribui numeração se ainda não tiver
        if (empty($purchase->invoice_number)) {
            $series = Setting::get('fiscal_invoice_series', 'NUM-'.date('Y').'-');
            $purchase->update([
                'invoice_series' => $series,
                'invoice_number' => $this->generateInvoiceNumber($purchase),
                'invoice_generated_at' => now(),
            ]);
            $purchase->refresh();
        }

        $paidAt = $purchase->paid_at ?? now();

        // Dados do Emissor (Nume / Empresa)
        $companyName = Setting::get('fiscal_company_name', 'Nume Creative S.L.');
        $companyCif = Setting::get('fiscal_company_cif', 'B12345678');
        $companyAddress = Setting::get('fiscal_company_address', 'Calle Alfonso I, 12, Planta 3');
        $companyPostalCode = Setting::get('fiscal_company_postal_code', '50003');
        $companyCity = Setting::get('fiscal_company_city', 'Zaragoza');
        $companyEmail = Setting::get('fiscal_company_email', 'facturacion@nume.es');

        // Dados do Cliente / Receptor
        $cliente = $purchase->cliente;
        $clientLegalName = $purchase->billing_legal_name ?: ($cliente->company_name ?: $cliente->name);
        $clientTaxId = $purchase->billing_tax_id ?: '';
        $clientAddress = $purchase->billing_address ?: '';
        $clientPostalCode = $purchase->billing_postal_code ?: '';
        $clientCity = $purchase->billing_city ?: '';
        $clientCountry = $purchase->billing_country ?: 'ES';
        $clientEmail = $cliente->email;

        // Add-ons desmembrados
        $addonsList = [];
        $rawAddons = $purchase->addons_purchased ?? [];
        $effectiveAddons = collect($purchase->pack->effective_addons ?? []);

        foreach ($rawAddons as $raw) {
            $matched = $effectiveAddons->first(function ($a) use ($raw) {
                return (isset($a['name']) && $a['name'] === $raw) || (isset($a['id']) && (string) $a['id'] === (string) $raw);
            });

            $addonsList[] = [
                'name' => is_array($matched) ? $matched['name'] : (string) $raw,
                'price' => is_array($matched) ? (float) ($matched['price'] ?? 0) : 0,
            ];
        }

        // Mapeamento do método de pagamento amigável
        $paymentMethod = $purchase->payment_gateway;
        $paymentMethodName = match ($paymentMethod) {
            'bizum', 'redsys_bizum' => 'Bizum Móvil (BBVA)',
            'redsys_card' => 'Tarjeta de Crédito / Débito (BBVA Redsys)',
            'stripe' => 'Stripe / Apple Pay',
            default => 'Pasarela Bancaria Segura',
        };

        $taxRate = (float) $purchase->tax_rate;
        $taxLabel = ($taxRate > 0) ? 'IVA' : 'Exento de IVA';

        return [
            'purchase' => $purchase,
            'pack' => $purchase->pack,
            'packBasePrice' => (float) $purchase->pack->price,
            'addonsList' => $addonsList,
            'invoiceNumber' => $purchase->invoice_number,
            'issuedDate' => $paidAt->format('d/m/Y H:i'),
            'operationDate' => $paidAt->format('d/m/Y'),
            'companyName' => $companyName,
            'companyCif' => $companyCif,
            'companyAddress' => $companyAddress,
            'companyPostalCode' => $companyPostalCode,
            'companyCity' => $companyCity,
            'companyEmail' => $companyEmail,
            'clientLegalName' => $clientLegalName,
            'clientTaxId' => $clientTaxId,
            'clientAddress' => $clientAddress,
            'clientPostalCode' => $clientPostalCode,
            'clientCity' => $clientCity,
            'clientCountry' => $clientCountry,
            'clientEmail' => $clientEmail,
            'subtotal' => (float) ($purchase->subtotal ?? $purchase->amount),
            'taxRate' => $taxRate,
            'taxAmount' => (float) $purchase->tax_amount,
            'taxLabel' => $taxLabel,
            'irpfRate' => (float) $purchase->irpf_rate,
            'irpfAmount' => (float) $purchase->irpf_amount,
            'totalAmount' => (float) ($purchase->total_amount ?? $purchase->amount),
            'paymentMethodName' => $paymentMethodName,
            'gatewayReference' => $purchase->gateway_order_reference ?: $purchase->stripe_payment_intent_id,
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
     * Salva o PDF da fatura em disco local seguro e atualiza o caminho no banco.
     */
    public function saveInvoiceFile(PackPurchase $purchase): string
    {
        $pdf = $this->renderPdf($purchase);
        $fileName = 'invoices/'.$purchase->invoice_number.'.pdf';

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
