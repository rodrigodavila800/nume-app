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
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Instagram pelo login unificado da Meta (sem Página do Facebook). App, tokens e contas fictícios;
 * Graph API simulada.
 */
class InstagramUnifiedConnectTest extends TestCase
{
    use RefreshDatabase;

    private const GRAPH = 'https://graph.facebook.com/v26.0';

    private Cliente $cliente;

    private array $logs = [];

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config([
            'services.meta.client_id' => 'app-meta-ficticio',
            'services.meta.client_secret' => 'segredo-meta-ficticio',
            'services.meta.config_id' => 'config-pagina-ficticia',
            'services.meta.instagram_config_id' => 'config-instagram-ficticia',
            'services.meta.redirect' => null,
            'social.meta.graph_version' => 'v26.0',
        ]);
        Event::listen(MessageLogged::class, function (MessageLogged $event) {
            $this->logs[] = $event->message.json_encode($event->context);
        });

        $tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active']);
        $this->cliente = Cliente::create(['tenant_id' => $tenant->id, 'name' => 'Cliente A', 'email' => 'a@ficticio.test', 'password' => bcrypt('secret123')]);
    }

    public function test_redirect_uses_the_instagram_configuration_and_its_own_callback(): void
    {
        $query = $this->start();

        $this->assertSame('app-meta-ficticio', $query['client_id']);
        $this->assertSame('config-instagram-ficticia', $query['config_id']);
        $this->assertSame(route('portal.social.oauth.callback', 'instagram'), $query['redirect_uri']);
        $this->assertArrayNotHasKey('scope', $query);
    }

    public function test_a_single_assigned_account_connects_without_a_facebook_page(): void
    {
        $this->fakeGraph([['id' => '17841400000000001', 'username' => 'clinicaficticia']]);

        $this->returnFromMeta($this->start()['state'])
            ->assertRedirect(route('portal.social.index'))
            ->assertSessionHas('success', 'Instagram @clinicaficticia conectado com sucesso!');

        $connection = SocialConnection::forTenant($this->cliente->tenant_id)->sole();
        $this->assertSame('instagram', $connection->platform);
        $this->assertSame('instagram', $connection->provider);
        $this->assertSame('17841400000000001', $connection->external_account_id);
        $this->assertSame('token-negocio-ficticio', $connection->access_token);
        $this->assertNull($connection->expires_at);
        $this->assertStringNotContainsString('token-negocio', DB::table('social_connections')->value('access_token'));

        // Uma troca só (sem fb_exchange_token), com o retorno do Instagram; contas lidas com o token no header.
        Http::assertSent(fn (HttpRequest $request) => $request->url() === self::GRAPH.'/oauth/access_token'
            && $request['code'] === 'codigo-ficticio'
            && $request['redirect_uri'] === route('portal.social.oauth.callback', 'instagram'));
        Http::assertNotSent(fn (HttpRequest $request) => ($request['grant_type'] ?? null) === 'fb_exchange_token');
        Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), '/me/assigned_instagram_accounts')
            && $request->hasHeader('Authorization', 'Bearer token-negocio-ficticio')
            && str_contains($request->url(), 'appsecret_proof='.hash_hmac('sha256', 'token-negocio-ficticio', 'segredo-meta-ficticio')));

        foreach ($this->logs as $line) {
            $this->assertStringNotContainsString('token-', $line);
            $this->assertStringNotContainsString('segredo-meta', $line);
        }
    }

    public function test_several_accounts_ask_which_one_and_keep_the_token_out_of_the_screen(): void
    {
        $this->fakeGraph([
            ['id' => '17841400000000001', 'username' => 'clinicaficticia'],
            ['id' => '17841400000000002', 'username' => 'lojaficticia'],
        ]);

        $this->returnFromMeta($this->start()['state'])->assertRedirect(route('portal.social.instagram.accounts'));

        $this->get(route('portal.social.instagram.accounts'))
            ->assertOk()
            ->assertSee('@clinicaficticia')
            ->assertSee('@lojaficticia')
            ->assertDontSee('token-negocio');
        $this->assertStringNotContainsString('token-negocio', json_encode(session()->all()));

        $this->post(route('portal.social.instagram.accounts.store'), ['account_id' => '17841400000000002'])
            ->assertRedirect(route('portal.social.index'))
            ->assertSessionHas('success', 'Instagram @lojaficticia conectado com sucesso!');

        $this->assertSame('17841400000000002', SocialConnection::forTenant($this->cliente->tenant_id)->sole()->external_account_id);
    }

    public function test_an_account_outside_the_list_is_rejected(): void
    {
        $this->fakeGraph([
            ['id' => '17841400000000001', 'username' => 'clinicaficticia'],
            ['id' => '17841400000000002', 'username' => 'lojaficticia'],
        ]);
        $this->returnFromMeta($this->start()['state']);

        $this->post(route('portal.social.instagram.accounts.store'), ['account_id' => '999'])
            ->assertRedirect(route('portal.social.index'))
            ->assertSessionHas('error', 'A escolha da conta expirou. Conecte o Instagram novamente.');

        $this->assertSame(0, SocialConnection::count());
    }

    public function test_no_assigned_account_explains_what_to_mark(): void
    {
        $this->fakeGraph([]);

        $this->returnFromMeta($this->start()['state'])
            ->assertSessionHas('error', 'Nenhuma conta do Instagram foi liberada para a NUME. Conecte de novo e, na tela da Meta, marque a sua conta profissional do Instagram.');

        $this->assertSame(0, SocialConnection::count());
    }

    public function test_the_portal_offers_the_unified_login_and_falls_back_to_the_page_login(): void
    {
        $this->actingAs($this->cliente, 'cliente')->get(route('portal.social.index'))
            ->assertSee(route('portal.social.oauth.redirect', 'instagram'), false)
            ->assertSee('Não precisa de Página do Facebook.');

        config(['services.meta.instagram_config_id' => null]);

        $this->get(route('portal.social.index'))
            ->assertDontSee(route('portal.social.oauth.redirect', 'instagram'), false)
            ->assertSee('ligada a uma Página do Facebook');
    }

    /**
     * @return array<string, string>
     */
    private function start(): array
    {
        $location = $this->actingAs($this->cliente, 'cliente')->get(route('portal.social.oauth.redirect', 'instagram'))->headers->get('Location');
        $this->assertStringStartsWith('https://www.facebook.com/v26.0/dialog/oauth?', $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $query);

        return $query;
    }

    private function returnFromMeta(string $state): TestResponse
    {
        return $this->get(route('portal.social.oauth.callback', ['platform' => 'instagram', 'state' => $state, 'code' => 'codigo-ficticio']));
    }

    /**
     * @param  list<array{id: string, username: string}>  $accounts
     */
    private function fakeGraph(array $accounts): void
    {
        Http::fake([
            self::GRAPH.'/oauth/access_token' => Http::response(['access_token' => 'token-negocio-ficticio', 'token_type' => 'bearer']),
            self::GRAPH.'/me/assigned_instagram_accounts*' => Http::response(['data' => $accounts]),
        ]);
    }
}
