<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Pack;
use App\Models\PackPurchase;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Cliente logado no guard 'cliente' só é reaproveitado se for do mesmo tenant da vitrine.
 * Se for de outro tenant, a vitrine trata como visitante e a compra registra um cliente do tenant da vitrine.
 */
class StorefrontCrossTenantClientAuthTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private Pack $packA;

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
            'name' => 'Reels Aurora',
            'slug' => 'reels-aurora',
            'price' => 490,
            'currency' => 'eur',
            'video_quantity' => 4,
            'status' => 'active',
        ]);
    }

    public function test_logged_client_of_another_tenant_sees_guest_checkout_form(): void
    {
        $clienteB = Cliente::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Cliente Boreal',
            'email' => 'cliente@boreal.test',
            'password' => Hash::make('secret12345'),
        ]);

        $response = $this->actingAs($clienteB, 'cliente')
            ->get(route('storefront.show', $this->tenantA->slug));

        $response->assertOk();
        $response->assertDontSee('Autenticado como:');
        $response->assertSee('Novo Cliente');
        $response->assertSee('Já sou Cliente');
    }

    public function test_logged_client_of_another_tenant_buys_as_tenant_a_client_and_switches_session(): void
    {
        $clienteB = Cliente::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Cliente Boreal',
            'email' => 'cliente@boreal.test',
            'password' => Hash::make('secret12345'),
        ]);

        $response = $this->actingAs($clienteB, 'cliente')
            ->post(route('storefront.checkout', [$this->tenantA->slug, $this->packA->slug]), [
                'payment_method' => 'bizum',
                'name' => 'Cliente na Aurora',
                'email' => 'cliente.novo@aurora.test',
                'password' => 'senha-aurora-123',
                'billing_type' => 'particular',
                'billing_legal_name' => 'Cliente na Aurora',
                'billing_country' => 'ES',
            ]);

        $response->assertOk(); // Tela de redirect Bizum

        $purchase = PackPurchase::withoutGlobalScope('tenant')->sole();
        $this->assertSame($this->tenantA->id, $purchase->tenant_id);

        $clienteA = Cliente::withoutGlobalScope('tenant')->where('email', 'cliente.novo@aurora.test')->first();
        $this->assertNotNull($clienteA);
        $this->assertSame($this->tenantA->id, $clienteA->tenant_id);
        $this->assertSame($clienteA->id, $purchase->cliente_id);

        // A sessão do guard 'cliente' agora é o cliente da Aurora
        $this->assertSame($clienteA->id, auth('cliente')->id());
    }

    public function test_logged_client_of_same_tenant_is_reused_directly(): void
    {
        $clienteA = Cliente::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Cliente Aurora',
            'email' => 'cliente@aurora.test',
            'password' => Hash::make('secret12345'),
        ]);

        // No show vê badge de autenticado
        $this->actingAs($clienteA, 'cliente')
            ->get(route('storefront.show', $this->tenantA->slug))
            ->assertOk()
            ->assertSee('Autenticado como:')
            ->assertSee($clienteA->email);

        // No checkout não precisa enviar senha/email
        $response = $this->actingAs($clienteA, 'cliente')
            ->post(route('storefront.checkout', [$this->tenantA->slug, $this->packA->slug]), [
                'payment_method' => 'bizum',
                'billing_type' => 'particular',
                'billing_legal_name' => 'Cliente Aurora',
                'billing_country' => 'ES',
            ]);

        $response->assertOk();

        $purchase = PackPurchase::withoutGlobalScope('tenant')->sole();
        $this->assertSame($clienteA->id, $purchase->cliente_id);
        $this->assertSame($this->tenantA->id, $purchase->tenant_id);
    }
}
