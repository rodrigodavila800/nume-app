<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Pack;
use App\Models\PackPurchase;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPlansAndSubscriptionsMvcTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $admin;
    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'NUME Zaragoza',
            'slug' => 'nume-zaragoza',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $this->admin = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Produtora Admin',
            'email' => 'admin@nume.es',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $this->cliente = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Carlos Cliente',
            'email' => 'carlos@empresa.com',
            'password' => bcrypt('password123'),
        ]);
    }

    public function test_staff_can_view_plans_index_and_see_only_subscription_plans(): void
    {
        // Cria plano recorrente
        Pack::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Plano Retainer Mensal',
            'slug' => 'plano-retainer-mensal',
            'price' => 590.00,
            'currency' => 'eur',
            'billing_type' => 'subscription',
            'billing_interval' => 'month',
            'commitment_months' => 6,
            'video_quantity' => 4,
            'status' => 'active',
        ]);

        // Cria pacote avulso que não deve aparecer na tela de planos recorrentes
        Pack::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pacote Avulso Pontual',
            'slug' => 'pacote-avulso-pontual',
            'price' => 350.00,
            'currency' => 'eur',
            'billing_type' => 'one_time',
            'video_quantity' => 1,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.plans.index'));

        $response->assertOk();
        $response->assertSee('Plano Retainer Mensal');
        $response->assertDontSee('Pacote Avulso Pontual');
        $response->assertSee('€ 590,00');
    }

    public function test_staff_can_create_and_update_a_recurring_plan(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.plans.store'), [
            'name' => 'Modo Criador Semestral',
            'price' => 890.00,
            'billing_interval' => 'month',
            'commitment_months' => 6,
            'video_quantity' => 8,
            'status' => 'active',
            'features' => ['Roteiros Sofi', 'Edição 4K'],
        ]);

        $response->assertRedirect(route('admin.plans.index'));

        $this->assertDatabaseHas('packs', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Modo Criador Semestral',
            'billing_type' => 'subscription',
            'commitment_months' => 6,
            'video_quantity' => 8,
        ]);

        $plan = Pack::where('name', 'Modo Criador Semestral')->first();

        // Atualização
        $updateResponse = $this->actingAs($this->admin)->put(route('admin.plans.update', $plan), [
            'name' => 'Modo Criador Semestral Pro',
            'price' => 990.00,
            'billing_interval' => 'month',
            'commitment_months' => 12,
            'video_quantity' => 10,
            'status' => 'active',
            'features' => ['Roteiros Sofi', 'Edição 4K', 'Distribuição Social'],
        ]);

        $updateResponse->assertRedirect(route('admin.plans.index'));

        $this->assertDatabaseHas('packs', [
            'id' => $plan->id,
            'name' => 'Modo Criador Semestral Pro',
            'price' => 990.00,
            'commitment_months' => 12,
        ]);
    }

    public function test_staff_can_view_client_subscriptions_dashboard_with_kpis(): void
    {
        $plan = Pack::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Retainer Mensal',
            'price' => 600.00,
            'billing_type' => 'subscription',
            'billing_interval' => 'month',
            'commitment_months' => 6,
            'video_quantity' => 4,
            'status' => 'active',
        ]);

        $subscription = PackPurchase::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'pack_id' => $plan->id,
            'amount' => 600.00,
            'total_amount' => 726.00,
            'status' => 'paid',
            'billing_type' => 'subscription',
            'subscription_status' => 'active',
            'subscription_interval' => 'month',
            'commitment_months' => 6,
            'subscription_cycle_current' => 2,
            'subscription_cycles_total' => 6,
            'video_quantity' => 4,
            'videos_used' => 1,
            'subscription_renews_at' => now()->addMonth(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.subscriptions.index'));

        $response->assertOk();
        $response->assertSee('Carlos Cliente');
        $response->assertSee('Retainer Mensal');
        $response->assertSee('€ 726,00');
        $response->assertSee('2 / 6'); // Ciclo 2 de 6
    }

    public function test_staff_can_view_details_renew_and_cancel_subscription(): void
    {
        $plan = Pack::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Retainer 4 Vídeos',
            'price' => 500.00,
            'billing_type' => 'subscription',
            'video_quantity' => 4,
            'status' => 'active',
        ]);

        $subscription = PackPurchase::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'pack_id' => $plan->id,
            'amount' => 500.00,
            'status' => 'paid',
            'billing_type' => 'subscription',
            'subscription_status' => 'active',
            'subscription_cycle_current' => 1,
            'subscription_cycles_total' => 6,
            'video_quantity' => 4,
            'videos_used' => 4,
        ]);

        // Visualizar detalhes
        $showResponse = $this->actingAs($this->admin)->get(route('admin.subscriptions.show', $subscription));
        $showResponse->assertOk();
        $showResponse->assertSee('Consumo de Cota de Vídeos');

        // Renovar ciclo
        $renewResponse = $this->actingAs($this->admin)->post(route('admin.subscriptions.renew', $subscription));
        $renewResponse->assertRedirect(route('admin.subscriptions.show', $subscription));

        $subscription->refresh();
        $this->assertEquals(2, $subscription->subscription_cycle_current);
        $this->assertEquals(8, $subscription->video_quantity); // 4 + 4

        // Cancelar assinatura
        $cancelResponse = $this->actingAs($this->admin)->post(route('admin.subscriptions.cancel', $subscription));
        $cancelResponse->assertRedirect(route('admin.subscriptions.show', $subscription));

        $subscription->refresh();
        $this->assertEquals('canceled', $subscription->subscription_status);
    }

    public function test_multi_tenant_isolation_protects_subscriptions_and_plans(): void
    {
        $otherTenant = Tenant::create([
            'name' => 'Outra Produtora',
            'slug' => 'outra-produtora',
            'status' => 'active',
        ]);

        $otherPlan = Pack::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Plano do Outro Tenant',
            'price' => 1500.00,
            'billing_type' => 'subscription',
            'video_quantity' => 12,
            'status' => 'active',
        ]);

        $otherClient = Cliente::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Cliente Alheio',
            'email' => 'alheio@outro.com',
            'password' => bcrypt('123'),
        ]);

        $otherSub = PackPurchase::create([
            'tenant_id' => $otherTenant->id,
            'cliente_id' => $otherClient->id,
            'pack_id' => $otherPlan->id,
            'amount' => 1500.00,
            'billing_type' => 'subscription',
            'subscription_status' => 'active',
            'video_quantity' => 12,
        ]);

        // Ao listar planos, o plano do outro tenant não deve aparecer
        $plansResponse = $this->actingAs($this->admin)->get(route('admin.plans.index'));
        $plansResponse->assertDontSee('Plano do Outro Tenant');

        // Ao listar assinaturas, a assinatura do outro tenant não deve aparecer
        $subsResponse = $this->actingAs($this->admin)->get(route('admin.subscriptions.index'));
        $subsResponse->assertDontSee('Cliente Alheio');

        // Tentativa de acesso direto deve ser 404 ou 403 pelo escopo global BelongsToTenant
        $directShowResponse = $this->actingAs($this->admin)->get(route('admin.subscriptions.show', $otherSub));
        $this->assertTrue(in_array($directShowResponse->status(), [403, 404]));
    }
}
