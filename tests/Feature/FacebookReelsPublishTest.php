<?php

namespace Tests\Feature;

use App\Jobs\PublishVideoToPlatform;
use App\Models\Cliente;
use App\Models\Production;
use App\Models\SocialConnection;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoPublication;
use App\Services\SocialPublishing\NativePublisherRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Reels na Página do Facebook pelo app próprio. Arquivo, tokens e Página fictícios; Graph API e rupload simulados.
 */
class FacebookReelsPublishTest extends TestCase
{
    use RefreshDatabase;

    private const GRAPH = 'https://graph.facebook.com/v26.0';

    private const CONTENT = 'conteudo-ficticio-de-video';

    private const RUPLOAD = 'https://rupload.facebook.com/video-upload/v26.0/880001';

    private Tenant $tenant;

    private Cliente $cliente;

    private Video $video;

    private SocialConnection $connection;

    /** @var list<array{headers: array, body: string}> */
    private array $uploads = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('videos');
        Http::preventStrayRequests();
        config([
            'services.meta.client_id' => 'app-meta-ficticio',
            'services.meta.client_secret' => 'segredo-meta-ficticio',
            'social.meta.graph_version' => 'v26.0',
        ]);

        $this->tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active']);
        $staff = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Staff', 'email' => 'staff@ficticio.test', 'password' => 'secret12345', 'role' => 'admin']);
        $this->cliente = Cliente::create(['tenant_id' => $this->tenant->id, 'name' => 'Cliente A', 'email' => 'a@ficticio.test', 'password' => bcrypt('secret123')]);
        $production = Production::create(['tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'title' => 'Campanha', 'status' => 'ready', 'created_by' => $staff->id]);

        $path = $this->tenant->id.'/reel.mp4';
        Storage::disk('videos')->put($path, self::CONTENT);
        $this->video = Video::create([
            'tenant_id' => $this->tenant->id, 'production_id' => $production->id, 'title' => 'Corte Final', 'status' => 'ready',
            'source_disk' => 'videos', 'source_path' => $path, 'source_mime' => 'video/mp4', 'source_size' => strlen(self::CONTENT),
            'source_width' => 1080, 'source_height' => 1920, 'duration_seconds' => 30,
            'sofi_hashtags' => ['clinica'],
        ]);

        $this->connection = SocialConnection::create([
            'tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'platform' => 'facebook', 'provider' => 'meta',
            'external_account_id' => '555', 'display_name' => 'Clinica Ficticia', 'access_token' => 'token-pagina-ficticio',
        ]);
    }

    public function test_starts_uploads_and_publishes_the_reel_on_the_page(): void
    {
        $this->fakeMeta();

        $this->runJob($publication = $this->publication())->assertNotReleased();

        $publication->refresh();
        $this->assertSame('publicado', $publication->status);
        $this->assertSame('880001', $publication->external_id);
        $this->assertSame('https://www.facebook.com/reel/880001', $publication->resultado['url']);

        Http::assertSent(fn (HttpRequest $request) => str_starts_with($request->url(), self::GRAPH.'/555/video_reels') && $request['upload_phase'] === 'start'
            && $request->hasHeader('Authorization', 'Bearer token-pagina-ficticio'));
        Http::assertSent(fn (HttpRequest $request) => str_starts_with($request->url(), self::GRAPH.'/555/video_reels') && $request['upload_phase'] === 'finish'
            && $request['video_id'] === '880001' && $request['video_state'] === 'PUBLISHED' && $request['description'] === "Lancamento\n\n#clinica");

        $this->assertCount(1, $this->uploads);
        $this->assertSame(self::CONTENT, $this->uploads[0]['body']);
        $this->assertSame(['OAuth token-pagina-ficticio'], $this->uploads[0]['headers']['Authorization']);
    }

    public function test_failed_finish_is_retried_without_uploading_again(): void
    {
        $this->fakeMeta(finish: Http::response(['error' => ['code' => 2]], 500));

        $this->runJob($publication = $this->publication())->assertReleased(delay: 60);
        $this->assertSame('880001', Cache::get('facebook-reel:'.$publication->id));

        Http::swap(new HttpFactory);
        Http::preventStrayRequests();
        $this->fakeMeta();

        $this->runJob($publication->fresh());

        $this->assertSame('publicado', $publication->fresh()->status);
        $this->assertCount(1, $this->uploads, 'o arquivo foi enviado uma única vez');
        $this->assertNull(Cache::get('facebook-reel:'.$publication->id));
    }

    public function test_video_outside_reel_specs_is_refused_before_calling_meta(): void
    {
        $this->video->update(['source_width' => 1920, 'source_height' => 1080]);
        $this->runJob($horizontal = $this->publication());
        $this->assertSame('reel_format_invalid', $horizontal->fresh()->last_error);

        $this->video->update(['source_width' => 1080, 'source_height' => 1920, 'duration_seconds' => 120]);
        $this->runJob($longo = $this->publication());
        $this->assertSame('reel_duration_invalid', $longo->fresh()->last_error);

        Http::assertNothingSent();
    }

    public function test_unknown_dimensions_do_not_block_the_upload(): void
    {
        $this->video->update(['source_width' => null, 'source_height' => null, 'duration_seconds' => null]);
        $this->fakeMeta();

        $this->runJob($publication = $this->publication());

        $this->assertSame('publicado', $publication->fresh()->status);
    }

    public function test_revoked_page_permission_asks_to_reconnect(): void
    {
        Http::fake([self::GRAPH.'/555/video_reels*' => Http::response(['error' => ['code' => 200, 'message' => 'Permissions error']], 403)]);

        $this->runJob($publication = $this->publication())->assertNotReleased();

        $this->assertSame('reconnect_required', $publication->fresh()->last_error);
        $this->assertSame(SocialConnection::STATUS_EXPIRED, $this->connection->fresh()->status);
    }

    private function publication(): VideoPublication
    {
        return VideoPublication::create([
            'tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'video_id' => $this->video->id,
            'driver' => 'facebook', 'platform' => 'facebook', 'social_connection_id' => $this->connection->id,
            'titulo' => 'Lancamento', 'plataformas' => ['facebook'], 'status' => 'pendente',
        ]);
    }

    private function runJob(VideoPublication $publication): PublishVideoToPlatform
    {
        $job = (new PublishVideoToPlatform($publication->id, $publication->tenant_id))->withFakeQueueInteractions();
        $job->handle(app(NativePublisherRegistry::class));

        return $job;
    }

    private function fakeMeta($finish = null): void
    {
        $finish ??= Http::response(['success' => true, 'post_id' => '555_999']);

        Http::fake([
            self::GRAPH.'/555/video_reels*' => function (HttpRequest $request) use ($finish) {
                return $request['upload_phase'] === 'start'
                    ? Http::response(['video_id' => '880001', 'upload_url' => self::RUPLOAD])
                    : $finish;
            },
            self::RUPLOAD => function (HttpRequest $request) {
                $this->uploads[] = ['headers' => $request->headers(), 'body' => $request->body()];

                return Http::response(['success' => true]);
            },
        ]);
    }
}
