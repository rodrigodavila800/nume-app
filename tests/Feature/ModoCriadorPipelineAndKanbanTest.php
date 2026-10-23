<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Production;
use App\Models\Roteiro;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SofiCopywriterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModoCriadorPipelineAndKanbanTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;
    private Cliente $clienteA;
    private Cliente $clienteB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name' => 'Produtora Alpha',
            'slug' => 'produtora-alpha',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Produtora Beta',
            'slug' => 'produtora-beta',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $this->userA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Staff Alpha',
            'email' => 'alpha@nume.test',
            'password' => bcrypt('secret123'),
            'role' => 'admin',
        ]);

        $this->userB = User::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Staff Beta',
            'email' => 'beta@nume.test',
            'password' => bcrypt('secret123'),
            'role' => 'admin',
        ]);

        $this->clienteA = Cliente::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Cliente Alpha 1',
            'email' => 'cliente.alpha@teste.com',
            'password' => bcrypt('password123'),
            'company_name' => 'Empresa Alpha Tech',
            'brand_niche' => 'Tecnologia B2B',
        ]);

        $this->clienteB = Cliente::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Cliente Beta 1',
            'email' => 'cliente.beta@teste.com',
            'password' => bcrypt('password123'),
            'company_name' => 'Empresa Beta Retail',
            'brand_niche' => 'Varejo',
        ]);
    }

    public function test_authenticated_user_can_access_productions_in_list_and_kanban_view(): void
    {
        $prod1 = Production::create([
            'tenant_id' => $this->tenantA->id,
            'created_by' => $this->userA->id,
            'cliente_id' => $this->clienteA->id,
            'title' => 'Vídeo Institucional Alpha',
            'status' => 'draft',
        ]);

        $prod2 = Production::create([
            'tenant_id' => $this->tenantA->id,
            'created_by' => $this->userA->id,
            'cliente_id' => $this->clienteA->id,
            'title' => 'Vídeo Viral Reels Alpha',
            'status' => 'in_production',
        ]);

        // 1. Acesso à lista padrão
        $responseList = $this->actingAs($this->userA)->get(route('productions.index'));
        $responseList->assertOk();
        $responseList->assertSee('Vídeo Institucional Alpha');
        $responseList->assertSee('Kanban Pipeline');

        // 2. Acesso à visualização Kanban
        $responseKanban = $this->actingAs($this->userA)->get(route('productions.index', ['view' => 'kanban']));
        $responseKanban->assertOk();
        $responseKanban->assertSee('Planejamento & Rascunho');
        $responseKanban->assertSee('Em Gravação & Edição');
        $responseKanban->assertSee('Aguardando Revisão');
        $responseKanban->assertSee('Vídeo Institucional Alpha');
        $responseKanban->assertSee('Vídeo Viral Reels Alpha');
    }

    public function test_user_can_filter_productions_by_cliente(): void
    {
        $clienteA2 = Cliente::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Cliente Alpha 2',
            'email' => 'cliente2@teste.com',
            'password' => bcrypt('password123'),
            'company_name' => 'Segunda Empresa',
        ]);

        $prod1 = Production::create([
            'tenant_id' => $this->tenantA->id,
            'created_by' => $this->userA->id,
            'cliente_id' => $this->clienteA->id,
            'title' => 'Campanha Alpha Tech',
            'status' => 'draft',
        ]);

        $prod2 = Production::create([
            'tenant_id' => $this->tenantA->id,
            'created_by' => $this->userA->id,
            'cliente_id' => $clienteA2->id,
            'title' => 'Campanha Segunda Empresa',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->userA)->get(route('productions.index', [
            'view' => 'kanban',
            'cliente_id' => $this->clienteA->id,
        ]));

        $response->assertOk();
        $response->assertSee('Campanha Alpha Tech');
        $response->assertDontSee('Campanha Segunda Empresa');
    }

    public function test_user_can_update_production_status(): void
    {
        $production = Production::create([
            'tenant_id' => $this->tenantA->id,
            'created_by' => $this->userA->id,
            'cliente_id' => $this->clienteA->id,
            'title' => 'Vídeo Transição Kanban',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->userA)->patch(route('productions.update-status', $production), [
            'status' => 'in_production',
        ]);

        $response->assertRedirect();
        $this->assertEquals('in_production', $production->fresh()->status);
    }

    public function test_tenant_isolation_prevents_updating_status_of_another_tenant_production(): void
    {
        $prodB = Production::create([
            'tenant_id' => $this->tenantB->id,
            'created_by' => $this->userB->id,
            'cliente_id' => $this->clienteB->id,
            'title' => 'Produção Secreta Beta',
            'status' => 'draft',
        ]);

        // User A tenta alterar status da produção do Tenant B
        $response = $this->actingAs($this->userA)->patch(route('productions.update-status', $prodB), [
            'status' => 'published',
        ]);

        $response->assertNotFound();
        $this->assertEquals('draft', $prodB->fresh()->status);
    }

    public function test_sofi_batch_planner_generates_funnel_stages(): void
    {
        $sofiService = app(SofiCopywriterService::class);

        $plan = $sofiService->generateBatchEditorialPlan(
            cliente: $this->clienteA,
            theme: 'IA para Pequenas Empresas',
            quantity: 4,
            language: 'pt_BR'
        );

        $this->assertCount(4, $plan);

        // Verifica que todos os itens possuem funnel_stage e funnel_label
        foreach ($plan as $item) {
            $this->assertArrayHasKey('funnel_stage', $item);
            $this->assertArrayHasKey('funnel_label', $item);
            $this->assertContains($item['funnel_stage'], ['topo', 'meio', 'fundo']);
            $this->assertNotEmpty($item['funnel_label']);
        }

        // Primeiro vídeo deve ser Topo de Funil (interrupção de padrão)
        $this->assertEquals('topo', $plan[0]['funnel_stage']);
        // Quarto vídeo (item 3) deve ser Fundo de Funil (oferta direta)
        $this->assertEquals('fundo', $plan[3]['funnel_stage']);
    }
}
