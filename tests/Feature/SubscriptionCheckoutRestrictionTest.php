<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Pack;
use App\Models\PackPurchase;
use App\Models\Tenant;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SubscriptionCheckoutRestrictionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $this->tenant = Tenant::create([
            'name' => 'Produtora Recorrente Teste',
            'slug' => 'produtora-recorrente',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $this->admin = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Produtora Admin',
            'email' => 'admin@recorrente.test',
            'password' => 'secret12345',
            'role' => 'admin',
        ]);

        $this->cliente = Cliente::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente Teste',
            'email' => 'cliente@recorrente.test',
            'password' => 'secret12345',
        ]);
    }

    public function test_storefront_checkout_rejects_subscription_pack(): void
    {
        $pack = Pack::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Plano Mensal Recorrente',
            'slug' => 'plano-mensal-recorrente',
            'price' => 200,
            'currency' => 'eur',
            'video_quantity' => 4,
            'status' => 'active',
            'billing_type' => 'subscription',
        ]);

        $response = $this->post(route('storefront.checkout', [
            'tenant' => $this->tenant->slug,
            'pack' => $pack->slug,
        ]), [
            'payment_method' => 'stripe',
            'email' => 'cliente@recorrente.test',
            'password' => 'secret12345',
        ]);

        $response->assertSessionHas('error', 'As assinaturas de pacotes recorrentes estão temporariamente indisponíveis para novas contratações.');
    }

    public function test_portal_checkout_rejects_subscription_pack_with_json_when_embedded(): void
    {
        $this->actingAs($this->cliente, 'cliente');

        $pack = Pack::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Plano Recorrente Portal',
            'slug' => 'plano-recorrente-portal',
            'price' => 300,
            'currency' => 'eur',
            'video_quantity' => 6,
            'status' => 'active',
            'billing_type' => 'subscription',
        ]);

        $response = $this->postJson(route('portal.packs.checkout', $pack), [
            'payment_method' => 'stripe',
            'embedded' => true,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'status' => 'error',
            'message' => 'As assinaturas de pacotes recorrentes estão temporariamente indisponíveis para novas contratações.',
        ]);
    }

    public function test_renew_subscription_cycle_throws_domain_exception_if_status_not_paid(): void
    {
        $pack = Pack::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Plano Mensal',
            'slug' => 'plano-mensal',
            'price' => 150,
            'currency' => 'eur',
            'video_quantity' => 2,
            'status' => 'active',
            'billing_type' => 'subscription',
        ]);

        $purchase = PackPurchase::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'pack_id' => $pack->id,
            'payment_gateway' => 'stripe',
            'amount' => 150,
            'total_amount' => 150,
            'subtotal' => 150,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'irpf_rate' => 0,
            'irpf_amount' => 0,
            'billing_mode' => 'subscription',
            'billing_type' => 'particular',
            'video_quantity' => 2,
            'status' => 'pending',
            'subscription_cycle_current' => 1,
            'subscription_cycles_total' => 3,
        ]);

        $this->expectException(DomainException::class);
        $purchase->renewSubscriptionCycle();
    }

    public function test_admin_renew_route_catches_domain_exception_and_redirects_with_error(): void
    {
        $this->actingAs($this->admin);

        $pack = Pack::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Plano Semanal',
            'slug' => 'plano-semanal',
            'price' => 100,
            'currency' => 'eur',
            'video_quantity' => 1,
            'status' => 'active',
            'billing_type' => 'subscription',
        ]);

        $purchase = PackPurchase::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'pack_id' => $pack->id,
            'payment_gateway' => 'stripe',
            'amount' => 100,
            'total_amount' => 100,
            'subtotal' => 100,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'irpf_rate' => 0,
            'irpf_amount' => 0,
            'billing_mode' => 'subscription',
            'billing_type' => 'particular',
            'video_quantity' => 1,
            'status' => 'pending',
            'subscription_cycle_current' => 1,
            'subscription_cycles_total' => 3,
        ]);

        $response = $this->post(route('admin.subscriptions.renew', $purchase));

        $response->assertRedirect(route('admin.subscriptions.show', $purchase));
        $response->assertSessionHas('error');
    }

    public function test_admin_subscriptions_index_lists_purchases_by_billing_mode_or_pack(): void
    {
        $this->actingAs($this->admin);

        $pack = Pack::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Plano Trimestral',
            'slug' => 'plano-trimestral',
            'price' => 500,
            'currency' => 'eur',
            'video_quantity' => 10,
            'status' => 'active',
            'billing_type' => 'subscription',
        ]);

        // Purchase com billing_type = particular (fiscal) mas billing_mode = subscription
        $purchase = PackPurchase::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'pack_id' => $pack->id,
            'payment_gateway' => 'stripe',
            'amount' => 500,
            'total_amount' => 500,
            'subtotal' => 500,
            'tax_rate' => 0,
            'tax_amount' => 0,
            'irpf_rate' => 0,
            'irpf_amount' => 0,
            'billing_mode' => 'subscription',
            'billing_type' => 'particular',
            'video_quantity' => 10,
            'status' => 'paid',
            'subscription_status' => 'active',
            'subscription_cycle_current' => 1,
            'subscription_cycles_total' => 3,
        ]);

        $response = $this->get(route('admin.subscriptions.index'));

        $response->assertOk();
        $response->assertSee('Plano Trimestral');
    }
}
