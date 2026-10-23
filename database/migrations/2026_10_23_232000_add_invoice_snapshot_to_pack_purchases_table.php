<?php

use App\Models\PackPurchase;
use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pack_purchases', function (Blueprint $table) {
            $table->json('invoice_snapshot')->nullable()->after('invoice_generated_at');
        });

        // Tenta substituir a unique global de invoice_number por composta (tenant_id, invoice_number)
        try {
            Schema::table('pack_purchases', function (Blueprint $table) {
                $table->dropUnique('pack_purchases_invoice_number_unique');
                $table->unique(['tenant_id', 'invoice_number']);
            });
        } catch (\Throwable $e) {
            // Em caso de sqlite ou nome de índice diferente, continua
        }

        // Backfill das faturas já emitidas com snapshot baseado nos dados globais atuais
        $companyName = Setting::get('fiscal_company_name', 'Nume Creative S.L.');
        $companyCif = Setting::get('fiscal_company_cif', 'B12345678');
        $companyAddress = Setting::get('fiscal_company_address', 'Calle Alfonso I, 12, Planta 3');
        $companyPostalCode = Setting::get('fiscal_company_postal_code', '50003');
        $companyCity = Setting::get('fiscal_company_city', 'Zaragoza');
        $companyEmail = Setting::get('fiscal_company_email', 'facturacion@nume.es');

        $purchases = DB::table('pack_purchases')
            ->whereNotNull('invoice_number')
            ->whereNull('invoice_snapshot')
            ->get();

        foreach ($purchases as $p) {
            $cliente = DB::table('clientes')->where('id', $p->cliente_id)->first();
            $clientLegalName = $p->billing_legal_name ?: ($cliente?->company_name ?: $cliente?->name ?? 'Cliente');
            $clientTaxId = $p->billing_tax_id ?: ($cliente?->tax_id ?? '');

            $snapshot = [
                'invoice_number' => $p->invoice_number,
                'invoice_series' => $p->invoice_series,
                'issued_at' => $p->invoice_generated_at ?: ($p->paid_at ?: $p->created_at),
                'company' => [
                    'name' => $companyName,
                    'cif' => $companyCif,
                    'address' => $companyAddress,
                    'postal_code' => $companyPostalCode,
                    'city' => $companyCity,
                    'email' => $companyEmail,
                ],
                'client' => [
                    'name' => $clientLegalName,
                    'tax_id' => $clientTaxId,
                    'address' => $p->billing_address ?: '',
                    'postal_code' => $p->billing_postal_code ?: '',
                    'city' => $p->billing_city ?: '',
                    'country' => $p->billing_country ?: 'ES',
                    'email' => $cliente?->email ?? '',
                ],
                'financial' => [
                    'subtotal' => (float) ($p->subtotal ?? $p->amount),
                    'tax_rate' => (float) $p->tax_rate,
                    'tax_amount' => (float) $p->tax_amount,
                    'irpf_rate' => (float) $p->irpf_rate,
                    'irpf_amount' => (float) $p->irpf_amount,
                    'total_amount' => (float) ($p->total_amount ?? $p->amount),
                    'payment_method' => $p->payment_gateway,
                    'gateway_reference' => $p->gateway_order_reference ?: $p->stripe_payment_intent_id,
                ],
            ];

            DB::table('pack_purchases')
                ->where('id', $p->id)
                ->update(['invoice_snapshot' => json_encode($snapshot)]);
        }
    }

    public function down(): void
    {
        Schema::table('pack_purchases', function (Blueprint $table) {
            $table->dropColumn('invoice_snapshot');
        });
    }
};
