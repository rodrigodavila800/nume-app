<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\SocialConnection;
use App\Models\Tenant;
use App\Services\SocialPublishing\Connectors\ConnectorRegistry;
use App\Services\SocialPublishing\SocialPublisherRouter;
use App\Services\SocialPublishing\YouTubeConnector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Base comum dos conectores (uma rede ou família de redes por conector). Dados fictícios, sem rede.
 */
class SocialConnectorRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_resolves_connectors_by_key_and_by_provider(): void
    {
        $registry = app(ConnectorRegistry::class);

        $this->assertInstanceOf(YouTubeConnector::class, $registry->for('youtube'));
        $this->assertInstanceOf(YouTubeConnector::class, $registry->forProvider('google'));
        $this->assertNull($registry->for('rede-inexistente'));
        $this->assertNull($registry->forProvider('provedor-inexistente'));
    }

    public function test_route_of_a_connector_that_is_not_installed_is_not_found(): void
    {
        $cliente = $this->cliente();
        config(['social.connectors.meta' => null]);

        $this->actingAs($cliente, 'cliente')->get(route('portal.social.oauth.redirect', 'meta'))->assertNotFound();
    }

    public function test_network_goes_native_only_with_a_ready_publisher(): void
    {
        config(['social.routing.tiktok' => 'native']);
        $router = app(SocialPublisherRouter::class);

        $this->assertSame(SocialPublisherRouter::UPLOAD_POST, $router->routeFor('tiktok'), 'liberada, mas sem publicador');
        $this->assertSame(SocialPublisherRouter::NATIVE, $router->routeFor('youtube'));
    }

    public function test_token_without_expiry_is_not_treated_as_expiring(): void
    {
        $connection = $this->connection($this->cliente(), ['expires_at' => null]);

        $this->assertFalse($connection->expiresWithin(5));
        $connection->expires_at = now()->subMinute();
        $this->assertTrue($connection->expiresWithin(5));
    }

    public function test_connection_of_an_unknown_provider_is_still_disconnected(): void
    {
        Http::preventStrayRequests();
        $cliente = $this->cliente();
        $connection = $this->connection($cliente, ['provider' => 'provedor-removido']);

        $this->actingAs($cliente, 'cliente')->delete(route('portal.social.connections.destroy', $connection))
            ->assertRedirect(route('portal.social.index'))
            ->assertSessionHas('success');

        $this->assertSame(0, DB::table('social_connections')->count());
    }

    private function cliente(): Cliente
    {
        $tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active']);

        return Cliente::create(['tenant_id' => $tenant->id, 'name' => 'Cliente A', 'email' => 'a@ficticio.test', 'password' => bcrypt('secret123')]);
    }

    private function connection(Cliente $cliente, array $attributes = []): SocialConnection
    {
        return SocialConnection::create(array_merge([
            'tenant_id' => $cliente->tenant_id, 'cliente_id' => $cliente->id, 'platform' => 'facebook', 'provider' => 'meta',
            'external_account_id' => 'pagina-ficticia', 'access_token' => 'token-ficticio',
        ], $attributes));
    }
}
