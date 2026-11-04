<?php

namespace Tests\Feature;

use App\Models\Pack;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Catálogo de pacotes (avulsos) e Planos (assinatura) são independentes. Dados fictícios.
 */
class PackCatalogSeparationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Pack $pack;

    private Pack $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::create(['name' => 'Produtora Alpha', 'slug' => 'produtora-alpha', 'status' => 'active', 'subscription_expires_at' => now()->addMonth()]);
        $this->admin = User::create(['tenant_id' => $tenant->id, 'name' => 'Admin', 'email' => 'admin@ficticio.test', 'password' => 'secret12345', 'role' => 'admin']);
        $this->pack = Pack::create(['tenant_id' => $tenant->id, 'name' => 'Pacote Lançamento', 'slug' => 'pacote-lancamento', 'price' => 500, 'video_quantity' => 4, 'status' => 'active', 'billing_type' => 'one_time']);
        $this->plan = Pack::create(['tenant_id' => $tenant->id, 'name' => 'Plano Mensal Criador', 'slug' => 'plano-mensal', 'price' => 300, 'video_quantity' => 4, 'status' => 'active', 'billing_type' => 'subscription', 'billing_interval' => 'month', 'commitment_months' => 3]);
    }

    private function packPayload(array $overrides = []): array
    {
        return $overrides + ['name' => 'Pacote Novo', 'price' => 400, 'video_quantity' => 3, 'status' => 'active', 'currency' => 'eur'];
    }

    public function test_the_catalog_lists_only_one_off_packs_and_plans_list_only_plans(): void
    {
        $this->actingAs($this->admin)->get(route('packs.index'))
            ->assertOk()->assertSee('Pacote Lançamento')->assertDontSee('Plano Mensal Criador');

        $this->actingAs($this->admin)->get(route('admin.plans.index'))
            ->assertOk()->assertSee('Plano Mensal Criador')->assertDontSee('Pacote Lançamento');
    }

    public function test_a_pack_saved_in_the_catalog_never_becomes_a_plan(): void
    {
        $this->actingAs($this->admin)->post(route('packs.store'), $this->packPayload(['billing_type' => 'subscription']))->assertRedirect();
        $this->assertSame('one_time', Pack::where('name', 'Pacote Novo')->sole()->billing_type);

        $this->actingAs($this->admin)->put(route('packs.update', $this->pack), $this->packPayload(['name' => 'Pacote Lançamento', 'billing_type' => 'subscription']))->assertRedirect();
        $this->assertSame('one_time', $this->pack->fresh()->billing_type);
    }

    public function test_opening_a_plan_through_the_catalog_goes_to_the_plans_screen_and_changes_nothing(): void
    {
        $this->actingAs($this->admin)->get(route('packs.edit', $this->plan))
            ->assertRedirect(route('admin.plans.edit', $this->plan));

        $this->actingAs($this->admin)->put(route('packs.update', $this->plan), $this->packPayload(['name' => 'Plano Mensal Criador']))
            ->assertRedirect(route('admin.plans.edit', $this->plan));

        $this->assertSame('subscription', $this->plan->fresh()->billing_type);
        $this->assertEquals(300.00, $this->plan->fresh()->price);
    }

    public function test_the_pack_form_has_no_subscription_option(): void
    {
        $this->actingAs($this->admin)->get(route('packs.create'))
            ->assertOk()
            ->assertDontSee('name="billing_type"', false)
            ->assertSee(route('admin.plans.index'), false);
    }
}
