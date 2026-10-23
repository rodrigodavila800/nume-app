<?php

namespace Tests\Feature;

use App\Models\Pack;
use App\Models\PackPurchase;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * O {pack} do checkout da vitrine é resolvido só no tenant da vitrine. Dados fictícios.
 * Checkout por Bizum (Redsys): nenhuma chamada externa, só a tela de redirecionamento.
 */
class StorefrontCheckoutPackBindingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Pack $packA;

    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        // A produtora B é criada primeiro: o binding antigo pegaria o pacote dela (menor id).
        $this->tenantB = Tenant::create(['name' => 'Produtora Boreal', 'slug' => 'produtora-boreal', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        Pack::create(['tenant_id' => $this->tenantB->id, 'name' => 'Reels Boreal', 'slug' => 'reels', 'price' => 990, 'currency' => 'eur', 'video_quantity' => 8, 'status' => 'active']);

        $this->tenantA = Tenant::create(['name' => 'Produtora Aurora', 'slug' => 'produtora-aurora', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        $this->packA = Pack::create(['tenant_id' => $this->tenantA->id, 'name' => 'Reels Aurora', 'slug' => 'reels', 'price' => 490, 'currency' => 'eur', 'video_quantity' => 4, 'status' => 'active']);
    }

    public function test_repeated_slug_in_another_tenant_does_not_collide(): void
    {
        $this->checkout()->assertOk();

        $purchase = PackPurchase::withoutGlobalScope('tenant')->sole();
        $this->assertSame($this->packA->id, $purchase->pack_id);
        $this->assertSame($this->tenantA->id, $purchase->tenant_id);
    }

    public function test_logged_staff_of_another_tenant_does_not_get_404_on_a_legit_purchase(): void
    {
        $this->actingAs(User::create(['tenant_id' => $this->tenantB->id, 'name' => 'Staff Boreal', 'email' => 'staff@boreal.test', 'password' => 'secret12345', 'role' => 'admin']));

        $this->checkout()->assertOk();

        $this->assertSame($this->packA->id, PackPurchase::withoutGlobalScope('tenant')->sole()->pack_id);
    }

    public function test_slug_that_only_exists_in_another_tenant_returns_404(): void
    {
        Pack::create(['tenant_id' => $this->tenantB->id, 'name' => 'Exclusivo Boreal', 'slug' => 'exclusivo-boreal', 'price' => 100, 'currency' => 'eur', 'video_quantity' => 1, 'status' => 'active']);

        $this->checkout('exclusivo-boreal')->assertNotFound();
        $this->assertSame(0, PackPurchase::withoutGlobalScope('tenant')->count());
    }

    private function checkout(string $slug = 'reels')
    {
        return $this->post("/vitrine/produtora-aurora/pacotes/{$slug}/checkout", [
            'payment_method' => 'bizum',
            'email' => 'visitante@ficticio.test',
            'password' => 'secret123',
            'name' => 'Visitante Ficticio',
        ]);
    }
}
