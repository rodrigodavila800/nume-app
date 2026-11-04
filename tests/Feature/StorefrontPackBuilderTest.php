<?php

namespace Tests\Feature;

use App\Http\Controllers\Portal\PackBuilderController;
use App\Models\Addon;
use App\Models\Cliente;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\VideoType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Monte seu pacote" na vitrine: só simula; contratar leva a cadastro ou login. Dados fictícios.
 */
class StorefrontPackBuilderTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private VideoType $reels;

    private Addon $drone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'NUME', 'slug' => 'nume', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        $this->reels = VideoType::create(['tenant_id' => $this->tenant->id, 'name' => 'Reels 30 s', 'slug' => 'reels', 'duration_seconds' => 30, 'blocks' => [], 'builder_price' => 100, 'is_active' => true]);
        $this->drone = Addon::create(['tenant_id' => $this->tenant->id, 'name' => 'Drone', 'slug' => 'drone', 'default_price' => 150, 'currency' => 'eur', 'is_active' => true, 'in_builder' => true]);
    }

    public function test_the_storefront_shows_the_builder_simulation(): void
    {
        $this->get(route('marketplace.index'))
            ->assertOk()
            ->assertSee('Monte seu pacote')
            ->assertSee('Reels 30 s')
            ->assertSee('data-builder-qty', false)
            ->assertSee(route('storefront.builder', 'nume'), false);
    }

    public function test_the_builder_section_is_hidden_without_priced_video_types(): void
    {
        $this->reels->forceFill(['builder_price' => null])->save();

        $this->get(route('marketplace.index'))->assertOk()->assertDontSee('id="monte-seu-pacote"', false);
    }

    public function test_hiring_saves_the_package_and_sends_the_visitor_to_register(): void
    {
        $this->post(route('storefront.builder', 'nume'), ['items' => [$this->reels->id => 3], 'addons' => [$this->drone->id]])
            ->assertRedirect(route('portal.register'))
            ->assertSessionHas(PackBuilderController::SESSION_KEY, ['tenant_id' => $this->tenant->id, 'items' => [$this->reels->id => 3], 'addons' => [$this->drone->id]])
            ->assertSessionHas('url.intended', route('portal.builder'));

        $this->assertSame(0, Quote::count(), 'na vitrine nada é contratado');
    }

    public function test_an_empty_simulation_returns_to_the_builder_with_an_error(): void
    {
        $this->post(route('storefront.builder', 'nume'), ['items' => [$this->reels->id => 0]])
            ->assertRedirect(route('marketplace.index').'#monte-seu-pacote')
            ->assertSessionHas('builder_error')
            ->assertSessionMissing(PackBuilderController::SESSION_KEY);
    }

    public function test_after_registering_the_client_confirms_the_saved_package_in_the_portal(): void
    {
        $this->post(route('storefront.builder', 'nume'), ['items' => [$this->reels->id => 3]]);

        $this->post(route('portal.register'), [
            'name' => 'Ana', 'email' => 'ana@ficticio.test', 'password' => 'Senha-Forte-2026!', 'password_confirmation' => 'Senha-Forte-2026!', 'terms' => '1',
        ])->assertRedirect(route('portal.builder'));

        $this->get(route('portal.builder'))->assertOk()->assertSee('value="3"', false);
    }

    public function test_an_existing_client_logs_in_and_lands_on_the_builder(): void
    {
        Cliente::create(['tenant_id' => $this->tenant->id, 'name' => 'Ana', 'email' => 'ana@ficticio.test', 'password' => 'Senha-Forte-2026!']);
        $this->post(route('storefront.builder', 'nume'), ['items' => [$this->reels->id => 2]]);

        $this->post(route('portal.login'), ['email' => 'ana@ficticio.test', 'password' => 'Senha-Forte-2026!'])
            ->assertRedirect(route('portal.builder'));
    }

    public function test_a_logged_in_client_goes_straight_to_the_builder(): void
    {
        $cliente = Cliente::create(['tenant_id' => $this->tenant->id, 'name' => 'Ana', 'email' => 'ana@ficticio.test', 'password' => 'secret12345']);

        $this->actingAs($cliente, 'cliente')
            ->post(route('storefront.builder', 'nume'), ['items' => [$this->reels->id => 2]])
            ->assertRedirect(route('portal.builder'));
    }
}
