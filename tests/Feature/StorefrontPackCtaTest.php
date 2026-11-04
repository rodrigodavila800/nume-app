<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Pack;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Contratar" de pacote na vitrine leva às telas oficiais de cadastro ou login (sem modal de senha). Dados fictícios.
 */
class StorefrontPackCtaTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'NUME', 'slug' => 'nume', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        Pack::create(['tenant_id' => $this->tenant->id, 'name' => 'Nume Ads Starter', 'slug' => 'nume-ads-starter', 'price' => 450, 'video_quantity' => 3, 'status' => 'active', 'billing_type' => 'one_time']);
    }

    public function test_a_visitor_is_sent_to_the_registration_page_with_the_pack(): void
    {
        $this->get(route('marketplace.index'))
            ->assertOk()
            ->assertSee('href="'.e(route('portal.register', ['pack' => 'nume-ads-starter'])).'"', false)
            ->assertDontSee('onclick="openStorefrontCheckout(', false);

        $this->get(route('portal.register', ['pack' => 'nume-ads-starter']))
            ->assertOk()
            ->assertSee('Nume Ads Starter')
            ->assertSee(e(route('portal.login', ['pack' => 'nume-ads-starter'])), false);
    }

    public function test_a_logged_in_client_goes_straight_to_the_portal_packs(): void
    {
        $cliente = Cliente::create(['tenant_id' => $this->tenant->id, 'name' => 'Ana', 'email' => 'ana@ficticio.test', 'password' => 'secret12345']);

        $this->actingAs($cliente, 'cliente')->get(route('marketplace.index'))
            ->assertOk()
            ->assertSee('href="'.e(route('portal.packs.index', ['pack' => 'nume-ads-starter'])).'"', false);
    }
}
