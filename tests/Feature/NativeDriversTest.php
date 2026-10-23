<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Production;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Video;
use App\Services\SocialPublishing\Drivers\MetaGraphDriver;
use App\Services\SocialPublishing\Drivers\TikTokDriver;
use App\Services\SocialPublishing\Drivers\UploadPostDriver;
use App\Services\SocialPublishing\Drivers\YouTubeDataDriver;
use App\Services\SocialPublishing\SocialPublisherManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_meta_graph_driver_publishes_reels_and_refreshes(): void
    {
        $driver = new MetaGraphDriver();
        $publication = $driver->publish(
            cliente: $this->cliente,
            videoUrl: $this->video->video_url,
            title: 'Lançamento Instagram Reels #viral',
            platforms: ['instagram'],
            video: $this->video
        );

        $this->assertDatabaseHas('video_publications', [
            'id' => $publication->id,
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
        ]);
        $this->assertEquals(['instagram'], $publication->plataformas);

        // Atualiza status (refreshStatus)
        $refreshed = $driver->refreshStatus($publication);
        $this->assertNotNull($refreshed->status);
    }

    public function test_youtube_data_driver_publishes_shorts(): void
    {
        $driver = new YouTubeDataDriver();
        $publication = $driver->publish(
            cliente: $this->cliente,
            videoUrl: $this->video->video_url,
            title: 'Shorts de Alta Retenção Sofi',
            platforms: ['youtube'],
            video: $this->video
        );

        $this->assertDatabaseHas('video_publications', [
            'id' => $publication->id,
            'titulo' => 'Shorts de Alta Retenção Sofi',
        ]);

        $refreshed = $driver->refreshStatus($publication);
        $this->assertEquals('publicado', $refreshed->status);
    }

    public function test_tiktok_driver_publishes_video(): void
    {
        $driver = new TikTokDriver();
        $publication = $driver->publish(
            cliente: $this->cliente,
            videoUrl: $this->video->video_url,
            title: 'Trend TikTok Vídeo',
            platforms: ['tiktok'],
            video: $this->video
        );

        $this->assertDatabaseHas('video_publications', [
            'id' => $publication->id,
            'plataformas' => json_encode(['tiktok']),
        ]);

        $refreshed = $driver->refreshStatus($publication);
        $this->assertEquals('publicado', $refreshed->status);
    }
}
