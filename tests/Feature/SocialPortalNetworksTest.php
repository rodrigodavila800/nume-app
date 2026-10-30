<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\SocialConnection;
use App\Models\Tenant;
use App\Models\VideoPublication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tela de Redes Sociais do portal com YouTube, Meta (Instagram + Facebook) e Threads diretos.
 */
class SocialPortalNetworksTest extends TestCase
{
    use RefreshDatabase;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'upload_post.api_key' => null,
            'services.google.client_id' => null,
            'services.meta.client_id' => 'app-meta-ficticio',
            'services.meta.client_secret' => 'segredo-meta-ficticio',
            'services.threads.client_id' => 'app-threads-ficticio',
            'services.threads.client_secret' => 'segredo-threads-ficticio',
        ]);

        $tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        $this->cliente = Cliente::create([
            'tenant_id' => $tenant->id, 'name' => 'Cliente A', 'email' => 'a@ficticio.test', 'password' => bcrypt('secret123'),
            'brand_niche' => 'Saude', 'website' => 'https://clinica-ficticia.test',
        ]);
    }

    public function test_instagram_and_facebook_connect_through_the_meta_login_and_threads_through_its_own(): void
    {
        $this->actingAs($this->cliente, 'cliente')->get(route('portal.social.index'))
            ->assertOk()
            ->assertSee('Conectar Conta')
            ->assertSee(route('portal.social.oauth.redirect', 'meta'), false)
            ->assertSee(route('portal.social.oauth.redirect', 'threads'), false)
            ->assertSee('Precisa ser conta profissional')
            ->assertDontSee(route('portal.social.oauth.redirect', 'youtube'), false);
    }

    public function test_networks_switched_to_upload_post_hide_the_direct_connection(): void
    {
        config(['social.routing.threads' => 'upload_post']);

        $this->actingAs($this->cliente, 'cliente')->get(route('portal.social.index'))
            ->assertOk()
            ->assertDontSee(route('portal.social.oauth.redirect', 'threads'), false);
    }

    public function test_connected_accounts_show_handle_and_disconnect_without_tokens(): void
    {
        $instagram = $this->connect('instagram', 'meta', '@clinicaficticia');
        $this->connect('facebook', 'meta', null, 'Clinica Ficticia');

        $this->actingAs($this->cliente, 'cliente')->get(route('portal.social.index'))
            ->assertOk()
            ->assertSee('@clinicaficticia')
            ->assertSee('Clinica Ficticia')
            ->assertSee(route('portal.social.connections.destroy', $instagram), false)
            ->assertSee('2 de 8')
            ->assertDontSee('token-ficticio');
    }

    public function test_history_links_the_native_post_and_explains_failures(): void
    {
        $connection = $this->connect('facebook', 'meta', null, 'Clinica Ficticia');
        $base = ['tenant_id' => $this->cliente->tenant_id, 'cliente_id' => $this->cliente->id, 'driver' => 'facebook', 'platform' => 'facebook', 'social_connection_id' => $connection->id, 'plataformas' => ['facebook']];

        VideoPublication::create($base + ['titulo' => 'Publicado', 'status' => 'publicado', 'external_id' => '880001', 'resultado' => ['url' => 'https://www.facebook.com/reel/880001']]);
        VideoPublication::create($base + ['titulo' => 'Horizontal', 'status' => 'falhou', 'last_error' => 'reel_format_invalid']);

        $this->actingAs($this->cliente, 'cliente')->get(route('portal.social.index'))
            ->assertOk()
            ->assertSee('https://www.facebook.com/reel/880001', false)
            ->assertSee('Ver publicação')
            ->assertSee('O vídeo precisa ser vertical (9:16) e ter pelo menos 540x960 para virar Reel.');
    }

    public function test_page_selection_screen_has_no_inline_css(): void
    {
        foreach (['portal/social/index', 'portal/social/meta-pages'] as $view) {
            $this->assertStringNotContainsString('style="', file_get_contents(resource_path("views/{$view}.blade.php")), $view);
        }
    }

    private function connect(string $platform, string $provider, ?string $handle, ?string $name = null): SocialConnection
    {
        return SocialConnection::create([
            'tenant_id' => $this->cliente->tenant_id, 'cliente_id' => $this->cliente->id, 'platform' => $platform, 'provider' => $provider,
            'external_account_id' => $platform.'-ficticio', 'handle' => $handle, 'display_name' => $name, 'access_token' => 'token-ficticio',
        ]);
    }
}
