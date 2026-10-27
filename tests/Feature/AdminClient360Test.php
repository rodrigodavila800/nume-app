<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\MarketingPlan;
use App\Models\Production;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminClient360Test extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_view_client_360_ficha_and_regenerate_marketing_plan(): void
    {
        $tenant = Tenant::create([
            'name' => 'Nume Studio',
            'slug' => 'nume-studio',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);

        $cliente = Cliente::create([
            'tenant_id' => $tenant->id,
            'name' => 'Clínica Dental Zaragoza',
            'email' => 'contato@clinicazaragoza.es',
            'password' => 'secret123',
            'company_name' => 'Clínica Zaragoza S.L.',
            'brand_niche' => 'Odontologia Estética',
            'target_audience' => 'Adultos 30-55 anos',
            'key_differentials' => 'Implantes sem dor em 24h',
        ]);

        $cliente->forceFill([
            'anamnesis' => [
                'objective' => 'leads',
                'tone' => 'authority',
                'main_offer' => 'Implante Carga Imediata',
                'average_ticket' => '1500 EUR',
                'ideal_customer' => 'Pacientes 35+ em Zaragoza',
                'posting_frequency' => '3x_week',
            ],
            'anamnesis_completed_at' => now(),
        ])->save();

        $plan = MarketingPlan::create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'month_year' => now()->format('Y-m'),
            'title' => 'Plano Trimestral Implantes',
            'strategic_goal' => 'Aumentar agendamentos via Reels',
            'status' => MarketingPlan::STATUS_ADJUSTMENT,
            'adjustment_request' => 'Incluir mais vídeos de depoimentos reais.',
        ]);

        $response = $this->actingAs($admin)->get(route('clientes.show', $cliente));

        $response->assertOk();
        $response->assertSee('Clínica Dental Zaragoza');
        $response->assertSee('Odontologia Estética');
        $response->assertSee('Implante Carga Imediata');
        $response->assertSee('Incluir mais vídeos de depoimentos reais.');
        $response->assertSee('1. DNA da Marca');
        $response->assertSee('3. Plano Marketing');

        $regenerateResponse = $this->actingAs($admin)->post(route('clientes.marketing-plan.regenerate', $cliente), [
            'adjustment_notes' => 'Priorizar vídeos de depoimentos reais.',
        ]);

        $regenerateResponse->assertRedirect(route('clientes.show', $cliente));
        $regenerateResponse->assertSessionHas('success');

        $plan->refresh();
        $this->assertSame(MarketingPlan::STATUS_DRAFT, $plan->status);
    }

    public function test_other_tenant_cannot_access_client_360_ficha(): void
    {
        $tenantA = Tenant::create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);
        $tenantB = Tenant::create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $adminA = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => 'admin']);

        $clienteB = Cliente::withoutGlobalScopes()->create([
            'tenant_id' => $tenantB->id,
            'name' => 'Cliente Secreto B',
            'email' => 'secreto@b.es',
            'password' => 'secret123',
        ]);

        $this->actingAs($adminA)
            ->get(route('clientes.show', $clienteB->id))
            ->assertNotFound();
    }

    public function test_productions_kanban_displays_operational_queue_and_accepts_ready_status(): void
    {
        $tenant = Tenant::create([
            'name' => 'Tenant Kanban',
            'slug' => 'tenant-kanban',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);

        $production = Production::create([
            'tenant_id' => $tenant->id,
            'title' => 'Campanha Verão',
            'status' => 'in_production',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('productions.index'))
            ->assertOk()
            ->assertSee('Pedidos de Orçamento')
            ->assertSee('Abrir Ficha 360º, DNA e Anamnese');

        $this->actingAs($admin)
            ->patch(route('productions.update-status', $production), ['status' => 'ready'])
            ->assertRedirect();

        $this->assertSame('ready', $production->fresh()->status);
    }
}

