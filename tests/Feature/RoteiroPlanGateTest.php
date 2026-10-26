<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\MarketingPlan;
use App\Models\Production;
use App\Models\Roteiro;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Roteiro só depois do plano de marketing aprovado; admin libera com justificativa. Dados fictícios.
 */
class RoteiroPlanGateTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private User $editor;

    private Cliente $cliente;

    private Production $production;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        $this->admin = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Admin', 'email' => 'admin@ficticio.test', 'password' => 'secret12345', 'role' => 'admin']);
        $this->editor = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Editor', 'email' => 'editor@ficticio.test', 'password' => 'secret12345', 'role' => 'editor']);
        $this->cliente = Cliente::create(['tenant_id' => $this->tenant->id, 'name' => 'Cliente', 'email' => 'cliente@ficticio.test', 'password' => 'secret12345']);
        $this->production = Production::create([
            'tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'created_by' => $this->admin->id,
            'title' => 'Vídeo da padaria', 'status' => 'draft',
        ]);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 500)]);
    }

    private function approvePlan(): void
    {
        MarketingPlan::create(['tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'month_year' => now()->format('Y-m')])
            ->approveBy($this->cliente);
    }

    private function store(User $user, array $extra = [])
    {
        return $this->actingAs($user)->from(route('roteiros.create', $this->production))
            ->post(route('roteiros.store', $this->production), ['content' => str_repeat('Cena do pão saindo do forno. ', 3)] + $extra);
    }

    public function test_editor_cannot_create_a_roteiro_before_the_plan_is_approved(): void
    {
        $this->actingAs($this->editor)->get(route('roteiros.create', $this->production))
            ->assertOk()->assertSee('O cliente ainda não aprovou o plano de marketing')->assertDontSee('name="plan_override_reason"', false);

        $this->store($this->editor, ['plan_override_reason' => 'Quero passar na frente'])->assertSessionHasErrors('plan_override_reason');
        $this->actingAs($this->editor)->postJson(route('roteiros.generate-sofi', $this->production))->assertStatus(422);

        $this->assertSame(0, Roteiro::forTenant($this->tenant->id)->count());
    }

    public function test_admin_creates_without_plan_only_with_a_reason_that_is_recorded(): void
    {
        $this->store($this->admin)->assertSessionHasErrors('plan_override_reason');
        $this->assertSame(0, Roteiro::forTenant($this->tenant->id)->count());

        $this->store($this->admin, ['plan_override_reason' => 'Vídeo institucional da própria NUME'])->assertRedirect();

        $roteiro = Roteiro::forTenant($this->tenant->id)->sole();
        $this->assertSame('Vídeo institucional da própria NUME', $roteiro->plan_override_reason);
        $this->assertSame($this->admin->id, $roteiro->plan_override_by);

        $this->actingAs($this->admin)->get(route('roteiros.show', [$this->production, $roteiro]))
            ->assertSee('Criado sem plano aprovado.');
    }

    public function test_with_an_approved_plan_the_editor_creates_normally(): void
    {
        $this->approvePlan();

        $this->actingAs($this->editor)->get(route('roteiros.create', $this->production))
            ->assertOk()->assertDontSee('O cliente ainda não aprovou o plano de marketing');
        $this->actingAs($this->editor)->postJson(route('roteiros.generate-sofi', $this->production))->assertOk();
        $this->store($this->editor)->assertRedirect();

        $this->assertNull(Roteiro::forTenant($this->tenant->id)->sole()->plan_override_reason);
    }

    public function test_a_draft_or_other_clients_plan_does_not_count(): void
    {
        MarketingPlan::create(['tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'month_year' => now()->format('Y-m'), 'status' => MarketingPlan::STATUS_DRAFT]);
        $outro = Cliente::create(['tenant_id' => $this->tenant->id, 'name' => 'Outro', 'email' => 'outro@ficticio.test', 'password' => 'secret12345']);
        MarketingPlan::create(['tenant_id' => $this->tenant->id, 'cliente_id' => $outro->id, 'month_year' => now()->format('Y-m')])->approveBy($outro);

        $this->store($this->editor)->assertSessionHasErrors('plan_override_reason');
    }

    public function test_existing_roteiro_can_still_be_regenerated_by_the_editor(): void
    {
        $this->production->roteiros()->create(['tenant_id' => $this->tenant->id, 'created_by' => $this->admin->id, 'content' => str_repeat('x', 60)]);

        $this->actingAs($this->editor)->postJson(route('roteiros.generate-sofi', $this->production))->assertOk();
    }

    public function test_batch_planner_follows_the_same_gate(): void
    {
        $payload = ['cliente_id' => $this->cliente->id, 'items' => [
            ['title' => 'Vídeo 1', 'script_content' => 'Roteiro 1', 'suggested_date' => now()->addWeek()->toDateString()],
        ]];

        $this->actingAs($this->editor)->post(route('batch_planner.commit'), $payload)->assertSessionHasErrors('plan_override_reason');
        $this->actingAs($this->admin)->post(route('batch_planner.commit'), $payload + ['plan_override_reason' => 'Campanha urgente combinada por telefone'])->assertRedirect();

        $this->assertSame('Campanha urgente combinada por telefone', Roteiro::forTenant($this->tenant->id)->sole()->plan_override_reason);
    }

    public function test_batch_planner_requires_a_team_role(): void
    {
        $viewer = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Visitante', 'email' => 'viewer@ficticio.test', 'password' => 'secret12345', 'role' => 'viewer']);

        $this->actingAs($viewer)->get(route('batch_planner.index'))->assertForbidden();
        $this->actingAs($viewer)->post(route('batch_planner.commit'), ['cliente_id' => $this->cliente->id, 'items' => []])->assertForbidden();
    }
}
