<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Pack;
use App\Models\PackPurchase;
use App\Models\Tenant;
use App\Services\Payment\PackPaymentConfirmer;
use App\Services\TelemetryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformFeeTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Tenant $numeTenant;

    protected Cliente $cliente;

    protected Pack $pack;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Produtora Parceira',
            'slug' => 'produtora-parceira',
            'status' => 'active',
            'subscription_expires_at' => now()->addMonth(),
        ]);

        $this->numeTenant = Tenant::create([
            'name' => 'Nume Creative S.L.',
            'slug' => 'nume',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $this->cliente = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente Teste',
            'email' => 'cliente@teste.com',
            'password' => bcrypt('secret123'),
        ]);

        $this->pack = Pack::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pack Reels',
            'slug' => 'pack-reels',
            'price' => 1000,
            'currency' => 'eur',
            'video_quantity' => 5,
            'status' => 'active',
        ]);
    }

    public function test_stripe_payment_sets_platform_fee_status_to_collected_at_gateway(): void
    {
        $sessionId = 'cs_test_abc123';

        $purchase = PackPurchase::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'pack_id' => $this->pack->id,
            'payment_gateway' => 'stripe',
            'stripe_checkout_session_id' => $sessionId,
            'subtotal' => 1000.00,
            'tax_amount' => 210.00,
            'total_amount' => 1210.00,
            'amount' => 1210.00,
            'application_fee_amount' => 100.00, // 10%
            'video_quantity' => 5,
            'status' => 'pending',
            'platform_fee_status' => 'not_applicable',
        ]);

        $confirmer = app(PackPaymentConfirmer::class);
        $fakeSession = (object) [
            'id' => $sessionId,
            'payment_status' => 'paid',
            'amount_total' => 121000,
            'currency' => 'eur',
            'payment_intent' => 'pi_fake_123',
            'client_reference_id' => (string) $purchase->id,
            'metadata' => (object) [
                'pack_purchase_id' => (string) $purchase->id,
                'tenant_id' => (string) $this->tenant->id,
            ],
        ];

        $status = $confirmer->confirmStripeSession($purchase, $fakeSession);

        $this->assertEquals(PackPaymentConfirmer::PAID, $status);
        $fresh = $purchase->fresh();
        $this->assertEquals('paid', $fresh->status);
        $this->assertEquals('collected_at_gateway', $fresh->platform_fee_status);
        $this->assertTrue($fresh->isPlatformFeeCollected());
        $this->assertFalse($fresh->isPlatformFeePending());
    }

    public function test_redsys_payment_for_tenant_sets_platform_fee_status_to_pending_billing(): void
    {
        $order = '261023000001';

        $purchase = PackPurchase::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'pack_id' => $this->pack->id,
            'payment_gateway' => 'bizum',
            'gateway_order_reference' => $order,
            'subtotal' => 1000.00,
            'tax_amount' => 210.00,
            'total_amount' => 1210.00,
            'amount' => 1210.00,
            'application_fee_amount' => 100.00, // 10%
            'video_quantity' => 5,
            'status' => 'pending',
            'platform_fee_status' => 'not_applicable',
        ]);

        $confirmer = app(PackPaymentConfirmer::class);
        $data = [
            'Ds_Order' => $order,
            'Ds_Amount' => '121000',
            'Ds_Currency' => '978',
            'Ds_Response' => '0000',
            'Ds_PayMethod' => 'z',
        ];

        $status = $confirmer->confirmRedsys($purchase, $data);

        $this->assertEquals(PackPaymentConfirmer::PAID, $status);
        $fresh = $purchase->fresh();
        $this->assertEquals('paid', $fresh->status);
        $this->assertEquals('pending_billing', $fresh->platform_fee_status);
        $this->assertTrue($fresh->isPlatformFeePending());
        $this->assertFalse($fresh->isPlatformFeeCollected());
    }

    public function test_redsys_payment_for_nume_tenant_sets_platform_fee_status_to_not_applicable(): void
    {
        $order = '261023000002';
        $numeCliente = Cliente::create([
            'tenant_id' => $this->numeTenant->id,
            'name' => 'Cliente Nume',
            'email' => 'cliente@nume.com',
            'password' => bcrypt('secret123'),
        ]);

        $numePack = Pack::create([
            'tenant_id' => $this->numeTenant->id,
            'name' => 'Pack Oficial Nume',
            'slug' => 'pack-oficial-nume',
            'price' => 500,
            'currency' => 'eur',
            'video_quantity' => 2,
            'status' => 'active',
        ]);

        $purchase = PackPurchase::create([
            'tenant_id' => $this->numeTenant->id,
            'cliente_id' => $numeCliente->id,
            'pack_id' => $numePack->id,
            'payment_gateway' => 'bizum',
            'gateway_order_reference' => $order,
            'subtotal' => 500.00,
            'tax_amount' => 105.00,
            'total_amount' => 605.00,
            'amount' => 605.00,
            'application_fee_amount' => 0.00,
            'video_quantity' => 2,
            'status' => 'pending',
            'platform_fee_status' => 'not_applicable',
        ]);

        $confirmer = app(PackPaymentConfirmer::class);
        $data = [
            'Ds_Order' => $order,
            'Ds_Amount' => '60500',
            'Ds_Currency' => '978',
            'Ds_Response' => '0000',
            'Ds_PayMethod' => 'z',
        ];

        $status = $confirmer->confirmRedsys($purchase, $data);

        $this->assertEquals(PackPaymentConfirmer::PAID, $status);
        $fresh = $purchase->fresh();
        $this->assertEquals('paid', $fresh->status);
        $this->assertEquals('not_applicable', $fresh->platform_fee_status);
        $this->assertFalse($fresh->isPlatformFeePending());
    }

    public function test_telemetry_service_distinguishes_collected_from_pending_fees(): void
    {
        // 1. Compra paga via Stripe Connect (comissão retida)
        PackPurchase::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'pack_id' => $this->pack->id,
            'payment_gateway' => 'stripe',
            'subtotal' => 1000.00,
            'tax_amount' => 210.00,
            'total_amount' => 1210.00,
            'amount' => 1210.00,
            'application_fee_amount' => 100.00,
            'platform_fee_status' => 'collected_at_gateway',
            'video_quantity' => 5,
            'status' => 'paid',
        ]);

        // 2. Compra paga via Redsys próprio da produtora (comissão a faturar)
        PackPurchase::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'pack_id' => $this->pack->id,
            'payment_gateway' => 'redsys_card',
            'subtotal' => 500.00,
            'tax_amount' => 105.00,
            'total_amount' => 605.00,
            'amount' => 605.00,
            'application_fee_amount' => 50.00,
            'platform_fee_status' => 'pending_billing',
            'video_quantity' => 3,
            'status' => 'paid',
        ]);

        $telemetry = app(TelemetryService::class)->getSnapshot();

        $this->assertEquals(150.00, $telemetry['kpis']['totalPlatformFee']);
        $this->assertEquals(100.00, $telemetry['kpis']['collectedPlatformFee']);
        $this->assertEquals(50.00, $telemetry['kpis']['pendingBillingPlatformFee']);
    }
}
