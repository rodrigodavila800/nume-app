<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeConnectTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_stripe_connect(): void
    {
        $tenant = Tenant::create([
            'name' => 'Produtora Teste',
            'slug' => 'produtora-teste',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'editor',
        ]);

        $response = $this->actingAs($user)->get(route('stripe-connect.connect'));
        $response->assertStatus(403);
    }

    public function test_connect_redirects_with_error_when_stripe_secret_is_missing(): void
    {
        config(['services.stripe.secret' => null]);

        $tenant = Tenant::create([
            'name' => 'Produtora Teste',
            'slug' => 'produtora-teste',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);

        $response = $this->actingAs($user)->get(route('stripe-connect.connect'));
        $response->assertRedirect();
        $response->assertSessionHas('error');
    }

    public function test_stripe_v2_recommendation_notice_does_not_throw_error_exception(): void
    {
        // Simula disparo de aviso de recomendação da Stripe SDK
        $executed = false;
        try {
            trigger_error('We recommend building your integration using Accounts v2. See https://docs.stripe.com/api/v2/core/accounts', E_USER_WARNING);
            $executed = true;
        } catch (\ErrorException $e) {
            $this->fail('Stripe notice não deveria lançar ErrorException no Laravel');
        }

        $this->assertTrue($executed);
    }

    public function test_connect_handles_api_exception_gracefully_without_500(): void
    {
        config(['services.stripe.secret' => 'sk_test_invalid_mock_key_for_testing']);

        $tenant = Tenant::create([
            'name' => 'Produtora Teste',
            'slug' => 'produtora-teste',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);

        $response = $this->actingAs($user)->get(route('stripe-connect.connect'));

        // Não deve quebrar com 500, deve redirecionar com mensagem amigável de erro
        $response->assertStatus(302);
        $response->assertRedirect(route('stripe-connect.index'));
        $response->assertSessionHas('error');
    }
}
