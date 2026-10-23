<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class GoogleAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $staffUser;
    protected Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'NUME Zaragoza Studio',
            'slug' => 'nume',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $this->staffUser = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Produtor Staff',
            'email' => 'staff@numecreative.es',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $this->cliente = Cliente::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente Existente',
            'email' => 'cliente.existente@empresa.es',
            'password' => bcrypt('password123'),
            'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_google_oauth_redirect_for_staff(): void
    {
        $response = $this->get(route('auth.google'));

        $response->assertStatus(302);
        $this->assertStringContainsString('accounts.google.com', $response->headers->get('Location'));
    }

    public function test_google_oauth_redirect_for_client_portal(): void
    {
        $response = $this->get(route('portal.auth.google', ['tenant_slug' => 'nume']));

        $response->assertStatus(302);
        $this->assertStringContainsString('accounts.google.com', $response->headers->get('Location'));
    }

    public function test_google_callback_logs_in_existing_staff(): void
    {
        $abstractUser = Mockery::mock(SocialiteUser::class);
        $abstractUser->shouldReceive('getEmail')->andReturn('staff@numecreative.es');
        $abstractUser->shouldReceive('getName')->andReturn('Produtor Staff');

        $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
        $provider->shouldReceive('redirectUrl')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('productions.index'));
        $this->assertAuthenticatedAs($this->staffUser, 'web');
    }

    public function test_google_callback_rejects_unregistered_staff(): void
    {
        $abstractUser = Mockery::mock(SocialiteUser::class);
        $abstractUser->shouldReceive('getEmail')->andReturn('desconhecido@gmail.com');

        $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
        $provider->shouldReceive('redirectUrl')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get(route('auth.google.callback'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['email']);
        $this->assertGuest('web');
    }

    public function test_google_callback_logs_in_existing_client(): void
    {
        $abstractUser = Mockery::mock(SocialiteUser::class);
        $abstractUser->shouldReceive('getEmail')->andReturn('cliente.existente@empresa.es');
        $abstractUser->shouldReceive('getName')->andReturn('Cliente Existente');

        $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
        $provider->shouldReceive('redirectUrl')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get(route('portal.auth.google.callback'));

        $response->assertRedirect(route('portal.dashboard'));
        $this->assertAuthenticatedAs($this->cliente, 'cliente');
    }

    public function test_google_callback_auto_registers_new_client(): void
    {
        $newEmail = 'novo.cliente@gmail.com';

        $abstractUser = Mockery::mock(SocialiteUser::class);
        $abstractUser->shouldReceive('getEmail')->andReturn($newEmail);
        $abstractUser->shouldReceive('getName')->andReturn('Novo Cliente Google');

        $provider = Mockery::mock('Laravel\Socialite\Two\GoogleProvider');
        $provider->shouldReceive('redirectUrl')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get(route('portal.auth.google.callback', ['state' => 'nume']));

        $response->assertRedirect(route('portal.dashboard'));

        $createdClient = Cliente::withoutGlobalScope('tenant')->where('email', $newEmail)->first();
        $this->assertNotNull($createdClient);
        $this->assertEquals('Novo Cliente Google', $createdClient->name);
        $this->assertEquals($this->tenant->id, $createdClient->tenant_id);
        $this->assertAuthenticatedAs($createdClient, 'cliente');
    }

    public function test_admin_can_save_google_oauth_settings(): void
    {
        $response = $this->actingAs($this->staffUser, 'web')
            ->post(route('admin.settings.gateways.update'), [
                'stripe_mode' => 'test',
                'stripe_platform_fee_percent' => 10,
                'redsys_environment' => 'test',
                'tax_default_iva_rate' => 21,
                'google_client_id' => 'test-client-id-1234.apps.googleusercontent.com',
                'google_client_secret' => 'test-secret-abcd-5678',
            ]);

        $response->assertRedirect(route('admin.settings.gateways'));

        $this->assertEquals('test-client-id-1234.apps.googleusercontent.com', \App\Models\Setting::get('google_client_id'));
        $this->assertEquals('test-secret-abcd-5678', \App\Models\Setting::get('google_client_secret'));
    }
}
