<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\Pack;
use App\Models\PackPurchase;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Payment\PackCheckoutPricing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Valida o cálculo confiável de adicionais e gravação do addons_snapshot.
 */
class PackCheckoutPricingAddonsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private Pack $packA;

    private Addon $addonA1;

    private Addon $addonA2;

    private Addon $addonB;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $this->tenantA = Tenant::create([
            'name' => 'Produtora Aurora',
            'slug' => 'produtora-aurora',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Produtora Boreal',
            'slug' => 'produtora-boreal',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $this->packA = Pack::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Pack Aurora Pro',
            'slug' => 'pack-aurora-pro',
            'price' => 500.00,
            'currency' => 'eur',
            'video_quantity' => 5,
            'status' => 'active',
        ]);

        $this->addonA1 = Addon::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Thumbnails Extras',
            'slug' => 'thumbnails-extras',
            'default_price' => 50.00,
            'currency' => 'eur',
            'is_active' => true,
        ]);

        $this->addonA2 = Addon::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Legendas Dinâmicas',
            'slug' => 'legendas-dinamicas',
            'default_price' => 70.00,
            'currency' => 'eur',
            'is_active' => true,
        ]);

        $this->addonB = Addon::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Addon Exclusivo Boreal',
            'slug' => 'addon-boreal',
            'default_price' => 100.00,
            'currency' => 'eur',
            'is_active' => true,
        ]);

        // Vincula os addons do tenant A ao pacote do tenant A
        $this->packA->addons()->attach([
            $this->addonA1->id => ['custom_price' => 45.00, 'sort_order' => 1],
            $this->addonA2->id => ['custom_price' => null, 'sort_order' => 2], // usa default_price (70.00)
        ]);
    }

    public function test_pricing_service_resolves_valid_addons_and_ignores_foreign_or_invalid(): void
    {
        // Passa:
        // - addonA1 (válido, custom_price 45.00)
        // - addonB->id (do tenant B, deve ser ignorado)
        // - 99999 (id inexistente, deve ser ignorado)
        // - 'fake-slug' (inexistente, deve ser ignorado)
        $pricing = PackCheckoutPricing::resolve($this->packA, [
            $this->addonA1->id,
            $this->addonB->id,
            99999,
            'fake-slug',
        ]);

        $this->assertSame(545.00, $pricing->subtotal);
        $this->assertSame(45.00, $pricing->addonsTotal);
        $this->assertCount(1, $pricing->addonsSnapshot);
        $this->assertSame($this->addonA1->id, $pricing->addonsSnapshot[0]['id']);
        $this->assertSame(45.00, $pricing->addonsSnapshot[0]['price']);
        $this->assertSame(['Thumbnails Extras'], $pricing->addonNames);
    }

    public function test_checkout_records_addons_snapshot_and_charges_correctly_with_cross_tenant_logged_user(): void
    {
        // Simula usuário do tenant B logado no sistema
        $this->actingAs(User::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Admin Boreal',
            'email' => 'admin@boreal.test',
            'password' => 'secret12345',
            'role' => 'admin',
        ]));

        $response = $this->post(route('storefront.checkout', [$this->tenantA->slug, $this->packA->slug]), [
            'payment_method' => 'bizum',
            'name' => 'Cliente Teste',
            'email' => 'cliente@teste.com',
            'password' => 'senha-forte-123',
            'billing_type' => 'particular',
            'billing_legal_name' => 'Cliente Teste',
            'billing_country' => 'ES',
            'selected_addons' => [
                $this->addonA1->id, // 45.00
                $this->addonA2->id, // 70.00
                $this->addonB->id,  // de outro tenant: deve ser ignorado
                999999,             // inexistente: deve ser ignorado
            ],
        ]);

        $response->assertOk();

        $purchase = PackPurchase::withoutGlobalScope('tenant')->sole();

        // Subtotal esperado: 500 (pack) + 45 (addon1) + 70 (addon2) = 615.00
        $this->assertEquals(615.00, (float) $purchase->subtotal);

        // addons_purchased contém os 2 válidos
        $this->assertEquals(['Thumbnails Extras', 'Legendas Dinâmicas'], $purchase->addons_purchased);

        // addons_snapshot é gravado com id, name e price
        $this->assertIsArray($purchase->addons_snapshot);
        $this->assertCount(2, $purchase->addons_snapshot);
        $this->assertSame($this->addonA1->id, $purchase->addons_snapshot[0]['id']);
        $this->assertSame(45.00, (float) $purchase->addons_snapshot[0]['price']);
        $this->assertSame($this->addonA2->id, $purchase->addons_snapshot[1]['id']);
        $this->assertSame(70.00, (float) $purchase->addons_snapshot[1]['price']);
    }

    public function test_legacy_addons_fallback_matches_by_slug_or_name(): void
    {
        $legacyPack = Pack::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Pack Legado',
            'slug' => 'pack-legado',
            'price' => 300.00,
            'currency' => 'eur',
            'video_quantity' => 3,
            'status' => 'active',
            'addons' => [
                ['name' => 'Roteiro Express', 'slug' => 'roteiro-express', 'price' => 40.00],
            ],
        ]);

        $pricing = PackCheckoutPricing::resolve($legacyPack, ['roteiro-express', 'addon-fantasma']);

        $this->assertSame(340.00, $pricing->subtotal);
        $this->assertSame(40.00, $pricing->addonsTotal);
        $this->assertCount(1, $pricing->addonsSnapshot);
        $this->assertSame('Roteiro Express', $pricing->addonsSnapshot[0]['name']);
        $this->assertSame(40.00, $pricing->addonsSnapshot[0]['price']);
    }
}
