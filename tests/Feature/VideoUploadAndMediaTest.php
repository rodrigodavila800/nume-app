<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\PackPurchase;
use App\Models\Production;
use App\Models\SocialProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Video;
use App\Services\UploadPost\SocialDistributionService;
use App\Services\UploadPost\UploadPostClient;
use App\Services\Video\VideoMetadata;
use App\Services\Video\VideoUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Upload e entrega do vídeo hospedado na NUME. Dados fictícios, disco falso e sem rede.
 */
class VideoUploadAndMediaTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $staffA;

    private User $staffB;

    private Cliente $clienteA;

    private Cliente $clienteB;

    private Production $production;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('videos');
        Http::preventStrayRequests();

        // Sem ffprobe real: metadados fixos e determinísticos.
        $this->app->instance(VideoMetadata::class, new class extends VideoMetadata
        {
            public function read(string $absolutePath): array
            {
                return ['width' => 1080, 'height' => 1920, 'duration' => 30];
            }
        });

        $this->tenantA = Tenant::create(['name' => 'Produtora Aurora', 'slug' => 'produtora-aurora', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        $this->tenantB = Tenant::create(['name' => 'Produtora Boreal', 'slug' => 'produtora-boreal', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        $this->staffA = User::create(['tenant_id' => $this->tenantA->id, 'name' => 'Staff A', 'email' => 'staff-a@ficticio.test', 'password' => 'secret12345', 'role' => 'admin']);
        $this->staffB = User::create(['tenant_id' => $this->tenantB->id, 'name' => 'Staff B', 'email' => 'staff-b@ficticio.test', 'password' => 'secret12345', 'role' => 'admin']);
        $this->clienteA = Cliente::create(['tenant_id' => $this->tenantA->id, 'name' => 'Cliente A', 'email' => 'cliente-a@ficticio.test', 'password' => bcrypt('secret123')]);
        $this->clienteB = Cliente::create(['tenant_id' => $this->tenantB->id, 'name' => 'Cliente B', 'email' => 'cliente-b@ficticio.test', 'password' => bcrypt('secret123')]);
        $this->production = Production::create(['tenant_id' => $this->tenantA->id, 'cliente_id' => $this->clienteA->id, 'title' => 'Producao Ficticia', 'status' => 'draft', 'created_by' => $this->staffA->id]);
    }

    // ---------------------------------------------------------------- upload

    public function test_staff_uploads_an_mp4_and_the_file_is_stored_privately(): void
    {
        $file = $this->realMp4('clip.mp4');

        $this->actingAs($this->staffA)
            ->post(route('videos.store', $this->production), ['title' => 'Corte Final', 'video_file' => $file])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $video = Video::withoutGlobalScope('tenant')->sole();

        $this->assertNull($video->video_url);
        $this->assertSame('videos', $video->source_disk);
        $this->assertMatchesRegularExpression('#^'.$this->tenantA->id.'/[0-9a-f-]{36}\.mp4$#', $video->source_path);
        $this->assertSame('video/mp4', $video->source_mime);
        $this->assertSame(hash_file('sha256', $file->getRealPath()), $video->source_sha256);
        $this->assertSame([1080, 1920, 30], [$video->source_width, $video->source_height, $video->duration_seconds]);
        Storage::disk('videos')->assertExists($video->source_path);
    }

    public function test_a_disguised_text_file_is_rejected_by_content_not_by_name(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'vid');
        file_put_contents($path, '<?php echo "nao sou um video"; ?>');
        $evil = new UploadedFile($path, 'clip.mp4', 'video/mp4', null, true);

        $this->actingAs($this->staffA)
            ->post(route('videos.store', $this->production), ['title' => 'Falso', 'video_file' => $evil])
            ->assertSessionHasErrors('video_file');

        $this->assertSame(0, Video::withoutGlobalScope('tenant')->count());
        $this->assertSame([], Storage::disk('videos')->allFiles());
    }

    public function test_upload_service_rejects_a_disguised_file_on_its_own(): void
    {
        // Segunda camada: mesmo sem a validação do formulário, o serviço confere o conteúdo.
        $path = tempnam(sys_get_temp_dir(), 'vid');
        file_put_contents($path, 'texto simples, nao e video');

        $this->expectException(\DomainException::class);

        app(VideoUploadService::class)
            ->store(new UploadedFile($path, 'clip.mp4', 'video/mp4', null, true), $this->tenantA->id);
    }

    public function test_file_above_the_configured_limit_is_rejected(): void
    {
        config(['social.video.max_upload_mb' => 1]);

        $this->actingAs($this->staffA)
            ->post(route('videos.store', $this->production), ['title' => 'Grande', 'video_file' => UploadedFile::fake()->create('big.mp4', 2048, 'video/mp4')])
            ->assertSessionHasErrors(['video_file' => 'O vídeo excede o tamanho máximo de 1 MB.']);

        $this->assertSame(0, Video::withoutGlobalScope('tenant')->count());
    }

    public function test_a_video_needs_either_a_link_or_a_file(): void
    {
        $this->actingAs($this->staffA)
            ->post(route('videos.store', $this->production), ['title' => 'Sem nada'])
            ->assertSessionHasErrors(['video_url' => 'Informe o link do vídeo ou envie o arquivo.']);
    }

    public function test_a_video_that_is_only_a_link_still_works(): void
    {
        $this->actingAs($this->staffA)
            ->post(route('videos.store', $this->production), ['title' => 'Link', 'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'])
            ->assertSessionHasNoErrors();

        $video = Video::withoutGlobalScope('tenant')->sole();
        $this->assertFalse($video->hasSourceFile());
        $this->assertSame('youtube', $video->video_type);
        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $video->playbackUrl());
    }

    public function test_replacing_the_file_deletes_the_previous_one(): void
    {
        $video = $this->videoWithFile();
        $old = $video->source_path;

        $this->actingAs($this->staffA)
            ->put(route('videos.update', [$this->production, $video]), ['title' => 'Novo corte', 'video_file' => $this->realMp4('novo.mp4')])
            ->assertSessionHasNoErrors();

        $video->refresh();
        $this->assertNotSame($old, $video->source_path);
        Storage::disk('videos')->assertMissing($old);
        Storage::disk('videos')->assertExists($video->source_path);
    }

    public function test_removing_the_file_requires_a_link_to_remain(): void
    {
        $video = $this->videoWithFile();

        // Sem link restante, não dá para remover o arquivo.
        $this->actingAs($this->staffA)
            ->put(route('videos.update', [$this->production, $video]), ['title' => 'Sem arquivo', 'remove_video_file' => 1])
            ->assertSessionHasErrors('video_url');
        Storage::disk('videos')->assertExists($video->source_path);

        // Com um link, remove o arquivo do disco e limpa o registro.
        $path = $video->source_path;
        $this->actingAs($this->staffA)
            ->put(route('videos.update', [$this->production, $video]), ['title' => 'So link', 'remove_video_file' => 1, 'video_url' => 'https://vimeo.com/123456789'])
            ->assertSessionHasNoErrors();

        $this->assertFalse($video->fresh()->hasSourceFile());
        Storage::disk('videos')->assertMissing($path);
    }

    // ----------------------------------------------------------------- media

    public function test_signed_url_streams_the_video_and_honours_range_requests(): void
    {
        $video = $this->videoWithFile('0123456789ABCDEFGHIJ');

        $this->get($video->playbackUrl())
            ->assertOk()
            ->assertHeader('Content-Type', 'video/mp4')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->get($video->playbackUrl(), ['Range' => 'bytes=0-9'])
            ->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 0-9/20');
    }

    public function test_unsigned_expired_or_tampered_urls_are_refused(): void
    {
        $video = $this->videoWithFile();

        $this->get(route('videos.media', $video))->assertForbidden();

        $expired = URL::temporarySignedRoute('videos.media', now()->subMinute(), ['video' => $video->id]);
        $this->get($expired)->assertForbidden();

        $tampered = str_replace('/media/videos/'.$video->id, '/media/videos/'.($video->id + 1), $video->playbackUrl());
        $this->get($tampered)->assertForbidden();
    }

    public function test_missing_file_returns_404(): void
    {
        $video = $this->videoWithFile();
        Storage::disk('videos')->delete($video->source_path);

        $this->get($video->playbackUrl())->assertNotFound();
    }

    public function test_download_rules_staff_owner_stranger_and_escrow(): void
    {
        $video = $this->videoWithFile();
        $download = $video->downloadUrl();

        // Sem login: a assinatura não basta para baixar.
        $this->get($download)->assertForbidden();

        // Equipe do mesmo tenant: sempre pode.
        $this->actingAs($this->staffA)->get($download)->assertOk()->assertHeader('Content-Disposition');
        auth()->forgetGuards();

        // Equipe de outro tenant e cliente de outro tenant: não.
        $this->actingAs($this->staffB)->get($download)->assertForbidden();
        auth()->forgetGuards();
        $this->actingAs($this->clienteB, 'cliente')->get($download)->assertForbidden();
        auth()->forgetGuards();

        // Cliente dono, saldo quitado: pode.
        $this->actingAs($this->clienteA, 'cliente')->get($download)->assertOk();
        auth()->forgetGuards();

        // Cliente dono com o ativo em custódia (50/50 com saldo pendente): download bloqueado, reprodução liberada.
        $this->lockAsset();
        $this->actingAs($this->clienteA, 'cliente')->get($download)->assertForbidden();
        $this->get($video->fresh()->playbackUrl())->assertOk();
    }

    // ------------------------------------------------------ portal distribute

    public function test_portal_distribution_of_a_hosted_video_uses_a_server_signed_url_and_ignores_the_typed_one(): void
    {
        config(['upload_post.api_key' => 'chave-de-teste', 'upload_post.base_url' => 'https://api.upload-post.com/api']);
        $this->app->forgetInstance(UploadPostClient::class);
        $this->app->forgetInstance(SocialDistributionService::class);
        Http::fake(['api.upload-post.com/*' => Http::response(['request_id' => 'req_ficticio', 'success' => true])]);

        $video = $this->videoWithFile();
        $this->connectedClienteWithQuota();

        $this->actingAs($this->clienteA, 'cliente')
            ->postJson(route('portal.videos.distribute', $video), [
                'video_url' => 'http://169.254.169.254/latest/meta-data/',
                'titulo' => 'Meu video',
                'plataformas' => ['youtube'],
            ])
            ->assertStatus(202);

        Http::assertSent(fn ($request) => str_contains($request->body(), '/media/videos/'.$video->id)
            && str_contains($request->body(), 'signature=')
            && ! str_contains($request->body(), '169.254.169.254'));
    }

    public function test_portal_distribution_of_a_hosted_video_is_blocked_while_the_asset_is_in_escrow(): void
    {
        $video = $this->videoWithFile();
        $this->connectedClienteWithQuota();
        $this->lockAsset();

        $this->actingAs($this->clienteA, 'cliente')
            ->postJson(route('portal.videos.distribute', $video), ['titulo' => 'Meu video', 'plataformas' => ['youtube']])
            ->assertForbidden();

        Http::assertNothingSent();
    }

    // ---------------------------------------------------------------- helpers

    private function realMp4(string $name): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'mp4');
        // Cabeçalho "ftyp" de um MP4: o conteúdo é o que define o tipo, não o nome.
        file_put_contents($path, "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom".str_repeat("\x00", 2048));

        return new UploadedFile($path, $name, 'video/mp4', null, true);
    }

    private function videoWithFile(string $content = 'conteudo-ficticio'): Video
    {
        $path = $this->tenantA->id.'/'.uniqid().'.mp4';
        Storage::disk('videos')->put($path, $content);

        return Video::create([
            'tenant_id' => $this->tenantA->id,
            'production_id' => $this->production->id,
            'title' => 'Video Ficticio',
            'status' => 'ready',
            'source_disk' => 'videos',
            'source_path' => $path,
            'source_mime' => 'video/mp4',
            'source_size' => strlen($content),
        ]);
    }

    private function lockAsset(): void
    {
        $purchase = PackPurchase::create([
            'tenant_id' => $this->tenantA->id,
            'cliente_id' => $this->clienteA->id,
            'status' => 'paid',
            'amount' => 500,
            'video_quantity' => 5,
            'videos_used' => 0,
            'billing_mode' => 'split_50_50',
            'balance_amount' => 500,
            'balance_status' => 'pending',
        ]);

        $this->production->update(['pack_purchase_id' => $purchase->id]);
    }

    private function connectedClienteWithQuota(): void
    {
        SocialProfile::create([
            'tenant_id' => $this->tenantA->id,
            'cliente_id' => $this->clienteA->id,
            'upload_post_username' => (string) Str::uuid(),
            'connected' => true,
            'platforms_connected' => ['youtube'],
        ]);

        PackPurchase::create([
            'tenant_id' => $this->tenantA->id,
            'cliente_id' => $this->clienteA->id,
            'status' => 'paid',
            'amount' => 100,
            'video_quantity' => 10,
            'videos_used' => 0,
        ]);
    }
}
