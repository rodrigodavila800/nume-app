<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\SocialConnection;
use App\Models\Tenant;
use App\Services\SocialPublishing\SocialPublisherRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Roteamento rede a rede entre o app próprio e o Upload-Post. Dados fictícios, sem rede.
 */
class SocialPublisherRouterTest extends TestCase
{
    use RefreshDatabase;

    private Cliente $cliente;

    private SocialPublisherRouter $router;

    protected function setUp(): void
    {
        parent::setUp();

        config(['social.routing.youtube' => 'native']);
        $tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active']);
        $this->cliente = Cliente::create(['tenant_id' => $tenant->id, 'name' => 'Cliente A', 'email' => 'a@ficticio.test', 'password' => bcrypt('secret123')]);
        $this->router = app(SocialPublisherRouter::class);
    }

    public function test_without_a_connection_everything_goes_to_upload_post(): void
    {
        $this->assertSame(
            ['native' => [], 'upload_post' => ['youtube', 'instagram']],
            $this->router->route($this->cliente, ['youtube', 'instagram'])
        );
    }

    public function test_connected_youtube_goes_native_and_the_rest_to_upload_post(): void
    {
        $connection = $this->connect($this->cliente);

        $plan = $this->router->route($this->cliente, ['instagram', 'youtube', 'tiktok', 'youtube']);

        $this->assertSame(['youtube'], array_keys($plan['native']));
        $this->assertTrue($plan['native']['youtube']->is($connection));
        $this->assertSame(['instagram', 'tiktok'], $plan['upload_post']);
    }

    public function test_inactive_connection_falls_back_to_upload_post(): void
    {
        $this->connect($this->cliente, ['status' => SocialConnection::STATUS_EXPIRED]);

        $this->assertSame(['youtube'], $this->router->route($this->cliente, ['youtube'])['upload_post']);
    }

    public function test_network_not_released_in_config_ignores_the_connection(): void
    {
        config(['social.routing.youtube' => 'upload_post']);
        $this->connect($this->cliente);

        $this->assertSame(['native' => [], 'upload_post' => ['youtube']], $this->router->route($this->cliente, ['youtube']));
    }

    public function test_networks_without_an_approved_app_never_go_native(): void
    {
        $this->connect($this->cliente, ['platform' => 'pinterest', 'provider' => 'pinterest']);

        $this->assertSame(['pinterest'], $this->router->route($this->cliente, ['pinterest'])['upload_post']);
        $this->assertSame(SocialPublisherRouter::UPLOAD_POST, $this->router->routeFor('x'));
        $this->assertSame(SocialPublisherRouter::UPLOAD_POST, $this->router->routeFor('rede-inexistente'));
    }

    public function test_another_clientes_connection_is_never_used(): void
    {
        $tenantB = Tenant::create(['name' => 'Produtora B', 'slug' => 'produtora-b', 'status' => 'active']);
        $outroMesmoTenant = Cliente::create(['tenant_id' => $this->cliente->tenant_id, 'name' => 'Cliente C', 'email' => 'c@ficticio.test', 'password' => bcrypt('secret123')]);
        $outroTenant = Cliente::create(['tenant_id' => $tenantB->id, 'name' => 'Cliente B', 'email' => 'b@ficticio.test', 'password' => bcrypt('secret123')]);
        $this->connect($outroMesmoTenant, ['external_account_id' => 'UC-c']);
        $this->connect($outroTenant, ['external_account_id' => 'UC-b']);

        $this->assertSame([], $this->router->route($this->cliente, ['youtube'])['native']);
    }

    private function connect(Cliente $cliente, array $attributes = []): SocialConnection
    {
        return SocialConnection::create(array_merge([
            'tenant_id' => $cliente->tenant_id,
            'cliente_id' => $cliente->id,
            'platform' => 'youtube',
            'provider' => 'google',
            'external_account_id' => 'UC-canal-ficticio',
            'access_token' => 'ya29.ficticio',
            'refresh_token' => '1//ficticio',
            'expires_at' => now()->addHour(),
        ], $attributes));
    }
}
