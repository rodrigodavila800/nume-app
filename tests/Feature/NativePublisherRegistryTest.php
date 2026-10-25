<?php

namespace Tests\Feature;

use App\Jobs\PublishVideoToPlatform;
use App\Models\Cliente;
use App\Models\Production;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoPublication;
use App\Services\SocialPublishing\NativePublisher;
use App\Services\SocialPublishing\NativePublisherRegistry;
use App\Services\SocialPublishing\PublishException;
use App\Services\SocialPublishing\YouTube\YouTubeUploader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * O job de envio nativo é o mesmo para todas as redes: o publicador vem do driver da publicação.
 */
class NativePublisherRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_registry_resolves_only_configured_publishers(): void
    {
        $registry = app(NativePublisherRegistry::class);

        $this->assertInstanceOf(YouTubeUploader::class, $registry->for('youtube'));
        $this->assertNull($registry->for('rede-inexistente'));
        $this->assertNull($registry->for('rede-inexistente'));
        $this->assertFalse($registry->supports('rede-inexistente'));

        config(['social.native_publishers.rede-inexistente' => \stdClass::class]);
        $this->assertNull($registry->for('rede-inexistente'), 'classe que não implementa NativePublisher é ignorada');
    }

    public function test_job_uses_the_publisher_of_the_publication_driver(): void
    {
        config(['social.native_publishers.fakegram' => FakeGramPublisher::class]);
        $publication = $this->publication('fakegram');

        (new PublishVideoToPlatform($publication->id, $publication->tenant_id))->withFakeQueueInteractions()
            ->handle(app(NativePublisherRegistry::class));

        $publication->refresh();
        $this->assertSame('publicado', $publication->status);
        $this->assertSame('post-123', $publication->external_id);
        $this->assertSame('fakegram_native', $publication->resultado['driver']);
        $this->assertSame('https://fakegram.test/post-123', $publication->resultado['url']);
        $this->assertSame('fakegram', $publication->video->fresh()->platform);
    }

    public function test_job_failure_is_recorded_with_the_driver_of_the_network(): void
    {
        config(['social.native_publishers.fakegram' => FakeGramPublisher::class]);
        FakeGramPublisher::$fail = true;
        $publication = $this->publication('fakegram');

        (new PublishVideoToPlatform($publication->id, $publication->tenant_id))->withFakeQueueInteractions()
            ->handle(app(NativePublisherRegistry::class));

        $publication->refresh();
        $this->assertSame('falhou', $publication->status);
        $this->assertSame('rede_recusou', $publication->last_error);
        $this->assertSame('fakegram_native', $publication->resultado['driver']);
        FakeGramPublisher::$fail = false;
    }

    public function test_job_ignores_publications_of_networks_without_a_publisher(): void
    {
        $publication = $this->publication('rede-inexistente');

        (new PublishVideoToPlatform($publication->id, $publication->tenant_id))->withFakeQueueInteractions()
            ->handle(app(NativePublisherRegistry::class));

        $this->assertSame('pendente', $publication->fresh()->status);
        $this->assertSame(0, $publication->fresh()->attempts);
    }

    private function publication(string $driver): VideoPublication
    {
        $tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active']);
        $staff = User::create(['tenant_id' => $tenant->id, 'name' => 'Staff', 'email' => 'staff@ficticio.test', 'password' => 'secret12345', 'role' => 'admin']);
        $cliente = Cliente::create(['tenant_id' => $tenant->id, 'name' => 'Cliente A', 'email' => 'a@ficticio.test', 'password' => bcrypt('secret123')]);
        $production = Production::create(['tenant_id' => $tenant->id, 'cliente_id' => $cliente->id, 'title' => 'Campanha', 'status' => 'ready', 'created_by' => $staff->id]);
        $video = Video::create(['tenant_id' => $tenant->id, 'production_id' => $production->id, 'title' => 'Corte', 'status' => 'ready', 'video_url' => 'https://cdn.ficticio.test/v.mp4']);

        return VideoPublication::create([
            'tenant_id' => $tenant->id, 'cliente_id' => $cliente->id, 'video_id' => $video->id,
            'driver' => $driver, 'platform' => $driver, 'titulo' => 'Envio', 'plataformas' => [$driver], 'status' => 'pendente',
        ]);
    }
}

class FakeGramPublisher implements NativePublisher
{
    public static bool $fail = false;

    public function publish(VideoPublication $publication): array
    {
        if (self::$fail) {
            throw new PublishException('rede_recusou');
        }

        return ['id' => 'post-123', 'url' => 'https://fakegram.test/post-123'];
    }
}
