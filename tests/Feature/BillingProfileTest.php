<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\PackPurchase;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Support\BillingProfile;
use App\Support\SpanishTaxId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Dados de faturação antes do pagamento: validados, criptografados no cliente e copiados para a
 * compra. Dados fictícios (identificadores com dígito de controle válido, inventados).
 */
class BillingProfileTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Quote $quote;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        TenantSetting::set($this->tenant, 'bank_transfer_iban', 'ES9121000418450200051332');
        TenantSetting::set($this->tenant, 'bank_transfer_enabled', '1');

        $this->quote = Quote::create([
            'tenant_id' => $this->tenant->id, 'status' => 'sent', 'client_name' => 'Padaria Ficticia', 'client_email' => 'ana@ficticio.test',
            'inputs' => ['videos' => 2], 'calculation' => [], 'subtotal' => 1000, 'tax_rate' => 21, 'tax_amount' => 210, 'total_amount' => 1210,
            'deposit_amount' => 605, 'balance_amount' => 605, 'valid_until' => now()->addDays(5),
            'approval_token' => str_repeat('t', 48), 'approval_token_expires_at' => now()->addDays(5),
        ]);
    }

    private function approve(array $billing)
    {
        return $this->post(route('quotes.public.approve', $this->quote->approval_token), ['payment_method' => 'bank_transfer'] + $billing);
    }

    private function companyBilling(array $overrides = []): array
    {
        return array_merge([
            'billing_type' => 'empresa', 'billing_legal_name' => 'Padaria Ficticia S.L.', 'billing_tax_id' => 'b-1234567-4',
            'billing_address' => 'Calle Falsa 123', 'billing_postal_code' => '50001', 'billing_city' => 'Zaragoza', 'billing_country' => 'ES',
        ], $overrides);
    }

    public function test_spanish_tax_ids_are_checked_with_the_control_digit(): void
    {
        $this->assertTrue(SpanishTaxId::isValid('12345678Z'));
        $this->assertFalse(SpanishTaxId::isValid('12345678A'));
        $this->assertTrue(SpanishTaxId::isValid('X1234567L'));
        $this->assertTrue(SpanishTaxId::isValid('B12345674'));
        $this->assertFalse(SpanishTaxId::isValid('B12345675'));
        $this->assertTrue(SpanishTaxId::isValid('Q2826000H'));
        $this->assertFalse(SpanishTaxId::isValid('Q28260008'), 'organismo público exige letra de controle');
        $this->assertFalse(SpanishTaxId::isValid(''));
    }

    public function test_company_without_valid_tax_id_cannot_approve(): void
    {
        $this->approve($this->companyBilling(['billing_tax_id' => 'B12345675']))->assertSessionHasErrors('billing_tax_id');
        $this->approve($this->companyBilling(['billing_tax_id' => '', 'billing_address' => '']))->assertSessionHasErrors(['billing_tax_id', 'billing_address']);

        $this->assertSame(0, PackPurchase::withoutGlobalScopes()->count());
    }

    public function test_billing_data_is_encrypted_on_the_client_and_copied_to_the_purchase(): void
    {
        $this->approve($this->companyBilling())->assertRedirect();

        $cliente = Cliente::withoutGlobalScopes()->where('email', 'ana@ficticio.test')->sole();
        $this->assertSame('B12345674', $cliente->billing_tax_id, 'normalizado');
        $this->assertSame('empresa', $cliente->billing_type);

        $raw = DB::table('clientes')->where('id', $cliente->id)->first();
        $this->assertStringNotContainsString('B12345674', (string) $raw->billing_tax_id, 'criptografado no banco');
        $this->assertStringNotContainsString('Calle Falsa', (string) $raw->billing_address);
        $this->assertArrayNotHasKey('billing_tax_id', $cliente->toArray(), 'fora de JSON');

        $purchase = PackPurchase::withoutGlobalScopes()->where('quote_id', $this->quote->id)->sole();
        $this->assertSame('empresa_autonomo', $purchase->billing_type);
        $this->assertSame('B12345674', $purchase->billing_tax_id);
        $this->assertSame('Padaria Ficticia S.L.', $purchase->billing_legal_name);
        $this->assertSame('50001', $purchase->billing_postal_code);
    }

    public function test_public_page_never_shows_saved_data_in_full_and_allows_reusing_it(): void
    {
        $cliente = Cliente::create(['tenant_id' => $this->tenant->id, 'name' => 'Ana', 'email' => 'ana@ficticio.test', 'password' => 'secret12345']);
        BillingProfile::save($cliente, $this->companyBilling());

        $this->get(route('quotes.public.show', $this->quote->approval_token))
            ->assertOk()
            ->assertSee('NIF terminado en 5674')
            ->assertDontSee('B12345674')
            ->assertDontSee('Calle Falsa');

        $this->approve(['use_saved_billing' => '1'])->assertRedirect();
        $this->assertSame('B12345674', PackPurchase::withoutGlobalScopes()->sole()->billing_tax_id);
    }

    public function test_private_person_only_needs_the_name(): void
    {
        $this->approve(['billing_type' => 'particular', 'billing_legal_name' => 'Ana Ficticia', 'billing_country' => 'ES'])->assertRedirect();

        $this->assertSame('particular', PackPurchase::withoutGlobalScopes()->sole()->billing_type);
    }

    public function test_client_edits_billing_data_in_the_portal(): void
    {
        $cliente = Cliente::create(['tenant_id' => $this->tenant->id, 'name' => 'Ana', 'email' => 'ana@ficticio.test', 'password' => 'secret12345']);

        $this->actingAs($cliente, 'cliente')->put(route('portal.billing.update'), $this->companyBilling())->assertRedirect(route('portal.billing.edit'));
        $this->actingAs($cliente, 'cliente')->get(route('portal.billing.edit'))->assertOk()->assertSee('B12345674');

        $this->assertTrue($cliente->fresh()->hasBillingProfile());
    }

    public function test_views_have_no_inline_css(): void
    {
        $this->assertStringNotContainsString('style="', file_get_contents(resource_path('views/portal/billing/edit.blade.php')));
        $this->assertStringNotContainsString('style="', file_get_contents(resource_path('views/quotes/public/show.blade.php')));
    }
}
