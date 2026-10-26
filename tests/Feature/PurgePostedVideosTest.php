<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Production;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoPublication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Arquivo final apagado da hospedagem depois de postado. Disco falso; dados fictícios.
 */
class PurgePostedVideosTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Production $production;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('videos');

        $this->tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        $admin = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Admin', 'email' => 'admin@ficticio.test', 'password' => 'secret12345', 'role' => 'admin']);
        $this->cliente = Cliente::create(['tenant_id' => $this->tenant->id, 'name' => 'Ana', 'email' => 'ana@ficticio.test', 'password' => 'secret12345']);
        $this->production = Production::create(['tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'title' => 'Campanha', 'status' => 'ready', 'created_by' => $admin->id]);
    }

    private function hostedVideo(string $path, ?string $delivery = 'https://drive.google.com/drive/folders/entrega-ficticia'): Video
    {
        Storage::disk('videos')->put($path, 'conteudo-ficticio');

        return Video::create([
            'tenant_id' => $this->tenant->id, 'production_id' => $this->production->id, 'title' => 'Corte', 'status' => 'published',
            'source_disk' => 'videos', 'source_path' => $path, 'source_mime' => 'video/mp4', 'source_size' => 17, 'delivery_url' => $delivery,
        ]);
    }

    private function publication(Video $video, string $status, int $hoursAgo = 30): VideoPublication
    {
        $publication = VideoPublication::create([
            'tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'video_id' => $video->id,
            'driver' => 'youtube', 'platform' => 'youtube', 'titulo' => 'Post', 'plataformas' => ['youtube'], 'status' => $status,
        ]);
        VideoPublication::withoutGlobalScopes()->whereKey($publication->id)->update(['updated_at' => now()->subHours($hoursAgo), 'created_at' => now()->subHours($hoursAgo)]);

        return $publication;
    }

    public function test_posted_video_with_delivery_link_loses_the_hosted_file(): void
    {
        $video = $this->hostedVideo('a.mp4');
        $this->publication($video, 'publicado');

        $this->artisan('videos:purge-posted')->assertSuccessful();

        $video->refresh();
        Storage::disk('videos')->assertMissing('a.mp4');
        $this->assertNull($video->source_path);
        $this->assertNotNull($video->source_purged_at);
        $this->assertSame('https://drive.google.com/drive/folders/entrega-ficticia', $video->delivery_url);
    }

    public function test_files_are_kept_while_anything_is_pending_recent_or_without_delivery_link(): void
    {
        $pending = $this->hostedVideo('pendente.mp4');
        $this->publication($pending, 'publicado');
        $this->publication($pending, 'agendado');

        $recent = $this->hostedVideo('recente.mp4');
        $this->publication($recent, 'publicado', hoursAgo: 2);

        $noDelivery = $this->hostedVideo('sem-entrega.mp4', delivery: null);
        $this->publication($noDelivery, 'publicado');

        $neverPosted = $this->hostedVideo('nunca.mp4');
        $this->publication($neverPosted, 'falhou');

        $this->artisan('videos:purge-posted')->expectsOutputToContain('Postados sem link de entrega (mantidos): 1')->assertSuccessful();

        foreach (['pendente.mp4', 'recente.mp4', 'sem-entrega.mp4', 'nunca.mp4'] as $path) {
            Storage::disk('videos')->assertExists($path);
        }
    }

    public function test_dry_run_deletes_nothing(): void
    {
        $video = $this->hostedVideo('b.mp4');
        $this->publication($video, 'publicado');

        $this->artisan('videos:purge-posted', ['--dry-run' => true])->expectsOutputToContain('Seriam apagados: 1')->assertSuccessful();

        Storage::disk('videos')->assertExists('b.mp4');
        $this->assertNull($video->fresh()->source_purged_at);
    }
}
