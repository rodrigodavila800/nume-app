<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\SocialConnection;
use App\Models\Tenant;
use App\Services\SocialPublishing\OAuth\GoogleOAuthClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Conexão do YouTube pelo app próprio (OAuth + PKCE). Credenciais e tokens fictícios, Google simulado.
 */
class YouTubeOAuthConnectTest extends TestCase
{
    use RefreshDatabase;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config([
            'services.google.client_id' => 'cliente-oauth-ficticio.apps.googleusercontent.com',
            'services.google.client_secret' => 'segredo-ficticio',
            'services.google.youtube_redirect' => null,
        ]);

        $tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active']);
        $this->cliente = Cliente::create(['tenant_id' => $tenant->id, 'name' => 'Cliente A', 'email' => 'a@ficticio.test', 'password' => bcrypt('secret123')]);
    }

    public function test_redirect_without_google_credentials_explains_instead_of_breaking(): void
    {
        config(['services.google.client_id' => null]);

        $this->actingAs($this->cliente, 'cliente')->get(route('portal.social.oauth.redirect', 'youtube'))
            ->assertRedirect(route('portal.social.index'))
            ->assertSessionHas('error')
            ->assertSessionMissing('social_oauth.youtube');
    }

    public function test_redirect_sends_to_google_with_pkce_offline_access_and_minimum_scopes(): void
    {
        $response = $this->actingAs($this->cliente, 'cliente')->get(route('portal.social.oauth.redirect', 'youtube'));

        $location = $response->headers->get('Location');
        $this->assertStringStartsWith(GoogleOAuthClient::AUTHORIZE_URL.'?', $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $query);

        $pending = session('social_oauth.youtube');
        $this->assertSame($pending['state'], $query['state']);
        $this->assertSame($this->cliente->id, $pending['cliente_id']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertSame(rtrim(strtr(base64_encode(hash('sha256', $pending['verifier'], true)), '+/', '-_'), '='), $query['code_challenge']);
        $this->assertSame('offline', $query['access_type']);
        $this->assertSame('consent', $query['prompt']);
        $this->assertSame('https://www.googleapis.com/auth/youtube.upload https://www.googleapis.com/auth/youtube.readonly', $query['scope']);
        $this->assertSame(route('portal.social.oauth.callback', 'youtube'), $query['redirect_uri']);
    }

    public function test_only_supported_platforms_have_routes(): void
    {
        $this->actingAs($this->cliente, 'cliente')->get('/portal/redes-sociais/conectar/pinterest')->assertNotFound();
    }

    public function test_callback_rejects_a_state_that_does_not_match(): void
    {
        $this->startConnection();

        $this->get(route('portal.social.oauth.callback', ['platform' => 'youtube', 'state' => 'forjado', 'code' => 'codigo']))
            ->assertRedirect(route('portal.social.index'))
            ->assertSessionHas('error', 'A conexão expirou ou não foi iniciada por aqui. Tente conectar novamente.');

        Http::assertNothingSent();
        $this->assertSame(0, DB::table('social_connections')->count());
    }

    public function test_callback_without_starting_here_is_rejected(): void
    {
        $this->actingAs($this->cliente, 'cliente')
            ->get(route('portal.social.oauth.callback', ['platform' => 'youtube', 'state' => 'qualquer', 'code' => 'codigo']))
            ->assertSessionHas('error');

        Http::assertNothingSent();
    }

    public function test_state_is_single_use(): void
    {
        $state = $this->startConnection();
        $this->fakeGoogle();

        $this->get($this->callbackUrl($state))->assertSessionHas('success');
        $this->get($this->callbackUrl($state))->assertSessionHas('error');

        Http::assertSentCount(2);
    }

    public function test_expired_state_is_rejected(): void
    {
        $state = $this->startConnection();
        $this->travel(11)->minutes();

        $this->get($this->callbackUrl($state))->assertSessionHas('error');
        Http::assertNothingSent();
    }

    public function test_state_started_by_another_cliente_is_rejected(): void
    {
        $state = $this->startConnection();
        $outro = Cliente::create(['tenant_id' => $this->cliente->tenant_id, 'name' => 'Cliente B', 'email' => 'b@ficticio.test', 'password' => bcrypt('secret123')]);

        $this->actingAs($outro, 'cliente')->get($this->callbackUrl($state))->assertSessionHas('error');
        Http::assertNothingSent();
    }

    public function test_successful_callback_stores_the_channel_with_encrypted_tokens_and_clean_logs(): void
    {
        $logs = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logs) {
            $logs[] = $event->message.json_encode($event->context);
        });

        $state = $this->startConnection();
        $verifier = session('social_oauth.youtube.verifier');
        $this->fakeGoogle();

        $this->get($this->callbackUrl($state))
            ->assertRedirect(route('portal.social.index'))
            ->assertSessionHas('success', 'Canal do YouTube @canalficticio conectado com sucesso!');

        $connection = SocialConnection::forTenant($this->cliente->tenant_id)->sole();
        $this->assertSame($this->cliente->id, $connection->cliente_id);
        $this->assertSame('youtube', $connection->platform);
        $this->assertSame('google', $connection->provider);
        $this->assertSame('UC-canal-ficticio', $connection->external_account_id);
        $this->assertSame('@canalficticio', $connection->handle);
        $this->assertSame('ya29.acesso-ficticio', $connection->access_token);
        $this->assertSame('1//atualizacao-ficticia', $connection->refresh_token);
        $this->assertTrue($connection->expires_at->between(now()->addMinutes(59), now()->addMinutes(61)));

        $raw = DB::table('social_connections')->first();
        $this->assertStringNotContainsString('acesso-ficticio', $raw->access_token);
        $this->assertStringNotContainsString('atualizacao-ficticia', $raw->refresh_token);

        Http::assertSent(fn (HttpRequest $request) => $request->url() === GoogleOAuthClient::TOKEN_URL
            && $request['code'] === 'codigo-ficticio'
            && $request['code_verifier'] === $verifier
            && $request['grant_type'] === 'authorization_code');
        Http::assertSent(fn (HttpRequest $request) => str_starts_with($request->url(), GoogleOAuthClient::CHANNELS_URL)
            && $request->hasHeader('Authorization', 'Bearer ya29.acesso-ficticio'));

        $this->assertNotEmpty($logs);
        foreach ($logs as $line) {
            $this->assertStringNotContainsString('ficticio', str_replace(['@canalficticio', 'UC-canal-ficticio'], '', $line));
        }
    }

    public function test_user_cancelling_on_google_saves_nothing(): void
    {
        $state = $this->startConnection();

        $this->get(route('portal.social.oauth.callback', ['platform' => 'youtube', 'state' => $state, 'error' => 'access_denied']))
            ->assertSessionHas('error', 'Você cancelou a autorização no Google. Nada foi conectado.');

        Http::assertNothingSent();
    }

    public function test_missing_upload_scope_revokes_and_saves_nothing(): void
    {
        $state = $this->startConnection();
        $this->fakeGoogle(['scope' => 'https://www.googleapis.com/auth/youtube.readonly']);

        $this->get($this->callbackUrl($state))
            ->assertSessionHas('error', 'Para publicar, autorize o envio de vídeos ao seu canal na tela do Google.');

        Http::assertSent(fn (HttpRequest $request) => $request->url() === GoogleOAuthClient::REVOKE_URL);
        $this->assertSame(0, DB::table('social_connections')->count());
    }

    public function test_account_without_channel_revokes_and_saves_nothing(): void
    {
        $state = $this->startConnection();
        $this->fakeGoogle(channels: ['items' => []]);

        $this->get($this->callbackUrl($state))
            ->assertSessionHas('error', 'Esta conta Google não tem um canal do YouTube. Crie o canal e tente de novo.');

        Http::assertSent(fn (HttpRequest $request) => $request->url() === GoogleOAuthClient::REVOKE_URL);
        $this->assertSame(0, DB::table('social_connections')->count());
    }

    public function test_rejected_code_saves_nothing(): void
    {
        $state = $this->startConnection();
        Http::fake([GoogleOAuthClient::TOKEN_URL => Http::response(['error' => 'invalid_grant', 'error_description' => 'Bad Request'], 400)]);

        $this->get($this->callbackUrl($state))
            ->assertSessionHas('error', 'Não foi possível conectar o YouTube agora. Tente novamente em alguns minutos.');

        $this->assertSame(0, DB::table('social_connections')->count());
    }

    public function test_reconnecting_the_same_channel_updates_it_and_keeps_the_refresh_token(): void
    {
        $this->fakeGoogle();
        $this->get($this->callbackUrl($this->startConnection()))->assertSessionHas('success');
        $this->fakeGoogle(['access_token' => 'ya29.novo-acesso', 'refresh_token' => null], fresh: true);

        $this->get($this->callbackUrl($this->startConnection()))->assertSessionHas('success');

        $connection = SocialConnection::forTenant($this->cliente->tenant_id)->sole();
        $this->assertSame('ya29.novo-acesso', $connection->access_token);
        $this->assertSame('1//atualizacao-ficticia', $connection->refresh_token);
    }

    private function startConnection(): string
    {
        $this->actingAs($this->cliente, 'cliente')->get(route('portal.social.oauth.redirect', 'youtube'));

        return session('social_oauth.youtube.state');
    }

    private function callbackUrl(string $state): string
    {
        return route('portal.social.oauth.callback', ['platform' => 'youtube', 'state' => $state, 'code' => 'codigo-ficticio']);
    }

    private function fakeGoogle(array $token = [], ?array $channels = null, bool $fresh = false): void
    {
        if ($fresh) {
            Http::swap(new HttpFactory);
            Http::preventStrayRequests();
        }

        Http::fake([
            GoogleOAuthClient::TOKEN_URL => Http::response(array_merge([
                'access_token' => 'ya29.acesso-ficticio',
                'refresh_token' => '1//atualizacao-ficticia',
                'expires_in' => 3599,
                'scope' => 'https://www.googleapis.com/auth/youtube.upload https://www.googleapis.com/auth/youtube.readonly',
                'token_type' => 'Bearer',
            ], $token)),
            GoogleOAuthClient::CHANNELS_URL.'*' => Http::response($channels ?? ['items' => [[
                'id' => 'UC-canal-ficticio',
                'snippet' => ['title' => 'Canal Ficticio', 'customUrl' => '@canalficticio', 'thumbnails' => ['default' => ['url' => 'https://yt3.ggpht.test/avatar.jpg']]],
            ]]]),
            GoogleOAuthClient::REVOKE_URL => Http::response([]),
        ]);
    }
}
