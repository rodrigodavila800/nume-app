<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Pack;
use App\Models\PackPurchase;
use App\Models\Tenant;
use App\Services\Payment\PackBalancePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * O modo simulado da cobrança do saldo 50/50 só existe na suíte de testes.
 * Fora dela, sem chave válida da Stripe, nada é cobrado nem marcado como pago.
 */
class PackBalancePaymentSimulationTest extends TestCase
{
    use RefreshDatabase;

    private PackPurchase $purchase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        $pack = Pack::create(['tenant_id' => $tenant->id, 'name' => 'Institucional 50/50', 'slug' => 'institucional', 'price' => 2000, 'currency' => 'eur', 'video_quantity' => 1, 'status' => 'active']);
        $cliente = Cliente::create(['tenant_id' => $tenant->id, 'name' => 'Cliente A', 'email' => 'cliente-a@empresa.com', 'password' => bcrypt('secret123')]);

        $this->purchase = PackPurchase::create([
            'tenant_id' => $tenant->id,
            'pack_id' => $pack->id,
            'cliente_id' => $cliente->id,
            'status' => 'paid',
            'amount' => 1210,
            'total_amount' => 2420,
            'billing_mode' => 'split_50_50',
            'deposit_amount' => 1210,
            'balance_amount' => 1210,
            'balance_status' => 'pending',
            'video_quantity' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        $this->app['env'] = 'testing';

        parent::tearDown();
    }

    public function test_outside_testing_without_valid_key_nothing_is_charged_or_marked_paid(): void
    {
        $this->app['env'] = 'production';

        foreach ([null, '', 'sk_test_mock_123', 'chave-qualquer'] as $secret) {
            config(['services.stripe.secret' => $secret]);

            $result = app(PackBalancePaymentService::class)->chargeBalance($this->purchase->fresh());

            $this->assertFalse($result['success'], 'Saldo quitado sem chave válida com secret='.var_export($secret, true));
            $this->assertNull($result['payment_intent_id']);
            $this->assertSame('pending', $this->purchase->fresh()->balance_status);
        }
    }

    public function test_simulated_mode_still_works_inside_the_test_suite(): void
    {
        config(['services.stripe.secret' => null]);

        $result = app(PackBalancePaymentService::class)->chargeBalance($this->purchase->fresh());

        $this->assertTrue($result['success']);
        $this->assertSame('paid', $this->purchase->fresh()->balance_status);
    }
}
