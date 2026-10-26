<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\PackPurchase;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Services\Payment\PackPaymentConfirmer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Orçamento aprovado → dados de faturação → pagamento confirmado → fatura com NIF no portal.
 * Dados fictícios; PDFs só no disco falso.
 */
class QuoteInvoiceFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_quote_invoice_carries_the_billing_data_and_appears_in_the_portal(): void
    {
        Storage::fake('local');

        $tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        TenantSetting::set($tenant, 'bank_transfer_iban', 'ES9121000418450200051332');
        TenantSetting::set($tenant, 'bank_transfer_enabled', '1');
        $admin = User::create(['tenant_id' => $tenant->id, 'name' => 'Admin', 'email' => 'admin@ficticio.test', 'password' => 'secret12345', 'role' => 'admin']);

        $quote = Quote::create([
            'tenant_id' => $tenant->id, 'status' => 'sent', 'client_name' => 'Padaria Ficticia', 'client_email' => 'ana@ficticio.test',
            'project_title' => 'Plano de vídeos 2026-11', 'inputs' => ['videos' => 2], 'calculation' => [],
            'subtotal' => 1000, 'tax_rate' => 21, 'tax_amount' => 210, 'total_amount' => 1210, 'deposit_amount' => 605, 'balance_amount' => 605,
            'valid_until' => now()->addDays(5), 'approval_token' => str_repeat('k', 48), 'approval_token_expires_at' => now()->addDays(5),
        ]);

        $this->post(route('quotes.public.approve', $quote->approval_token), [
            'payment_method' => 'bank_transfer', 'billing_type' => 'empresa', 'billing_legal_name' => 'Padaria Ficticia S.L.',
            'billing_tax_id' => 'B12345674', 'billing_address' => 'Calle Falsa 123', 'billing_postal_code' => '50001',
            'billing_city' => 'Zaragoza', 'billing_country' => 'ES',
        ])->assertRedirect();

        $purchase = PackPurchase::withoutGlobalScopes()->where('quote_id', $quote->id)->sole();
        $this->assertSame(PackPaymentConfirmer::PAID, app(PackPaymentConfirmer::class)->confirmBankTransfer($purchase, $admin));

        $purchase->refresh();
        $this->assertNotNull($purchase->invoice_number);
        $this->assertSame('B12345674', $purchase->invoice_snapshot['client']['tax_id'] ?? null);
        $this->assertSame('Padaria Ficticia S.L.', $purchase->invoice_snapshot['client']['legal_name'] ?? null);

        $cliente = Cliente::withoutGlobalScopes()->where('email', 'ana@ficticio.test')->sole();
        $this->actingAs($cliente, 'cliente')->get(route('portal.quotes.index'))
            ->assertOk()
            ->assertSee('Fatura '.$purchase->invoice_number.' (PDF)')
            ->assertSee(route('portal.purchases.invoice', $purchase), false);

        $this->actingAs($cliente, 'cliente')->get(route('portal.purchases.invoice', $purchase))->assertOk();
    }
}
