<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Production;
use App\Models\SocialConnection;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Video;
use App\Services\SocialPublishing\Drivers\MetaGraphDriver;
use App\Services\SocialPublishing\Drivers\TikTokDriver;
use App\Services\SocialPublishing\Drivers\UploadPostDriver;
use App\Services\SocialPublishing\Drivers\YouTubeDataDriver;
use App\Services\SocialPublishing\SocialPublisherManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NativeDriversTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Cliente $cliente;
    private Video $video;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Produtora Alpha',
            'slug' => 'produtora-alpha',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
        ]);

        $this->cliente = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente Beta',
            'email' => 'beta@cliente.com',
            'password' => bcrypt('password123'),
            'status' => 'active',
        ]);

        $production = Production::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'created_by' => $user->id,
            'title' => 'Produção de Teste',
            'status' => 'ready',
        ]);

        $this->video = Video::create([
            'tenant_id' => $this->tenant->id,
            'production_id' => $production->id,
            'title' => 'Vídeo Teaser',
            'status' => 'ready',
            'video_url' => 'https://storage.nume.app/videos/sample.mp4',
        ]);
    }

    public function test_manager_resolves_all_drivers_correctly(): void
    {
        /** @var SocialPublisherManager $manager */
        $manager = app(SocialPublisherManager::class);

        $this->assertInstanceOf(UploadPostDriver::class, $manager->driver('upload_post'));
        $this->assertInstanceOf(MetaGraphDriver::class, $manager->driver('meta_graph'));
        $this->assertInstanceOf(YouTubeDataDriver::class, $manager->driver('youtube_data'));
        $this->assertInstanceOf(TikTokDriver::class, $manager->driver('tiktok_native'));
    }

    public function test_pending_native_drivers_never_fake_a_publication(): void
    {
        Http::preventStrayRequests();

        foreach ([new MetaGraphDriver, new TikTokDriver, new YouTubeDataDriver] as $driver) {
            $publication = $driver->publish(
                cliente: $this->cliente,
                videoUrl: $this->video->video_url,
                title: 'Lançamento',
                platforms: $driver->supportedPlatforms(),
                video: $this->video
            );

            $this->assertSame('falhou', $publication->status, $driver->getName());
            $this->assertSame('driver_unavailable', $publication->last_error);
            $this->assertSame($driver->getName(), $publication->driver);
            $this->assertSame($this->tenant->id, $publication->tenant_id);
            $this->assertSame('falhou', $driver->refreshStatus($publication)->status);
        }

        // O vídeo não é marcado como publicado por um envio que não aconteceu.
        $this->assertSame('ready', $this->video->fresh()->status);
    }

    public function test_pending_native_drivers_do_not_claim_connected_accounts(): void
    {
        foreach ([new MetaGraphDriver, new TikTokDriver, new YouTubeDataDriver] as $driver) {
            $profile = $driver->syncConnectionStatus($this->cliente);

            $this->assertFalse((bool) $profile->connected, $driver->getName());
            $this->assertSame([], $profile->platforms_connected);
        }
    }

    public function test_youtube_driver_reflects_a_real_active_connection_and_keeps_other_networks(): void
    {
        (new YouTubeDataDriver)->ensureProfile($this->cliente)->update(['platforms_connected' => ['instagram']]);
        $this->cliente->refresh();

        SocialConnection::create([
            'tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'platform' => 'youtube', 'provider' => 'google',
            'external_account_id' => 'UC-canal-ficticio', 'access_token' => 'ya29.ficticio', 'refresh_token' => '1//ficticio',
        ]);

        $profile = (new YouTubeDataDriver)->syncConnectionStatus($this->cliente);
        $this->assertTrue((bool) $profile->connected);
        $this->assertEqualsCanonicalizing(['instagram', 'youtube'], $profile->platforms_connected);

        SocialConnection::forTenant($this->tenant->id)->update(['status' => SocialConnection::STATUS_REVOKED]);
        $this->assertSame(['instagram'], (new YouTubeDataDriver)->syncConnectionStatus($this->cliente->fresh())->platforms_connected);
    }

    public function test_youtube_connect_url_points_to_the_own_oauth_flow(): void
    {
        $this->assertSame(route('portal.social.oauth.redirect', 'youtube'), (new YouTubeDataDriver)->generateConnectUrl($this->cliente));
    }
}
