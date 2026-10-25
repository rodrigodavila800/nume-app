<?php

namespace Tests\Feature;

use App\Enums\PlataformaAlvo;
use App\Models\Cliente;
use App\Models\Production;
use App\Models\SocialConnection;
use App\Models\SocialProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoPublication;
use App\Services\SocialPublishing\OAuth\GoogleOAuthClient;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Ciclo de vida das contas conectadas pelo app próprio: tela do portal, desconexão, renovação diária e DNA.
 * Dados e tokens fictícios, Google simulado.
 */
class SocialConnectionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config([
            'upload_post.api_key' => null,
            'services.google.client_id' => 'cliente-oauth-ficticio',
            'services.google.client_secret' => 'segredo-ficticio',
            'social.routing.youtube' => 'native',
        ]);

        $this->tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        $this->cliente = Cliente::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Cliente A', 'email' => 'a@ficticio.test', 'password' => bcrypt('secret123'),
            'brand_niche' => 'Saude', 'website' => 'https://clinica-ficticia.test', 'youtube_channel' => '@digitado-no-dna',
        ]);
    }

    public function test_portal_offers_the_direct_youtube_connection(): void
    {
        $this->actingAs($this->cliente, 'cliente')->get(route('portal.social.index'))
            ->assertOk()
            ->assertSee(route('portal.social.oauth.redirect', 'youtube'), false)
            ->assertSee('Conectar YouTube')
            ->assertDontSee('Desconectar');
    }

    public function test_portal_hides_the_direct_connection_while_google_is_not_configured(): void
    {
        config(['services.google.client_id' => null]);

        $this->actingAs($this->cliente, 'cliente')->get(route('portal.social.index'))
            ->assertOk()
            ->assertDontSee(route('portal.social.oauth.redirect', 'youtube'), false);
    }

    public function test_portal_shows_the_connected_channel_and_never_its_tokens(): void
    {
        $connection = $this->connect();

        $this->actingAs($this->cliente, 'cliente')->get(route('portal.social.index'))
            ->assertOk()
            ->assertSee('Conectado direto pela NUME')
            ->assertSee('@canalficticio')
            ->assertSee(route('portal.social.connections.destroy', $connection), false)
            ->assertSee('1 de 8')
            ->assertDontSee('acesso-ficticio')
            ->assertDontSee('atualizacao-ficticia');
    }

    public function test_portal_asks_to_reconnect_an_expired_channel(): void
    {
        $this->connect(['status' => SocialConnection::STATUS_EXPIRED]);

        $this->actingAs($this->cliente, 'cliente')->get(route('portal.social.index'))
            ->assertOk()
            ->assertSee('Reconectar')
            ->assertSee('0 de 8');
    }

    public function test_disconnect_revokes_at_google_deletes_tokens_and_cancels_pending_sends(): void
    {
        $connection = $this->connect();
        [$pendente, $agendado, $publicado] = $this->publications($connection, ['pendente', 'agendado', 'publicado']);
        Http::fake([GoogleOAuthClient::REVOKE_URL => Http::response([])]);

        $this->actingAs($this->cliente, 'cliente')
            ->delete(route('portal.social.connections.destroy', $connection))
            ->assertRedirect(route('portal.social.index'))
            ->assertSessionHas('success');

        Http::assertSent(fn (HttpRequest $request) => $request->url() === GoogleOAuthClient::REVOKE_URL && $request['token'] === '1//atualizacao-ficticia');
        $this->assertSame(0, DB::table('social_connections')->count());
        $this->assertSame('disconnected', $pendente->fresh()->last_error);
        $this->assertSame('falhou', $agendado->fresh()->status);
        $this->assertSame('publicado', $publicado->fresh()->status);
    }

    public function test_disconnect_still_deletes_tokens_when_google_is_unreachable(): void
    {
        $connection = $this->connect();
        Http::fake([GoogleOAuthClient::REVOKE_URL => Http::failedConnection()]);

        $this->actingAs($this->cliente, 'cliente')->delete(route('portal.social.connections.destroy', $connection))->assertRedirect();

        $this->assertSame(0, DB::table('social_connections')->count());
    }

    public function test_cliente_cannot_disconnect_someone_elses_channel(): void
    {
        $outroMesmoTenant = Cliente::create(['tenant_id' => $this->tenant->id, 'name' => 'Cliente B', 'email' => 'b@ficticio.test', 'password' => bcrypt('secret123')]);
        $tenantB = Tenant::create(['name' => 'Produtora B', 'slug' => 'produtora-b', 'status' => 'active']);
        $outroTenant = Cliente::create(['tenant_id' => $tenantB->id, 'name' => 'Cliente C', 'email' => 'c@ficticio.test', 'password' => bcrypt('secret123')]);
        $doMesmoTenant = $this->connect(['cliente_id' => $outroMesmoTenant->id, 'external_account_id' => 'UC-b']);
        $deOutroTenant = $this->connect(['tenant_id' => $tenantB->id, 'cliente_id' => $outroTenant->id, 'external_account_id' => 'UC-c']);

        $this->actingAs($this->cliente, 'cliente')->delete(route('portal.social.connections.destroy', $doMesmoTenant))->assertForbidden();
        $this->actingAs($this->cliente, 'cliente')->delete(route('portal.social.connections.destroy', $deOutroTenant))->assertNotFound();

        Http::assertNothingSent();
        $this->assertSame(2, DB::table('social_connections')->count());
    }

    public function test_daily_refresh_renews_stale_accounts_and_flags_revoked_ones(): void
    {
        $tenantB = Tenant::create(['name' => 'Produtora B', 'slug' => 'produtora-b', 'status' => 'active']);
        $clienteB = Cliente::create(['tenant_id' => $tenantB->id, 'name' => 'Cliente B', 'email' => 'b@ficticio.test', 'password' => bcrypt('secret123')]);

        $antiga = $this->connect(['last_refreshed_at' => now()->subDays(2), 'refresh_token' => '1//antiga']);
        $revogada = $this->connect(['tenant_id' => $tenantB->id, 'cliente_id' => $clienteB->id, 'external_account_id' => 'UC-b', 'last_refreshed_at' => now()->subDays(2), 'refresh_token' => '1//revogada']);
        $recente = $this->connect(['cliente_id' => Cliente::create(['tenant_id' => $this->tenant->id, 'name' => 'C', 'email' => 'c@ficticio.test', 'password' => bcrypt('secret123')])->id, 'external_account_id' => 'UC-c', 'last_refreshed_at' => now()->subHour()]);

        Http::fake([GoogleOAuthClient::TOKEN_URL => function (HttpRequest $request) {
            return $request['refresh_token'] === '1//revogada'
                ? Http::response(['error' => 'invalid_grant'], 400)
                : Http::response(['access_token' => 'ya29.renovado-ficticio', 'expires_in' => 3599, 'scope' => '']);
        }]);

        $this->artisan('social:refresh-tokens')
            ->expectsOutputToContain('Renovadas: 1. Precisam reconectar: 1.')
            ->assertSuccessful();

        $this->assertSame('ya29.renovado-ficticio', $antiga->fresh()->access_token);
        $this->assertTrue($antiga->fresh()->last_refreshed_at->isToday());
        $this->assertSame(SocialConnection::STATUS_EXPIRED, $revogada->fresh()->status);
        $this->assertSame('ya29.acesso-ficticio', $recente->fresh()->access_token);
        Http::assertSentCount(2);
    }

    public function test_daily_refresh_is_scheduled(): void
    {
        $commands = collect(app(Schedule::class)->events())->map(fn ($event) => $event->command)->implode("\n");

        $this->assertStringContainsString('social:refresh-tokens', $commands);
    }

    public function test_dna_prefers_the_channel_connected_in_the_own_app(): void
    {
        SocialProfile::create([
            'tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'upload_post_username' => 'perfil-ficticio',
            'connected' => true, 'platforms_connected' => ['youtube'], 'account_details' => ['youtube' => ['handle' => '@via-upload-post']],
        ]);

        $this->assertSame('@via-upload-post', $this->cliente->fresh()->getEffectiveSocialHandle(PlataformaAlvo::YOUTUBE));

        $connection = $this->connect();
        $this->assertSame('@canalficticio', $this->cliente->fresh()->getEffectiveSocialHandle(PlataformaAlvo::YOUTUBE));

        $connection->update(['status' => SocialConnection::STATUS_EXPIRED]);
        $this->assertSame('@via-upload-post', $this->cliente->fresh()->getEffectiveSocialHandle(PlataformaAlvo::YOUTUBE));
    }

    public function test_dna_lists_a_natively_connected_network_and_keeps_the_typed_handle_as_fallback(): void
    {
        $this->assertSame(['youtube' => '@digitado-no-dna'], $this->cliente->activeSocialChannels());

        $this->connect(['handle' => null]);
        $cliente = $this->cliente->fresh();

        $this->assertSame('@digitado-no-dna', $cliente->getEffectiveSocialHandle(PlataformaAlvo::YOUTUBE));
        $this->assertArrayHasKey('youtube', $cliente->activeSocialChannels());
        $this->assertSame('@digitado-no-dna', $cliente->youtube_channel);
    }

    public function test_social_page_has_no_inline_css(): void
    {
        $this->assertStringNotContainsString('style="', file_get_contents(resource_path('views/portal/social/index.blade.php')));
    }

    private function connect(array $attributes = []): SocialConnection
    {
        return SocialConnection::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'platform' => 'youtube',
            'provider' => 'google',
            'external_account_id' => 'UC-canal-ficticio',
            'handle' => '@canalficticio',
            'display_name' => 'Canal Ficticio',
            'access_token' => 'ya29.acesso-ficticio',
            'refresh_token' => '1//atualizacao-ficticia',
            'expires_at' => now()->addHour(),
            'last_refreshed_at' => now(),
        ], $attributes));
    }

    /**
     * @return list<VideoPublication>
     */
    private function publications(SocialConnection $connection, array $statuses): array
    {
        $staff = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Staff', 'email' => 'staff@ficticio.test', 'password' => 'secret12345', 'role' => 'admin']);
        $production = Production::create(['tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'title' => 'Campanha', 'status' => 'ready', 'created_by' => $staff->id]);
        $video = Video::create(['tenant_id' => $this->tenant->id, 'production_id' => $production->id, 'title' => 'Corte', 'status' => 'ready', 'video_url' => 'https://cdn.ficticio.test/v.mp4']);

        return array_map(fn (string $status) => VideoPublication::create([
            'tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'video_id' => $video->id,
            'driver' => 'youtube', 'platform' => 'youtube', 'social_connection_id' => $connection->id,
            'titulo' => 'Envio', 'plataformas' => ['youtube'], 'status' => $status,
        ]), $statuses);
    }
}
