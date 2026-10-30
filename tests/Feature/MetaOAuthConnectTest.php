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
 * Login da Meta (Página + Instagram profissional). App, tokens e Páginas fictícios; Graph API simulada.
 */
class MetaOAuthConnectTest extends TestCase
{
    use RefreshDatabase;

    private const GRAPH = 'https://graph.facebook.com/v26.0';

    private const ALL_PERMISSIONS = ['pages_show_list', 'pages_read_engagement', 'pages_manage_posts', 'instagram_basic', 'instagram_content_publish'];

    private Cliente $cliente;

    private array $logs = [];

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config([
            'services.meta.client_id' => 'app-meta-ficticio',
            'services.meta.client_secret' => 'segredo-meta-ficticio',
            'services.meta.config_id' => null,
            'services.meta.redirect' => null,
            'social.meta.graph_version' => 'v26.0',
        ]);
        Event::listen(MessageLogged::class, function (MessageLogged $event) {
            $this->logs[] = $event->message.json_encode($event->context);
        });

        $tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active']);
        $this->cliente = Cliente::create(['tenant_id' => $tenant->id, 'name' => 'Cliente A', 'email' => 'a@ficticio.test', 'password' => bcrypt('secret123')]);
    }

    public function test_redirect_asks_facebook_for_the_minimum_publishing_permissions(): void
    {
        $location = $this->actingAs($this->cliente, 'cliente')->get(route('portal.social.oauth.redirect', 'meta'))->headers->get('Location');

        $this->assertStringStartsWith('https://www.facebook.com/v26.0/dialog/oauth?', $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame(session('social_oauth.meta.state'), $query['state']);
        $this->assertSame(implode(',', self::ALL_PERMISSIONS), $query['scope']);
        $this->assertSame(route('portal.social.oauth.callback', 'meta'), $query['redirect_uri']);
        $this->assertArrayNotHasKey('config_id', $query);
    }

    public function test_login_for_business_configuration_replaces_the_scope_list(): void
    {
        config(['services.meta.config_id' => 'config-ficticia']);

        $location = $this->actingAs($this->cliente, 'cliente')->get(route('portal.social.oauth.redirect', 'meta'))->headers->get('Location');
        parse_str(parse_url($location, PHP_URL_QUERY), $query);

        $this->assertSame('config-ficticia', $query['config_id']);
        $this->assertArrayNotHasKey('scope', $query);
    }

    public function test_single_page_connects_facebook_and_instagram_with_the_page_token_only(): void
    {
        $this->fakeGraph([$this->page('111', 'Clinica Ficticia', instagram: ['id' => '999', 'username' => 'clinicaficticia'])]);

        $this->get($this->callbackUrl($this->start()))
            ->assertRedirect(route('portal.social.index'))
            ->assertSessionHas('success', 'Página Clinica Ficticia e Instagram @clinicaficticia conectados com sucesso!');

        $facebook = SocialConnection::forTenant($this->cliente->tenant_id)->where('platform', 'facebook')->sole();
        $instagram = SocialConnection::forTenant($this->cliente->tenant_id)->where('platform', 'instagram')->sole();

        $this->assertSame('111', $facebook->external_account_id);
        $this->assertSame('meta', $facebook->provider);
        $this->assertSame('Clinica Ficticia', $facebook->display_name);
        $this->assertSame('token-pagina-111', $facebook->access_token);
        $this->assertNull($facebook->expires_at);
        $this->assertNull($facebook->refresh_token);
        $this->assertSame('999', $instagram->external_account_id);
        $this->assertSame('@clinicaficticia', $instagram->handle);
        $this->assertSame('token-pagina-111', $instagram->access_token);

        // O token do usuário nunca é guardado; os de Página ficam criptografados.
        $raw = DB::table('social_connections')->pluck('access_token')->implode(' ');
        $this->assertStringNotContainsString('token-pagina', $raw);
        $this->assertStringNotContainsString('token-longo', $raw);

        // Segredo do app só no corpo da troca de código; chamadas da Graph com token no header e appsecret_proof.
        Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), '/me/accounts')
            && $request->hasHeader('Authorization', 'Bearer token-longo-usuario')
            && str_contains($request->url(), 'appsecret_proof='.hash_hmac('sha256', 'token-longo-usuario', 'segredo-meta-ficticio'))
            && ! str_contains($request->url(), 'segredo-meta-ficticio'));

        foreach ($this->logs as $line) {
            $this->assertStringNotContainsString('token-', $line);
            $this->assertStringNotContainsString('segredo-meta', $line);
        }
    }

    public function test_pages_are_requested_in_small_batches_following_the_pagination(): void
    {
        config(['social.meta.pages_batch_size' => 2]);

        $first = [$this->page('111', 'Pagina Um'), $this->page('222', 'Pagina Dois')];
        $second = [$this->page('333', 'Pagina Tres')];

        Http::fake([
            self::GRAPH.'/oauth/access_token' => fn (HttpRequest $request) => Http::response([
                'access_token' => ($request['grant_type'] ?? null) === 'fb_exchange_token' ? 'token-longo-usuario' : 'token-curto-usuario',
            ]),
            self::GRAPH.'/me/permissions*' => Http::response(['data' => array_map(fn ($p) => ['permission' => $p, 'status' => 'granted'], self::ALL_PERMISSIONS)]),
            self::GRAPH.'/me/accounts*' => fn (HttpRequest $request) => str_contains($request->url(), 'after=cursor-um')
                ? Http::response(['data' => $second, 'paging' => ['cursors' => ['after' => 'cursor-dois']]])
                : Http::response(['data' => $first, 'paging' => ['cursors' => ['after' => 'cursor-um'], 'next' => 'https://graph.facebook.com/proxima']]),
        ]);

        $this->get($this->callbackUrl($this->start()))->assertRedirect(route('portal.social.meta.pages'));

        $this->get(route('portal.social.meta.pages'))
            ->assertSee('Pagina Um')
            ->assertSee('Pagina Dois')
            ->assertSee('Pagina Tres');

        Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), '/me/accounts') && str_contains($request->url(), 'limit=2'));
    }

    public function test_a_failing_graph_call_logs_only_the_step_and_the_meta_codes(): void
    {
        Http::fake([
            self::GRAPH.'/oauth/access_token' => fn (HttpRequest $request) => Http::response([
                'access_token' => ($request['grant_type'] ?? null) === 'fb_exchange_token' ? 'token-longo-usuario' : 'token-curto-usuario',
            ]),
            self::GRAPH.'/me/permissions*' => Http::response(['data' => array_map(fn ($p) => ['permission' => $p, 'status' => 'granted'], self::ALL_PERMISSIONS)]),
            self::GRAPH.'/me/accounts*' => Http::response(['error' => ['code' => 1, 'message' => 'Mensagem com token-vazado', 'type' => 'OAuthException']], 500),
        ]);

        $this->get($this->callbackUrl($this->start()))
            ->assertRedirect(route('portal.social.index'))
            ->assertSessionHas('error');

        $line = collect($this->logs)->first(fn (string $log) => str_starts_with($log, 'social.meta.graph_failed'));
        $this->assertNotNull($line);
        $this->assertStringContainsString('"step":"me\/accounts"', $line);
        $this->assertStringContainsString('"code":1', $line);
        $this->assertStringNotContainsString('token-', $line);
        $this->assertStringNotContainsString('Mensagem com', $line);
    }

    public function test_page_without_instagram_connects_only_facebook_and_explains(): void
    {
        $this->fakeGraph([$this->page('111', 'Clinica Ficticia')]);

        $this->get($this->callbackUrl($this->start()))
            ->assertSessionHas('success', 'Página Clinica Ficticia conectada com sucesso! Para publicar no Instagram, ligue uma conta profissional a esta Página e conecte de novo.');

        $this->assertSame(['facebook'], SocialConnection::forTenant($this->cliente->tenant_id)->pluck('platform')->all());
    }

    public function test_declined_instagram_permissions_connect_only_facebook(): void
    {
        $this->fakeGraph(
            [$this->page('111', 'Clinica Ficticia', instagram: ['id' => '999', 'username' => 'clinicaficticia'])],
            ['pages_show_list', 'pages_read_engagement', 'pages_manage_posts'],
        );

        $this->get($this->callbackUrl($this->start()))->assertSessionHas('success');

        $this->assertSame(['facebook'], SocialConnection::forTenant($this->cliente->tenant_id)->pluck('platform')->all());
    }

    public function test_declined_page_permissions_connect_nothing(): void
    {
        $this->fakeGraph([$this->page('111', 'Clinica Ficticia')], ['pages_show_list', 'instagram_basic']);

        $this->get($this->callbackUrl($this->start()))
            ->assertSessionHas('error', 'Para publicar, aceite todas as permissões pedidas na tela do Facebook.');

        $this->assertSame(0, DB::table('social_connections')->count());
    }

    public function test_pages_where_the_person_cannot_create_content_are_ignored(): void
    {
        $this->fakeGraph([$this->page('111', 'So Moderador', tasks: ['MODERATE'])]);

        $this->get($this->callbackUrl($this->start()))
            ->assertSessionHas('error', 'Não encontramos nenhuma Página do Facebook em que você possa publicar. Verifique se você é administrador da Página.');

        $this->assertSame(0, DB::table('social_connections')->count());
    }

    public function test_several_pages_ask_which_one_and_keep_tokens_out_of_the_screen(): void
    {
        $this->fakeGraph([
            $this->page('111', 'Clinica Ficticia', instagram: ['id' => '999', 'username' => 'clinicaficticia']),
            $this->page('222', 'Outra Marca'),
        ]);

        $this->get($this->callbackUrl($this->start()))->assertRedirect(route('portal.social.meta.pages'));
        $this->assertSame(0, DB::table('social_connections')->count());
        $this->assertStringNotContainsString('token-pagina', json_encode(session('social_oauth.meta_pages')));

        $this->get(route('portal.social.meta.pages'))
            ->assertOk()
            ->assertSee('Clinica Ficticia')
            ->assertSee('@clinicaficticia')
            ->assertSee('Outra Marca')
            ->assertDontSee('token-pagina');

        $this->post(route('portal.social.meta.pages.store'), ['page_id' => '222'])
            ->assertRedirect(route('portal.social.index'))
            ->assertSessionHas('success');

        $facebook = SocialConnection::forTenant($this->cliente->tenant_id)->sole();
        $this->assertSame('222', $facebook->external_account_id);
        $this->assertSame('token-pagina-222', $facebook->access_token);

        // A escolha vale uma vez.
        $this->post(route('portal.social.meta.pages.store'), ['page_id' => '111'])
            ->assertSessionHas('error', 'A escolha da Página expirou. Conecte o Facebook novamente.');
        $this->assertSame(1, DB::table('social_connections')->count());
    }

    public function test_a_page_outside_the_list_or_an_expired_choice_is_rejected(): void
    {
        $this->fakeGraph([$this->page('111', 'A'), $this->page('222', 'B')]);
        $this->get($this->callbackUrl($this->start()));

        $this->post(route('portal.social.meta.pages.store'), ['page_id' => 'pagina-de-outro'])
            ->assertSessionHas('error');

        $this->get($this->callbackUrl($this->start()));
        $this->travel(11)->minutes();
        $this->get(route('portal.social.meta.pages'))->assertRedirect(route('portal.social.index'))->assertSessionHas('error');
        $this->assertSame(0, DB::table('social_connections')->count());
    }

    public function test_another_cliente_cannot_use_a_pending_choice(): void
    {
        $this->fakeGraph([$this->page('111', 'A'), $this->page('222', 'B')]);
        $this->get($this->callbackUrl($this->start()));

        $outro = Cliente::create(['tenant_id' => $this->cliente->tenant_id, 'name' => 'Cliente B', 'email' => 'b@ficticio.test', 'password' => bcrypt('secret123')]);
        $this->actingAs($outro, 'cliente')->post(route('portal.social.meta.pages.store'), ['page_id' => '111'])->assertSessionHas('error');

        $this->assertSame(0, DB::table('social_connections')->count());
    }

    public function test_forged_state_and_cancellation_save_nothing(): void
    {
        $this->start();
        $this->get(route('portal.social.oauth.callback', ['platform' => 'meta', 'state' => 'forjado', 'code' => 'c']))
            ->assertSessionHas('error', 'A conexão expirou ou não foi iniciada por aqui. Tente conectar novamente.');

        $state = $this->start();
        $this->get(route('portal.social.oauth.callback', ['platform' => 'meta', 'state' => $state, 'error' => 'access_denied']))
            ->assertSessionHas('error', 'Você cancelou a autorização no Facebook. Nada foi conectado.');

        Http::assertNothingSent();
    }

    public function test_disconnecting_meta_deletes_the_tokens_and_cancels_pending_sends(): void
    {
        $this->fakeGraph([$this->page('111', 'Clinica Ficticia', instagram: ['id' => '999', 'username' => 'clinicaficticia'])]);
        $this->get($this->callbackUrl($this->start()));
        $instagram = SocialConnection::forTenant($this->cliente->tenant_id)->where('platform', 'instagram')->sole();

        $this->delete(route('portal.social.connections.destroy', $instagram))->assertSessionHas('success');

        $this->assertSame(['facebook'], SocialConnection::forTenant($this->cliente->tenant_id)->pluck('platform')->all());
    }

    private function start(): string
    {
        $this->actingAs($this->cliente, 'cliente')->get(route('portal.social.oauth.redirect', 'meta'));

        return session('social_oauth.meta.state');
    }

    private function callbackUrl(string $state): string
    {
        return route('portal.social.oauth.callback', ['platform' => 'meta', 'state' => $state, 'code' => 'codigo-ficticio']);
    }

    private function page(string $id, string $name, array $tasks = ['CREATE_CONTENT', 'MANAGE'], ?array $instagram = null): array
    {
        return array_filter([
            'id' => $id,
            'name' => $name,
            'access_token' => 'token-pagina-'.$id,
            'tasks' => $tasks,
            'picture' => ['data' => ['url' => 'https://scontent.ficticio.test/'.$id.'.jpg']],
            'instagram_business_account' => $instagram === null ? null : array_merge($instagram, ['profile_picture_url' => 'https://scontent.ficticio.test/ig.jpg']),
        ], fn ($value) => $value !== null);
    }

    private function fakeGraph(array $pages, array $permissions = self::ALL_PERMISSIONS): void
    {
        Http::fake([
            self::GRAPH.'/oauth/access_token' => function (HttpRequest $request) {
                return $request['grant_type'] === 'fb_exchange_token'
                    ? Http::response(['access_token' => 'token-longo-usuario', 'token_type' => 'bearer', 'expires_in' => 5183944])
                    : Http::response(['access_token' => 'token-curto-usuario', 'token_type' => 'bearer', 'expires_in' => 3600]);
            },
            self::GRAPH.'/me/permissions*' => Http::response(['data' => array_map(fn ($p) => ['permission' => $p, 'status' => 'granted'], $permissions)]),
            self::GRAPH.'/me/accounts*' => Http::response(['data' => $pages]),
        ]);
    }
}
