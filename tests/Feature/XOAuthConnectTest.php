<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\SocialConnection;
use App\Models\Tenant;
use App\Services\SocialPublishing\OAuth\XOAuthClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Conexão do X pelo app próprio (OAuth 2.0 + PKCE). App, tokens e conta fictícios; API simulada.
 */
class XOAuthConnectTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://api.x.com/2';

    private Cliente $cliente;

    private array $logs = [];

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config([
            'services.x.client_id' => 'cliente-x-ficticio',
            'services.x.client_secret' => 'segredo-x-ficticio',
            'services.x.redirect' => null,
        ]);
        Event::listen(MessageLogged::class, function (MessageLogged $event) {
            $this->logs[] = $event->message.json_encode($event->context);
        });

        $tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active']);
        $this->cliente = Cliente::create(['tenant_id' => $tenant->id, 'name' => 'Cliente A', 'email' => 'a@ficticio.test', 'password' => bcrypt('secret123')]);
    }

    public function test_redirect_uses_pkce_and_asks_for_posting_media_and_offline_access(): void
    {
        $location = $this->actingAs($this->cliente, 'cliente')->get(route('portal.social.oauth.redirect', 'x'))->headers->get('Location');

        $this->assertStringStartsWith(XOAuthClient::AUTHORIZE_URL.'?', $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        $pending = session('social_oauth.x');
        $this->assertSame($pending['state'], $query['state']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertSame(rtrim(strtr(base64_encode(hash('sha256', $pending['verifier'], true)), '+/', '-_'), '='), $query['code_challenge']);
        $this->assertSame('tweet.read tweet.write users.read media.write offline.access', $query['scope']);
        $this->assertSame(route('portal.social.oauth.callback', 'x'), $query['redirect_uri']);
    }

    public function test_successful_connection_stores_the_account_with_rotating_refresh_token(): void
    {
        $this->fakeX();
        $state = $this->start();
        $verifier = session('social_oauth.x.verifier');

        $this->get($this->callbackUrl($state))
            ->assertRedirect(route('portal.social.index'))
            ->assertSessionHas('success', 'X @clinicaficticia conectado com sucesso!');

        $connection = SocialConnection::forTenant($this->cliente->tenant_id)->sole();
        $this->assertSame('x', $connection->platform);
        $this->assertSame('1234567890', $connection->external_account_id);
        $this->assertSame('@clinicaficticia', $connection->handle);
        $this->assertSame('acesso-x-ficticio', $connection->access_token);
        $this->assertSame('atualizacao-x-ficticia', $connection->refresh_token);
        $this->assertTrue($connection->expires_at->between(now()->addMinutes(119), now()->addMinutes(121)));
        $this->assertStringNotContainsString('acesso-x-ficticio', DB::table('social_connections')->value('access_token'));

        Http::assertSent(fn (HttpRequest $request) => $request->url() === self::API.'/oauth2/token'
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('cliente-x-ficticio:segredo-x-ficticio'))
            && $request['code_verifier'] === $verifier
            && $request['grant_type'] === 'authorization_code');

        foreach ($this->logs as $line) {
            $this->assertStringNotContainsString('acesso-x', $line);
            $this->assertStringNotContainsString('segredo-x', $line);
        }
    }

    public function test_missing_media_permission_revokes_and_saves_nothing(): void
    {
        $this->fakeX(['scope' => 'tweet.read tweet.write users.read offline.access']);

        $this->get($this->callbackUrl($this->start()))
            ->assertSessionHas('error', 'Para publicar, autorize a criação de posts e o envio de mídia na tela do X.');

        Http::assertSent(fn (HttpRequest $request) => $request->url() === self::API.'/oauth2/revoke');
        $this->assertSame(0, DB::table('social_connections')->count());
    }

    public function test_cancellation_saves_nothing(): void
    {
        $state = $this->start();

        $this->get(route('portal.social.oauth.callback', ['platform' => 'x', 'state' => $state, 'error' => 'access_denied']))
            ->assertSessionHas('error', 'Você cancelou a autorização no X. Nada foi conectado.');

        Http::assertNothingSent();
    }

    public function test_disconnect_revokes_at_x_and_deletes_tokens(): void
    {
        $this->fakeX();
        $this->get($this->callbackUrl($this->start()));
        $connection = SocialConnection::forTenant($this->cliente->tenant_id)->sole();

        $this->delete(route('portal.social.connections.destroy', $connection))->assertSessionHas('success');

        Http::assertSent(fn (HttpRequest $request) => $request->url() === self::API.'/oauth2/revoke' && $request['token'] === 'atualizacao-x-ficticia');
        $this->assertSame(0, DB::table('social_connections')->count());
    }

    public function test_daily_refresh_rotates_the_x_refresh_token(): void
    {
        $connection = SocialConnection::create([
            'tenant_id' => $this->cliente->tenant_id, 'cliente_id' => $this->cliente->id, 'platform' => 'x', 'provider' => 'x',
            'external_account_id' => '1234567890', 'access_token' => 'acesso-velho', 'refresh_token' => 'atualizacao-velha',
            'expires_at' => now()->addHour(), 'last_refreshed_at' => now()->subDay(),
        ]);
        Http::fake([self::API.'/oauth2/token' => Http::response(['access_token' => 'acesso-novo', 'refresh_token' => 'atualizacao-nova', 'expires_in' => 7200, 'scope' => 'tweet.write media.write offline.access', 'token_type' => 'bearer'])]);

        $this->artisan('social:refresh-tokens')->expectsOutputToContain('Renovadas: 1.')->assertSuccessful();

        $this->assertSame('acesso-novo', $connection->fresh()->access_token);
        $this->assertSame('atualizacao-nova', $connection->fresh()->refresh_token);
    }

    private function start(): string
    {
        $this->actingAs($this->cliente, 'cliente')->get(route('portal.social.oauth.redirect', 'x'));

        return session('social_oauth.x.state');
    }

    private function callbackUrl(string $state): string
    {
        return route('portal.social.oauth.callback', ['platform' => 'x', 'state' => $state, 'code' => 'codigo-ficticio']);
    }

    private function fakeX(array $token = []): void
    {
        Http::fake([
            self::API.'/oauth2/token' => Http::response(array_merge([
                'token_type' => 'bearer', 'expires_in' => 7200, 'access_token' => 'acesso-x-ficticio',
                'refresh_token' => 'atualizacao-x-ficticia', 'scope' => 'tweet.read tweet.write users.read media.write offline.access',
            ], $token)),
            self::API.'/users/me' => Http::response(['data' => ['id' => '1234567890', 'name' => 'Clinica Ficticia', 'username' => 'clinicaficticia']]),
            self::API.'/oauth2/revoke' => Http::response(['revoked' => true]),
        ]);
    }
}
