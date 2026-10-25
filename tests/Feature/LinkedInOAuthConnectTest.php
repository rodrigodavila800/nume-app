<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\SocialConnection;
use App\Models\Tenant;
use App\Services\SocialPublishing\OAuth\LinkedInOAuthClient;
use App\Services\SocialPublishing\PublishException;
use App\Services\SocialPublishing\SocialTokenManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Conexão do perfil do LinkedIn pelo app próprio. App, tokens e perfil fictícios; API simulada.
 */
class LinkedInOAuthConnectTest extends TestCase
{
    use RefreshDatabase;

    private Cliente $cliente;

    private array $logs = [];

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config([
            'services.linkedin.client_id' => 'cliente-linkedin-ficticio',
            'services.linkedin.client_secret' => 'segredo-linkedin-ficticio',
            'services.linkedin.redirect' => null,
        ]);
        Event::listen(MessageLogged::class, function (MessageLogged $event) {
            $this->logs[] = $event->message.json_encode($event->context);
        });

        $tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active']);
        $this->cliente = Cliente::create(['tenant_id' => $tenant->id, 'name' => 'Cliente A', 'email' => 'a@ficticio.test', 'password' => bcrypt('secret123')]);
    }

    public function test_redirect_asks_for_profile_and_member_posting_only(): void
    {
        $location = $this->actingAs($this->cliente, 'cliente')->get(route('portal.social.oauth.redirect', 'linkedin'))->headers->get('Location');

        $this->assertStringStartsWith(LinkedInOAuthClient::AUTHORIZE_URL.'?', $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('openid profile w_member_social', $query['scope']);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame(route('portal.social.oauth.callback', 'linkedin'), $query['redirect_uri']);
        $this->assertSame(session('social_oauth.linkedin.state'), $query['state']);
    }

    public function test_successful_connection_stores_the_member_with_a_60_day_token(): void
    {
        $this->fakeLinkedIn();

        $this->get($this->callbackUrl($this->start()))
            ->assertRedirect(route('portal.social.index'))
            ->assertSessionHas('success', 'LinkedIn de Ana Ficticia conectado com sucesso!');

        $connection = SocialConnection::forTenant($this->cliente->tenant_id)->sole();
        $this->assertSame('linkedin', $connection->platform);
        $this->assertSame('linkedin', $connection->provider);
        $this->assertSame('membro-ficticio', $connection->external_account_id);
        $this->assertSame('Ana Ficticia', $connection->display_name);
        $this->assertNull($connection->handle);
        $this->assertNull($connection->avatar_url);
        $this->assertNull($connection->refresh_token);
        $this->assertTrue($connection->expires_at->between(now()->addDays(59), now()->addDays(61)));
        $this->assertStringNotContainsString('acesso-ficticio', DB::table('social_connections')->value('access_token'));

        Http::assertSent(fn (HttpRequest $request) => $request->url() === LinkedInOAuthClient::TOKEN_URL
            && $request['grant_type'] === 'authorization_code'
            && $request['client_secret'] === 'segredo-linkedin-ficticio');
        Http::assertSent(fn (HttpRequest $request) => $request->url() === LinkedInOAuthClient::USERINFO_URL
            && $request->hasHeader('Authorization', 'Bearer AQV.acesso-ficticio'));

        foreach ($this->logs as $line) {
            $this->assertStringNotContainsString('acesso-ficticio', $line);
            $this->assertStringNotContainsString('segredo-linkedin', $line);
        }
    }

    public function test_missing_posting_permission_saves_nothing(): void
    {
        $this->fakeLinkedIn(['scope' => 'openid,profile']);

        $this->get($this->callbackUrl($this->start()))
            ->assertSessionHas('error', 'Para publicar, autorize o compartilhamento de posts na tela do LinkedIn.');

        $this->assertSame(0, DB::table('social_connections')->count());
    }

    public function test_cancelling_on_linkedin_saves_nothing(): void
    {
        $state = $this->start();

        $this->get(route('portal.social.oauth.callback', ['platform' => 'linkedin', 'state' => $state, 'error' => 'user_cancelled_authorize']))
            ->assertSessionHas('error', 'Você cancelou a autorização no LinkedIn. Nada foi conectado.');

        Http::assertNothingSent();
    }

    public function test_expired_token_without_refresh_asks_to_reconnect(): void
    {
        $connection = SocialConnection::create([
            'tenant_id' => $this->cliente->tenant_id, 'cliente_id' => $this->cliente->id, 'platform' => 'linkedin', 'provider' => 'linkedin',
            'external_account_id' => 'membro-ficticio', 'access_token' => 'AQV.vencido', 'expires_at' => now()->subMinute(),
        ]);

        try {
            app(SocialTokenManager::class)->accessToken($connection);
            $this->fail('deveria pedir reconexão');
        } catch (PublishException $e) {
            $this->assertSame('reconnect_required', $e->reason);
        }

        $this->assertSame(SocialConnection::STATUS_EXPIRED, $connection->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_daily_refresh_never_touches_linkedin_tokens(): void
    {
        SocialConnection::create([
            'tenant_id' => $this->cliente->tenant_id, 'cliente_id' => $this->cliente->id, 'platform' => 'linkedin', 'provider' => 'linkedin',
            'external_account_id' => 'membro-ficticio', 'access_token' => 'AQV.valido', 'expires_at' => now()->addDays(30),
            'last_refreshed_at' => now()->subDays(30),
        ]);

        $this->artisan('social:refresh-tokens')->expectsOutputToContain('Renovadas: 0. Precisam reconectar: 0.')->assertSuccessful();

        $this->assertSame(SocialConnection::STATUS_ACTIVE, SocialConnection::forTenant($this->cliente->tenant_id)->sole()->status);
    }

    private function start(): string
    {
        $this->actingAs($this->cliente, 'cliente')->get(route('portal.social.oauth.redirect', 'linkedin'));

        return session('social_oauth.linkedin.state');
    }

    private function callbackUrl(string $state): string
    {
        return route('portal.social.oauth.callback', ['platform' => 'linkedin', 'state' => $state, 'code' => 'codigo-ficticio']);
    }

    private function fakeLinkedIn(array $token = []): void
    {
        Http::fake([
            LinkedInOAuthClient::TOKEN_URL => Http::response(array_merge([
                'access_token' => 'AQV.acesso-ficticio', 'expires_in' => 5184000, 'scope' => 'openid,profile,w_member_social', 'token_type' => 'Bearer',
            ], $token)),
            LinkedInOAuthClient::USERINFO_URL => Http::response([
                'sub' => 'membro-ficticio', 'name' => 'Ana Ficticia', 'given_name' => 'Ana', 'family_name' => 'Ficticia',
                'picture' => 'https://media.licdn.test/foto.jpg', 'locale' => 'pt-BR',
            ]),
        ]);
    }
}
