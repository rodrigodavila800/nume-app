<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\SocialConnection;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Conexão do TikTok pelo app próprio (Login Kit). App, tokens e conta fictícios; API simulada.
 */
class TikTokOAuthConnectTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://open.tiktokapis.com';

    private Cliente $cliente;

    private array $logs = [];

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config([
            'services.tiktok.client_key' => 'chave-tiktok-ficticia',
            'services.tiktok.client_secret' => 'segredo-tiktok-ficticio',
            'services.tiktok.redirect' => null,
        ]);
        Event::listen(MessageLogged::class, function (MessageLogged $event) {
            $this->logs[] = $event->message.json_encode($event->context);
        });

        $tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active']);
        $this->cliente = Cliente::create(['tenant_id' => $tenant->id, 'name' => 'Cliente A', 'email' => 'a@ficticio.test', 'password' => bcrypt('secret123')]);
    }

    public function test_redirect_asks_tiktok_for_login_and_video_publishing(): void
    {
        $location = $this->actingAs($this->cliente, 'cliente')->get(route('portal.social.oauth.redirect', 'tiktok'))->headers->get('Location');

        $this->assertStringStartsWith('https://www.tiktok.com/v2/auth/authorize/?', $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('chave-tiktok-ficticia', $query['client_key']);
        $this->assertSame('user.info.basic,video.publish', $query['scope']);
        $this->assertSame(route('portal.social.oauth.callback', 'tiktok'), $query['redirect_uri']);
        $this->assertSame(session('social_oauth.tiktok.state'), $query['state']);
    }

    public function test_successful_connection_stores_the_account_with_encrypted_tokens(): void
    {
        $this->fakeTikTok();

        $this->get($this->callbackUrl($this->start()))
            ->assertRedirect(route('portal.social.index'))
            ->assertSessionHas('success', 'TikTok @clinicaficticia conectado com sucesso!');

        $connection = SocialConnection::forTenant($this->cliente->tenant_id)->sole();
        $this->assertSame('tiktok', $connection->platform);
        $this->assertSame('tiktok', $connection->provider);
        $this->assertSame('open-id-ficticio', $connection->external_account_id);
        $this->assertSame('@clinicaficticia', $connection->handle);
        $this->assertSame('Clinica Ficticia', $connection->display_name);
        $this->assertNull($connection->avatar_url);
        $this->assertSame('act.acesso-ficticio', $connection->access_token);
        $this->assertSame('rft.atualizacao-ficticia', $connection->refresh_token);
        $this->assertTrue($connection->expires_at->between(now()->addHours(23), now()->addHours(25)));

        $raw = DB::table('social_connections')->first();
        $this->assertStringNotContainsString('acesso-ficticio', $raw->access_token);
        $this->assertStringNotContainsString('atualizacao-ficticia', $raw->refresh_token);

        Http::assertSent(fn (HttpRequest $request) => $request->url() === self::API.'/v2/oauth/token/'
            && $request['grant_type'] === 'authorization_code'
            && $request['code'] === 'codigo-ficticio'
            && $request['client_secret'] === 'segredo-tiktok-ficticio');
        Http::assertSent(fn (HttpRequest $request) => $request->url() === self::API.'/v2/post/publish/creator_info/query/'
            && $request->hasHeader('Authorization', 'Bearer act.acesso-ficticio'));

        foreach ($this->logs as $line) {
            $this->assertStringNotContainsString('ficticio', str_replace(['@clinicaficticia', 'open-id-ficticio'], '', $line));
        }
    }

    public function test_missing_publish_permission_revokes_and_saves_nothing(): void
    {
        $this->fakeTikTok(token: ['scope' => 'user.info.basic']);

        $this->get($this->callbackUrl($this->start()))
            ->assertSessionHas('error', 'Para publicar, autorize a publicação de vídeos na tela do TikTok.');

        Http::assertSent(fn (HttpRequest $request) => $request->url() === self::API.'/v2/oauth/revoke/' && $request['token'] === 'act.acesso-ficticio');
        $this->assertSame(0, DB::table('social_connections')->count());
    }

    public function test_app_daily_user_cap_is_explained(): void
    {
        $this->fakeTikTok(creator: ['data' => [], 'error' => ['code' => 'reached_active_user_cap', 'message' => 'cap']]);

        $this->get($this->callbackUrl($this->start()))
            ->assertSessionHas('error', 'O TikTok limitou novas contas no app hoje. Tente amanhã.');

        $this->assertSame(0, DB::table('social_connections')->count());
    }

    public function test_forged_state_and_cancellation_save_nothing(): void
    {
        $this->start();
        $this->get(route('portal.social.oauth.callback', ['platform' => 'tiktok', 'state' => 'forjado', 'code' => 'c']))
            ->assertSessionHas('error', 'A conexão expirou ou não foi iniciada por aqui. Tente conectar novamente.');

        $state = $this->start();
        $this->get(route('portal.social.oauth.callback', ['platform' => 'tiktok', 'state' => $state, 'error' => 'access_denied']))
            ->assertSessionHas('error', 'Você cancelou a autorização no TikTok. Nada foi conectado.');

        Http::assertNothingSent();
    }

    public function test_disconnect_revokes_at_tiktok_and_deletes_tokens(): void
    {
        $this->fakeTikTok();
        $this->get($this->callbackUrl($this->start()));
        $connection = SocialConnection::forTenant($this->cliente->tenant_id)->sole();

        $this->delete(route('portal.social.connections.destroy', $connection))->assertSessionHas('success');

        Http::assertSent(fn (HttpRequest $request) => $request->url() === self::API.'/v2/oauth/revoke/' && $request['token'] === 'act.acesso-ficticio');
        $this->assertSame(0, DB::table('social_connections')->count());
    }

    public function test_daily_refresh_rotates_the_tiktok_refresh_token(): void
    {
        $connection = SocialConnection::create([
            'tenant_id' => $this->cliente->tenant_id, 'cliente_id' => $this->cliente->id, 'platform' => 'tiktok', 'provider' => 'tiktok',
            'external_account_id' => 'open-id-ficticio', 'access_token' => 'act.velho', 'refresh_token' => 'rft.velho',
            'expires_at' => now()->addHour(), 'last_refreshed_at' => now()->subDay(),
        ]);
        Http::fake([self::API.'/v2/oauth/token/' => Http::response([
            'access_token' => 'act.novo', 'refresh_token' => 'rft.novo', 'expires_in' => 86400, 'refresh_expires_in' => 31536000,
            'open_id' => 'open-id-ficticio', 'scope' => 'user.info.basic,video.publish', 'token_type' => 'Bearer',
        ])]);

        $this->artisan('social:refresh-tokens')->expectsOutputToContain('Renovadas: 1.')->assertSuccessful();

        $this->assertSame('act.novo', $connection->fresh()->access_token);
        $this->assertSame('rft.novo', $connection->fresh()->refresh_token);
        Http::assertSent(fn (HttpRequest $request) => $request['grant_type'] === 'refresh_token' && $request['refresh_token'] === 'rft.velho');
    }

    private function start(): string
    {
        $this->actingAs($this->cliente, 'cliente')->get(route('portal.social.oauth.redirect', 'tiktok'));

        return session('social_oauth.tiktok.state');
    }

    private function callbackUrl(string $state): string
    {
        return route('portal.social.oauth.callback', ['platform' => 'tiktok', 'state' => $state, 'code' => 'codigo-ficticio', 'scopes' => 'user.info.basic,video.publish']);
    }

    private function fakeTikTok(array $token = [], ?array $creator = null): void
    {
        Http::fake([
            self::API.'/v2/oauth/token/' => Http::response(array_merge([
                'access_token' => 'act.acesso-ficticio', 'refresh_token' => 'rft.atualizacao-ficticia',
                'expires_in' => 86400, 'refresh_expires_in' => 31536000, 'open_id' => 'open-id-ficticio',
                'scope' => 'user.info.basic,video.publish', 'token_type' => 'Bearer',
            ], $token)),
            self::API.'/v2/post/publish/creator_info/query/' => Http::response($creator ?? [
                'data' => [
                    'creator_username' => 'clinicaficticia', 'creator_nickname' => 'Clinica Ficticia',
                    'creator_avatar_url' => 'https://p16.tiktokcdn.test/avatar.jpg',
                    'privacy_level_options' => ['PUBLIC_TO_EVERYONE', 'MUTUAL_FOLLOW_FRIENDS', 'SELF_ONLY'],
                    'comment_disabled' => false, 'duet_disabled' => false, 'stitch_disabled' => true, 'max_video_post_duration_sec' => 600,
                ],
                'error' => ['code' => 'ok', 'message' => '', 'log_id' => 'log-ficticio'],
            ]),
            self::API.'/v2/oauth/revoke/' => Http::response(''),
        ]);
    }
}
