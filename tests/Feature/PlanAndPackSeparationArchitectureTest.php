<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Pack;
use App\Models\PackPurchase;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanAndPackSeparationArchitectureTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'NUME Creative Zaragoza',
            'slug' => 'nume',
            'status' => 'active',
            'social_whatsapp' => '+34 976 000 111',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $this->cliente = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Alejandro Martínez',
            'email' => 'alejandro@empresa.es',
            'password' => bcrypt('password123'),
        ]);
    }

    public function test_pack_model_provides_scopes_and_helpers_for_recurring_and_one_off_packs(): void
    {
        $recurringPlan = Pack::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Retainer Mensal Pro',
            'slug' => 'retainer-mensal-pro',
            'price' => 1200.00,
            'currency' => 'eur',
            'billing_type' => 'subscription',
            'billing_interval' => 'month',
            'commitment_months' => 3,
            'video_quantity' => 8,
            'status' => 'active',
        ]);

        $oneOffPack = Pack::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Vídeo Institucional 4K',
            'slug' => 'video-institucional-4k',
            'price' => 850.00,
            'currency' => 'eur',
            'billing_type' => 'one_time',
            'video_quantity' => 1,
            'status' => 'active',
        ]);

        // Helpers semânticos
        $this->assertTrue($recurringPlan->isSubscription());
        $this->assertFalse($recurringPlan->isOneTime());
        $this->assertEquals('Mensal', $recurringPlan->billing_interval_label);

        $this->assertFalse($oneOffPack->isSubscription());
        $this->assertTrue($oneOffPack->isOneTime());
        $this->assertEquals('Pagamento Único', $oneOffPack->billing_interval_label);

        // Escopos semânticos
        $recurringCount = Pack::recurringPlans()->count();
        $oneOffCount = Pack::oneOffPacks()->count();

        $this->assertEquals(1, $recurringCount);
        $this->assertEquals(1, $oneOffCount);
    }

    public function test_portal_provides_dedicated_plans_and_packs_routes_and_navigation_menus(): void
    {
        Pack::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Plano de Continuidade Reels',
            'slug' => 'plano-continuidade-reels',
            'price' => 900.00,
            'currency' => 'eur',
            'billing_type' => 'subscription',
            'billing_interval' => 'month',
            'commitment_months' => 3,
            'video_quantity' => 6,
            'status' => 'active',
        ]);

        Pack::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Campanha Pontual Fechada',
            'slug' => 'campanha-pontual-fechada',
            'price' => 500.00,
            'currency' => 'eur',
            'billing_type' => 'one_time',
            'video_quantity' => 2,
            'status' => 'active',
        ]);

        // 1. Tela dedicada de Planos (route: portal.plans.index)
        $plansResponse = $this->actingAs($this->cliente, 'cliente')
            ->get(route('portal.plans.index'));

        $plansResponse->assertStatus(200);
        $plansResponse->assertSee('Planos de Conteúdo com Escala & Previsibilidade');
        $plansResponse->assertSee('Plano de Continuidade Reels');
        $plansResponse->assertSee('Assinar Plano de Conteúdo');
        $plansResponse->assertDontSee('Campanha Pontual Fechada');
        // Menus dedicados no layout
        $plansResponse->assertSee(route('portal.plans.index'));
        $plansResponse->assertSee(route('portal.packs.index'));

        // 2. Tela dedicada de Pacotes Avulsos (route: portal.packs.index)
        $packsResponse = $this->actingAs($this->cliente, 'cliente')
            ->get(route('portal.packs.index'));

        $packsResponse->assertStatus(200);
        $packsResponse->assertSee('Pacotes Avulsos para Campanhas & Lançamentos');
        $packsResponse->assertSee('Campanha Pontual Fechada');
        $packsResponse->assertSee('Contratar Produção Avulsa');
        $packsResponse->assertDontSee('Plano de Continuidade Reels');
        // Menus dedicados no layout
        $packsResponse->assertSee(route('portal.plans.index'));
        $packsResponse->assertSee(route('portal.packs.index'));
    }

    public function test_storefront_renders_dedicated_sections_for_plans_and_one_off_packs(): void
    {
        Pack::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Retainer Viral 4K',
            'slug' => 'retainer-viral-4k',
            'price' => 1500.00,
            'currency' => 'eur',
            'billing_type' => 'subscription',
            'billing_interval' => 'month',
            'commitment_months' => 6,
            'video_quantity' => 10,
            'status' => 'active',
        ]);

        Pack::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pack Avulso Lançamento',
            'slug' => 'pack-avulso-lancamento',
            'price' => 750.00,
            'currency' => 'eur',
            'billing_type' => 'one_time',
            'video_quantity' => 3,
            'status' => 'active',
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        // Navbar do Prism com links dedicados
        $response->assertSee('href="#planos"', false);
        $response->assertSee('href="#pacotes"', false);

        // Seção dedicada de Planos
        $response->assertSee('id="planos"', false);
        $response->assertSee('Planos Recorrentes de Conteúdo');
        $response->assertSee('Retainer Viral 4K');
        $response->assertSee('Assinar Plano Recorrente');

        // Seção dedicada de Pacotes
        $response->assertSee('id="pacotes"', false);
        $response->assertSee('Pacotes de Vídeos Estratégicos');
        $response->assertSee('Pack Avulso Lançamento');
        $response->assertSee('Contratar Produção Avulsa');
    }

    public function test_client_dashboard_displays_active_contracts_separately_from_purchases(): void
    {
        $plan = Pack::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Retainer Mensal 4K',
            'slug' => 'retainer-mensal-4k',
            'price' => 1000.00,
            'currency' => 'eur',
            'billing_type' => 'subscription',
            'video_quantity' => 4,
            'status' => 'active',
        ]);

        // Assinatura ativa
        PackPurchase::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'pack_id' => $plan->id,
            'payment_gateway' => 'stripe',
            'status' => 'paid',
            'subscription_status' => 'active',
            'subscription_interval' => 'month',
            'commitment_months' => 3,
            'subscription_cycle_current' => 1,
            'subscription_cycles_total' => 3,
            'subscription_renews_at' => now()->addDays(20),
            'subscription_ends_at' => now()->addMonths(3),
            'amount' => 1000.00,
            'subtotal' => 1000.00,
            'video_quantity' => 4,
            'videos_used' => 1,
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($this->cliente, 'cliente')
            ->get(route('portal.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Contratos Recorrentes & Assinaturas Ativas');
        $response->assertSee('Pacotes Avulsos & Histórico de Faturas');
        $response->assertSee('Ciclo 1 de 3');
        $response->assertSee('Retainer Mensal 4K');
    }
}
