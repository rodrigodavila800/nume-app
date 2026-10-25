<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\SocialConnection;
use App\Models\Tenant;
use App\Services\SocialPublishing\OAuth\PinterestOAuthClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Conexão do Pinterest pelo app próprio. App, tokens e conta fictícios; API simulada.
 */
class PinterestOAuthConnectTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://api.pinterest.com/v5';

    private Cliente $cliente;

    private array $logs = [];

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config([
            'services.pinterest.client_id' => 'app-pinterest-ficticio',
            'services.pinterest.client_secret' => 'segredo-pinterest-ficticio',
            'services.pinterest.redirect' => null,
            'social.pinterest.api_base' => self::API,
        ]);
        Event::listen(MessageLogged::class, function (MessageLogged $event) {
            $this->logs[] = $event->message.json_encode($event->context);
        });

        $tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active']);
        $this->cliente = Cliente::create(['tenant_id' => $tenant->id, 'name' => 'Cliente A', 'email' => 'a@ficticio.test', 'password' => bcrypt('secret123')]);
    }

    public function test_redirect_asks_for_boards_and_pin_creation(): void
    {
        $location = $this->actingAs($this->cliente, 'cliente')->get(route('portal.social.oauth.redirect', 'pinterest'))->headers->get('Location');

        $this->assertStringStartsWith(PinterestOAuthClient::AUTHORIZE_URL.'?', $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('user_accounts:read,boards:read,pins:read,pins:write', $query['scope']);
        $this->assertSame(route('portal.social.oauth.callback', 'pinterest'), $query['redirect_uri']);
        $this->assertSame(session('social_oauth.pinterest.state'), $query['state']);
    }

    public function test_successful_connection_uses_basic_auth_and_stores_encrypted_tokens(): void
    {
        $this->fakePinterest();

        $this->get($this->callbackUrl($this->start()))
            ->assertRedirect(route('portal.social.index'))
            ->assertSessionHas('success', 'Pinterest @clinicaficticia conectado com sucesso!');

        $connection = SocialConnection::forTenant($this->cliente->tenant_id)->sole();
        $this->assertSame('pinterest', $connection->platform);
        $this->assertSame('conta-ficticia', $connection->external_account_id);
        $this->assertSame('@clinicaficticia', $connection->handle);
        $this->assertSame('pina.acesso-ficticio', $connection->access_token);
        $this->assertSame('pinr.atualizacao-ficticia', $connection->refresh_token);
        $this->assertTrue($connection->expires_at->between(now()->addDays(29), now()->addDays(31)));
        $this->assertStringNotContainsString('acesso-ficticio', DB::table('social_connections')->value('access_token'));

        Http::assertSent(fn (HttpRequest $request) => $request->url() === self::API.'/oauth/token'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('app-pinterest-ficticio:segredo-pinterest-ficticio'))
            && $request['grant_type'] === 'authorization_code'
            && $request['continuous_refresh'] === 'true'
            && ! isset($request['client_secret']));

        foreach ($this->logs as $line) {
            $this->assertStringNotContainsString('acesso-ficticio', $line);
            $this->assertStringNotContainsString('segredo-pinterest', $line);
        }
    }

    public function test_missing_pin_permission_saves_nothing(): void
    {
        $this->fakePinterest(['scope' => 'user_accounts:read,boards:read']);

        $this->get($this->callbackUrl($this->start()))
            ->assertSessionHas('error', 'Para publicar, autorize a leitura das pastas e a criação de pins na tela do Pinterest.');

        $this->assertSame(0, DB::table('social_connections')->count());
    }

    public function test_forged_state_and_cancellation_save_nothing(): void
    {
        $this->start();
        $this->get(route('portal.social.oauth.callback', ['platform' => 'pinterest', 'state' => 'forjado', 'code' => 'c']))
            ->assertSessionHas('error', 'A conexão expirou ou não foi iniciada por aqui. Tente conectar novamente.');

        $state = $this->start();
        $this->get(route('portal.social.oauth.callback', ['platform' => 'pinterest', 'state' => $state, 'error' => 'access_denied']))
            ->assertSessionHas('error', 'Você cancelou a autorização no Pinterest. Nada foi conectado.');

        Http::assertNothingSent();
    }

    public function test_daily_refresh_renews_pinterest_and_flags_revoked_ones(): void
    {
        $ok = $this->connection('conta-a', 'pinr.valido');
        $revogada = $this->connection('conta-b', 'pinr.revogado');

        Http::fake([self::API.'/oauth/token' => function (HttpRequest $request) {
            return $request['refresh_token'] === 'pinr.revogado'
                ? Http::response(['code' => 1, 'message' => 'Invalid refresh token'], 400)
                : Http::response(['access_token' => 'pina.novo', 'refresh_token' => 'pinr.novo', 'expires_in' => 2592000, 'refresh_token_expires_in' => 5184000, 'scope' => 'pins:write,boards:read', 'token_type' => 'bearer']);
        }]);

        $this->artisan('social:refresh-tokens')->expectsOutputToContain('Renovadas: 1. Precisam reconectar: 1.')->assertSuccessful();

        $this->assertSame('pina.novo', $ok->fresh()->access_token);
        $this->assertSame('pinr.novo', $ok->fresh()->refresh_token);
        $this->assertSame(SocialConnection::STATUS_EXPIRED, $revogada->fresh()->status);
    }

    private function connection(string $account, string $refresh): SocialConnection
    {
        return SocialConnection::create([
            'tenant_id' => $this->cliente->tenant_id, 'cliente_id' => $this->cliente->id, 'platform' => 'pinterest', 'provider' => 'pinterest',
            'external_account_id' => $account, 'access_token' => 'pina.velho', 'refresh_token' => $refresh,
            'expires_at' => now()->addDays(10), 'last_refreshed_at' => now()->subDays(2),
        ]);
    }

    private function start(): string
    {
        $this->actingAs($this->cliente, 'cliente')->get(route('portal.social.oauth.redirect', 'pinterest'));

        return session('social_oauth.pinterest.state');
    }

    private function callbackUrl(string $state): string
    {
        return route('portal.social.oauth.callback', ['platform' => 'pinterest', 'state' => $state, 'code' => 'codigo-ficticio']);
    }

    private function fakePinterest(array $token = []): void
    {
        Http::fake([
            self::API.'/oauth/token' => Http::response(array_merge([
                'access_token' => 'pina.acesso-ficticio', 'refresh_token' => 'pinr.atualizacao-ficticia',
                'expires_in' => 2592000, 'refresh_token_expires_in' => 5184000,
                'scope' => 'user_accounts:read,boards:read,pins:read,pins:write', 'token_type' => 'bearer',
            ], $token)),
            self::API.'/user_account' => Http::response(['id' => 'conta-ficticia', 'username' => 'clinicaficticia', 'account_type' => 'BUSINESS']),
        ]);
    }
}
