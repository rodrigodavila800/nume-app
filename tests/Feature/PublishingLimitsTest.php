<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Pack;
use App\Models\PackPurchase;
use App\Models\Production;
use App\Models\SocialConnection;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoPublication;
use App\Services\SocialPublishing\PublishingLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Redes liberadas e envios por mês definidos pelo admin da NUME. Redes e fila falsas; dados fictícios.
 */
class PublishingLimitsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private Cliente $cliente;

    private Pack $pack;

    private Video $video;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('videos');
        Queue::fake();
        config(['upload_post.api_key' => null, 'social.routing.youtube' => 'native', 'social.routing.instagram' => 'native', 'social.routing.facebook' => 'native']);

        $this->tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        $this->admin = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Admin', 'email' => 'admin@ficticio.test', 'password' => 'secret12345', 'role' => 'admin']);
        $this->cliente = Cliente::create(['tenant_id' => $this->tenant->id, 'name' => 'Ana', 'email' => 'ana@ficticio.test', 'password' => 'secret12345', 'brand_niche' => 'Padaria', 'website' => 'https://padaria.ficticio.test']);
        $this->pack = Pack::create(['tenant_id' => $this->tenant->id, 'name' => 'Plano Redes', 'slug' => 'plano-redes', 'price' => 300, 'video_quantity' => 10, 'status' => 'active', 'social_networks_limit' => 2, 'monthly_post_limit' => 2]);
        PackPurchase::create(['tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'pack_id' => $this->pack->id, 'amount' => 300, 'status' => 'paid', 'video_quantity' => 10]);

        $production = Production::create(['tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'title' => 'Campanha', 'status' => 'ready', 'created_by' => $this->admin->id]);
        Storage::disk('videos')->put('v.mp4', 'conteudo-ficticio');
        $this->video = Video::create(['tenant_id' => $this->tenant->id, 'production_id' => $production->id, 'title' => 'Corte', 'status' => 'ready', 'source_disk' => 'videos', 'source_path' => 'v.mp4', 'source_mime' => 'video/mp4', 'source_size' => 17]);
    }

    private function connect(string $platform): void
    {
        SocialConnection::create([
            'tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'platform' => $platform,
            'provider' => $platform === 'youtube' ? 'google' : 'meta', 'external_account_id' => $platform.'-1',
            'access_token' => 'token-ficticio', 'refresh_token' => 'refresh-ficticio', 'expires_at' => now()->addHour(),
        ]);
    }

    private function send(array $platforms)
    {
        return $this->actingAs($this->cliente, 'cliente')
            ->postJson(route('portal.videos.distribute', $this->video), ['titulo' => 'Lançamento', 'plataformas' => $platforms]);
    }

    public function test_limits_come_from_the_plan_and_the_admin_override_wins(): void
    {
        $limits = app(PublishingLimits::class);
        $this->assertSame(2, $limits->networksLimit($this->cliente));
        $this->assertSame(2, $limits->monthlyLimit($this->cliente));

        $this->cliente->forceFill(['social_networks_limit_override' => 5, 'monthly_post_limit_override' => 30])->save();
        $this->assertSame(5, $limits->networksLimit($this->cliente->fresh()));
        $this->assertSame(30, $limits->monthlyLimit($this->cliente->fresh()));
    }

    public function test_without_any_limit_configured_there_is_no_limit(): void
    {
        $this->pack->update(['social_networks_limit' => null, 'monthly_post_limit' => null]);

        $this->assertNull(app(PublishingLimits::class)->networksLimit($this->cliente));
        $this->assertTrue(app(PublishingLimits::class)->canConnect($this->cliente, ['youtube', 'instagram', 'facebook', 'tiktok']));
    }

    public function test_client_cannot_connect_more_networks_than_released(): void
    {
        config(['services.google.client_id' => 'id-ficticio', 'services.google.client_secret' => 'segredo-ficticio']);
        $this->connect('instagram');
        $this->connect('facebook');

        $this->actingAs($this->cliente, 'cliente')->get(route('portal.social.oauth.redirect', 'youtube'))
            ->assertRedirect(route('portal.social.index'))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Seu plano libera 2 redes conectadas'));

        $this->assertTrue(app(PublishingLimits::class)->canConnect($this->cliente, ['instagram']), 'reconectar a mesma rede pode');
    }

    public function test_send_with_more_networks_than_released_is_refused(): void
    {
        $this->cliente->forceFill(['social_networks_limit_override' => 1])->save();
        $this->connect('youtube');
        $this->connect('instagram');

        $this->send(['youtube', 'instagram'])->assertStatus(403)->assertJsonPath('reason', 'networks_limit');
        $this->assertSame(0, VideoPublication::forTenant($this->tenant->id)->count());
    }

    public function test_monthly_sends_limit_counts_one_per_send_and_ignores_failures(): void
    {
        $this->connect('youtube');
        $this->connect('instagram');

        $this->send(['youtube', 'instagram'])->assertStatus(202);
        $this->assertSame(1, app(PublishingLimits::class)->sendsThisMonth($this->cliente), 'duas redes no mesmo envio = 1');

        VideoPublication::forTenant($this->tenant->id)->update(['created_at' => now()->subMinutes(5)]);
        $this->send(['youtube'])->assertStatus(202);
        $this->send(['youtube'])->assertStatus(403)->assertJsonPath('reason', 'monthly_limit');

        VideoPublication::forTenant($this->tenant->id)->latest('id')->first()->update(['status' => 'falhou']);
        $this->send(['youtube'])->assertStatus(202);
    }

    public function test_quote_limits_are_copied_to_the_purchase(): void
    {
        PackPurchase::query()->delete();
        PackPurchase::create(['tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'amount' => 100, 'status' => 'paid', 'video_quantity' => 4, 'social_networks_limit' => 3, 'monthly_post_limit' => 12]);

        $this->assertSame(3, app(PublishingLimits::class)->networksLimit($this->cliente));
        $this->assertSame(12, app(PublishingLimits::class)->monthlyLimit($this->cliente));
    }

    public function test_only_admin_sets_the_override_on_the_client_page(): void
    {
        $editor = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Editor', 'email' => 'editor@ficticio.test', 'password' => 'secret12345', 'role' => 'editor']);
        $payload = ['name' => 'Ana', 'email' => 'ana@ficticio.test', 'social_networks_limit_override' => 6, 'monthly_post_limit_override' => 40];

        $this->actingAs($editor)->put(route('clientes.update', $this->cliente), $payload);
        $this->assertNull($this->cliente->fresh()->social_networks_limit_override);

        $this->actingAs($this->admin)->put(route('clientes.update', $this->cliente), $payload)->assertRedirect();
        $this->assertSame(6, $this->cliente->fresh()->social_networks_limit_override);
        $this->assertSame(40, $this->cliente->fresh()->monthly_post_limit_override);
    }

    public function test_admin_sets_limits_on_the_pack(): void
    {
        $this->actingAs($this->admin)->get(route('packs.edit', $this->pack))->assertOk()->assertSee('Redes liberadas');
    }

    public function test_partial_has_no_inline_css(): void
    {
        $this->assertStringNotContainsString('style="', file_get_contents(resource_path('views/packs/_publishing_limits.blade.php')));
    }
}
