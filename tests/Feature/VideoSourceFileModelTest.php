<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Production;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Vídeo hospedado na NUME: colunas, configuração e ciclo de vida do arquivo. Dados fictícios.
 */
class VideoSourceFileModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_videos_table_has_source_columns_and_nullable_video_url(): void
    {
        foreach (['source_disk', 'source_path', 'source_mime', 'source_size', 'source_width', 'source_height', 'source_sha256'] as $column) {
            $this->assertTrue(Schema::hasColumn('videos', $column), "Coluna {$column} ausente.");
        }

        $video = $this->video(['video_url' => null, 'source_disk' => 'videos', 'source_path' => '1/arquivo.mp4']);

        $this->assertNull($video->fresh()->video_url);
        $this->assertSame('direct', $video->fresh()->video_type);
        $this->assertNull($video->fresh()->embed_url);
    }

    public function test_social_video_config_defaults(): void
    {
        $this->assertSame('videos', config('social.video.disk'));
        $this->assertSame(512, config('social.video.max_upload_mb'));
        $this->assertSame(['video/mp4', 'video/quicktime'], config('social.video.allowed_mimes'));

        // O disco é privado: nunca servido direto pelo servidor web.
        $this->assertSame('private', config('filesystems.disks.videos.visibility'));
        $this->assertSame(storage_path('app/private/videos'), config('filesystems.disks.videos.root'));
    }

    public function test_source_file_helpers_and_cleanup_on_delete(): void
    {
        Storage::fake('videos');
        Storage::disk('videos')->put('1/arquivo.mp4', 'conteudo-ficticio');

        $video = $this->video(['source_disk' => 'videos', 'source_path' => '1/arquivo.mp4']);

        $this->assertTrue($video->hasSourceFile());
        $this->assertTrue($video->sourceFileExists());

        $video->delete();

        Storage::disk('videos')->assertMissing('1/arquivo.mp4');
    }

    public function test_video_with_only_a_link_has_no_source_file(): void
    {
        $video = $this->video(['video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']);

        $this->assertFalse($video->hasSourceFile());
        $this->assertFalse($video->sourceFileExists());
        $this->assertSame('youtube', $video->video_type);

        // Excluir um vídeo sem arquivo não tenta apagar nada.
        $video->delete();
        $this->assertDatabaseMissing('videos', ['id' => $video->id]);
    }

    private function video(array $attributes = []): Video
    {
        $tenant = Tenant::first() ?? Tenant::create(['name' => 'Produtora Aurora', 'slug' => 'produtora-aurora', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        $cliente = Cliente::create(['tenant_id' => $tenant->id, 'name' => 'Cliente Ficticio', 'email' => 'cliente'.uniqid().'@ficticio.test', 'password' => bcrypt('secret123')]);
        $staff = User::create(['tenant_id' => $tenant->id, 'name' => 'Staff Ficticio', 'email' => 'staff'.uniqid().'@ficticio.test', 'password' => 'secret12345', 'role' => 'admin']);
        $production = Production::create(['tenant_id' => $tenant->id, 'cliente_id' => $cliente->id, 'title' => 'Producao Ficticia', 'status' => 'draft', 'created_by' => $staff->id]);

        return Video::create(array_merge([
            'tenant_id' => $tenant->id,
            'production_id' => $production->id,
            'title' => 'Video Ficticio',
            'status' => 'ready',
        ], $attributes));
    }
}
