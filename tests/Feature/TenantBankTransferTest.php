<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Pack;
use App\Models\PackPurchase;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Services\Payment\PaymentMethodAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TenantBankTransferTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private Pack $pack;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Produtora SEPA Cinema',
            'slug' => 'sepa-cinema',
            'subscription_expires_at' => now()->addMonth(),
        ]);

        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
        ]);

        $this->cliente = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente Corporativo',
            'email' => 'financeiro@corporativo.com',
            'password' => bcrypt('password123'),
        ]);

        $this->pack = Pack::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pack Cinema 5 Vídeos',
            'type' => 'one_time',
            'price' => 1000.00,
            'video_quantity' => 5,
            'is_active' => true,
        ]);
    }

    public function test_payment_method_availability_detects_bank_transfer(): void
    {
        $availability = PaymentMethodAvailability::forTenant($this->tenant);
        $this->assertFalse($availability->isMethodActive('bank_transfer'));

        TenantSetting::set($this->tenant->id, 'bank_transfer_enabled', '1');
        $this->assertFalse(PaymentMethodAvailability::forTenant($this->tenant)->isMethodActive('bank_transfer'));

        TenantSetting::set($this->tenant->id, 'bank_transfer_iban', 'ES91 2100 0418 4502 0005 1332');
        $this->assertTrue(PaymentMethodAvailability::forTenant($this->tenant)->isMethodActive('bank_transfer'));

        $activeMethods = PaymentMethodAvailability::forTenant($this->tenant)->getActiveMethods();
        $this->assertTrue($activeMethods['bank_transfer']);
    }

    public function test_admin_can_update_bank_transfer_settings(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.settings.bank-transfer.update'), [
            'bank_transfer_enabled' => '1',
            'bank_transfer_account_holder' => 'SEPA Cinema Audiovisual S.L.',
            'bank_transfer_bank_name' => 'BBVA',
            'bank_transfer_iban' => 'ES9121000418450200051332',
            'bank_transfer_bic' => 'BBVAESMMXXX',
            'bank_transfer_expiration_days' => 5,
            'bank_transfer_instructions' => 'Enviar comprovante para financeiro@sepacinema.com',
        ]);

        $response->assertRedirect(route('admin.settings.bank-transfer'));
        $response->assertSessionHas('status');

        $this->assertSame('1', TenantSetting::get($this->tenant->id, 'bank_transfer_enabled'));
        $this->assertSame('ES9121000418450200051332', TenantSetting::get($this->tenant->id, 'bank_transfer_iban'));
        $this->assertSame('5', TenantSetting::get($this->tenant->id, 'bank_transfer_expiration_days'));
    }

    public function test_non_admin_cannot_update_bank_transfer_settings(): void
    {
        $editor = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'editor',
        ]);

        $response = $this->actingAs($editor)->post(route('admin.settings.bank-transfer.update'), [
            'bank_transfer_enabled' => '1',
            'bank_transfer_iban' => 'ES9121000418450200051332',
        ]);

        $response->assertForbidden();
    }

    public function test_storefront_checkout_bank_transfer_records_pending_billing_commission_and_reference(): void
    {
        TenantSetting::set($this->tenant->id, 'bank_transfer_enabled', '1');
        TenantSetting::set($this->tenant->id, 'bank_transfer_iban', 'ES9121000418450200051332');
        TenantSetting::set($this->tenant->id, 'bank_transfer_expiration_days', '4');

        $response = $this->post(route('storefront.checkout', ['tenant' => $this->tenant, 'pack' => $this->pack]), [
            'name' => 'Carlos Cliente',
            'email' => 'carlos@cliente.es',
            'password' => 'secret123',
            'payment_method' => 'bank_transfer',
            'billing_type' => 'empresa',
            'billing_legal_name' => 'Carlos Producciones S.L.',
            'billing_tax_id' => 'B99887766',
        ]);

        $purchase = PackPurchase::where('tenant_id', $this->tenant->id)->latest('id')->first();
        $this->assertNotNull($purchase);

        $response->assertRedirect(route('storefront.bank_transfer', ['tenant' => $this->tenant, 'purchase' => $purchase]));

        $this->assertSame('bank_transfer', $purchase->payment_gateway);
        $this->assertSame('pending', $purchase->status);
        $this->assertStringStartsWith('BT-', (string) $purchase->bank_transfer_reference);
        $this->assertNotNull($purchase->expires_at);

        // Ponto 1: Comissão fora da Stripe gravada como pending_billing e 10% calculado
        $this->assertSame('pending_billing', $purchase->platform_fee_status);
        $this->assertEquals(100.00, (float) $purchase->application_fee_amount);
    }

    public function test_admin_of_same_tenant_can_confirm_bank_transfer_and_records_audit(): void
    {
        $purchase = PackPurchase::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'pack_id' => $this->pack->id,
            'video_quantity' => 5,
            'amount' => 1210.00,
            'subtotal' => 1000.00,
            'tax_amount' => 210.00,
            'tax_rate' => 21.00,
            'total_amount' => 1210.00,
            'payment_gateway' => 'bank_transfer',
            'status' => 'pending',
            'platform_fee_status' => 'pending_billing',
            'application_fee_amount' => 100.00,
            'bank_transfer_reference' => 'BT-TEST1234',
            'expires_at' => now()->addDays(3),
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.purchases.confirm-bank-transfer', $purchase));

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $purchase->refresh();
        $this->assertSame('paid', $purchase->status);
        $this->assertNotNull($purchase->paid_at);

        // Ponto 2: Auditoria confirmed_by_user_id e confirmed_at
        $this->assertSame($this->admin->id, $purchase->confirmed_by_user_id);
        $this->assertNotNull($purchase->confirmed_at);

        // Fatura oficial gerada
        $this->assertNotNull($purchase->invoice_number);
    }

    public function test_admin_of_different_tenant_cannot_confirm_bank_transfer(): void
    {
        $otherTenant = Tenant::create([
            'name' => 'Outra Produtora',
            'slug' => 'outra-produtora',
            'subscription_expires_at' => now()->addMonth(),
        ]);

        $otherAdmin = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'role' => 'admin',
        ]);

        $purchase = PackPurchase::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'pack_id' => $this->pack->id,
            'video_quantity' => 5,
            'amount' => 1000.00,
            'payment_gateway' => 'bank_transfer',
            'status' => 'pending',
            'bank_transfer_reference' => 'BT-DIFF1234',
            'expires_at' => now()->addDays(3),
        ]);

        // Isolamento de tenant: route model binding barra compra de outro tenant com 404
        $response = $this->actingAs($otherAdmin)
            ->post(route('admin.purchases.confirm-bank-transfer', $purchase));

        $this->assertTrue(in_array($response->status(), [403, 404], true));
        $this->assertSame('pending', $purchase->fresh()->status);
    }

    public function test_editor_cannot_confirm_bank_transfer(): void
    {
        $editor = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'editor',
        ]);

        $purchase = PackPurchase::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'pack_id' => $this->pack->id,
            'video_quantity' => 5,
            'amount' => 1000.00,
            'payment_gateway' => 'bank_transfer',
            'status' => 'pending',
            'bank_transfer_reference' => 'BT-EDIT1234',
            'expires_at' => now()->addDays(3),
        ]);

        $response = $this->actingAs($editor)
            ->post(route('admin.purchases.confirm-bank-transfer', $purchase));

        $response->assertForbidden();
        $this->assertSame('pending', $purchase->fresh()->status);
    }

    public function test_scheduled_command_expires_overdue_bank_transfers_with_explicit_tenant(): void
    {
        Carbon::setTestNow('2026-10-23 12:00:00');

        $expiredPurchase = PackPurchase::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'pack_id' => $this->pack->id,
            'video_quantity' => 5,
            'amount' => 1000.00,
            'payment_gateway' => 'bank_transfer',
            'status' => 'pending',
            'bank_transfer_reference' => 'BT-EXPIRED1',
            'expires_at' => now()->subHour(),
        ]);

        $activePurchase = PackPurchase::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'pack_id' => $this->pack->id,
            'video_quantity' => 5,
            'amount' => 1000.00,
            'payment_gateway' => 'bank_transfer',
            'status' => 'pending',
            'bank_transfer_reference' => 'BT-ACTIVE1',
            'expires_at' => now()->addDay(),
        ]);

        $otherGatewayPurchase = PackPurchase::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'pack_id' => $this->pack->id,
            'video_quantity' => 5,
            'amount' => 1000.00,
            'payment_gateway' => 'stripe',
            'status' => 'pending',
            'expires_at' => now()->subHour(),
        ]);

        $this->artisan('packs:expire-bank-transfers', ['--tenant' => $this->tenant->id])
            ->assertSuccessful()
            ->expectsOutputToContain('Total de compras expiradas: 1');

        $this->assertSame('expired', $expiredPurchase->fresh()->status);
        $this->assertSame('pending', $activePurchase->fresh()->status);
        $this->assertSame('pending', $otherGatewayPurchase->fresh()->status);

        Carbon::setTestNow();
    }
}
