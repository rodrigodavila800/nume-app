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
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * Reels no Instagram pelo app próprio. Arquivo, tokens e conta fictícios; Graph API e rupload simulados.
 */
class InstagramReelsPublishTest extends TestCase
{
    use RefreshDatabase;

    private const GRAPH = 'https://graph.facebook.com/v26.0';

    private const CONTENT = 'conteudo-ficticio-de-video';

    private const RUPLOAD = 'https://rupload.facebook.com/ig-api-upload/v26.0/1790001';

    private Tenant $tenant;

    private Cliente $cliente;

    private Video $video;

    private SocialConnection $connection;

    private array $logs = [];

    /** @var list<array{headers: array, body: string}> */
    private array $uploads = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('videos');
        Http::preventStrayRequests();
        Sleep::fake();
        config([
            'services.meta.client_id' => 'app-meta-ficticio',
            'services.meta.client_secret' => 'segredo-meta-ficticio',
            'social.meta.graph_version' => 'v26.0',
            'social.meta.poll_attempts' => 3,
        ]);
        Event::listen(MessageLogged::class, function (MessageLogged $event) {
            $this->logs[] = $event->message.json_encode($event->context);
        });

        $this->tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active']);
        $staff = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Staff', 'email' => 'staff@ficticio.test', 'password' => 'secret12345', 'role' => 'admin']);
        $this->cliente = Cliente::create(['tenant_id' => $this->tenant->id, 'name' => 'Cliente A', 'email' => 'a@ficticio.test', 'password' => bcrypt('secret123')]);
        $production = Production::create(['tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'title' => 'Campanha', 'status' => 'ready', 'created_by' => $staff->id]);

        $path = $this->tenant->id.'/reel.mp4';
        Storage::disk('videos')->put($path, self::CONTENT);
        $this->video = Video::create([
            'tenant_id' => $this->tenant->id, 'production_id' => $production->id, 'title' => 'Corte Final', 'status' => 'ready',
            'source_disk' => 'videos', 'source_path' => $path, 'source_mime' => 'video/mp4', 'source_size' => strlen(self::CONTENT),
            'sofi_seo_description' => 'Sorriso novo em 3 passos', 'sofi_hashtags' => ['saude bucal', '#clinica', 'clinica'],
        ]);

        $this->connection = SocialConnection::create([
            'tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'platform' => 'instagram', 'provider' => 'meta',
            'external_account_id' => '178414', 'handle' => '@clinicaficticia', 'access_token' => 'token-pagina-ficticio',
        ]);
    }

    public function test_uploads_the_file_waits_processing_and_publishes_the_reel(): void
    {
        $this->fakeMeta(statuses: ['IN_PROGRESS', 'FINISHED']);
        $publication = $this->publication();

        $this->runJob($publication)->assertNotReleased();

        $publication->refresh();
        $this->assertSame('publicado', $publication->status);
        $this->assertSame('1795555', $publication->external_id);
        $this->assertSame('https://www.instagram.com/reel/ABC123/', $publication->resultado['url']);
        $this->assertSame('instagram_native', $publication->resultado['driver']);

        Http::assertSent(fn (HttpRequest $request) => $request->url() === self::GRAPH.'/178414/media?appsecret_proof='.hash_hmac('sha256', 'token-pagina-ficticio', 'segredo-meta-ficticio')
            && $request['media_type'] === 'REELS'
            && $request['upload_type'] === 'resumable'
            && $request['caption'] === "Lancamento\n\nSorriso novo em 3 passos\n\n#saudebucal #clinica"
            && $request->hasHeader('Authorization', 'Bearer token-pagina-ficticio'));

        $this->assertCount(1, $this->uploads);
        $this->assertSame(self::CONTENT, $this->uploads[0]['body']);
        $this->assertSame(['OAuth token-pagina-ficticio'], $this->uploads[0]['headers']['Authorization']);
        $this->assertSame([(string) strlen(self::CONTENT)], $this->uploads[0]['headers']['file_size']);
        $this->assertSame(['0'], $this->uploads[0]['headers']['offset']);

        Http::assertSent(fn (HttpRequest $request) => str_starts_with($request->url(), self::GRAPH.'/178414/media_publish') && $request['creation_id'] === '1790001');
        Sleep::assertSleptTimes(1);
        $this->assertNull(Cache::get('instagram-container:'.$publication->id));

        foreach ($this->logs as $line) {
            $this->assertStringNotContainsString('token-pagina', $line);
        }
    }

    public function test_slow_processing_is_retried_later_without_uploading_again(): void
    {
        $this->fakeMeta(statuses: ['IN_PROGRESS', 'IN_PROGRESS', 'IN_PROGRESS']);
        $publication = $this->publication();

        $this->runJob($publication)->assertReleased(delay: 60);
        $this->assertSame('processing', $publication->fresh()->last_error);
        $this->assertSame('1790001', Cache::get('instagram-container:'.$publication->id));

        Http::swap(new Factory);
        Http::preventStrayRequests();
        $this->fakeMeta(statuses: ['FINISHED']);

        $this->runJob($publication->fresh());

        $this->assertSame('publicado', $publication->fresh()->status);
        $this->assertCount(1, $this->uploads, 'o arquivo foi enviado uma única vez');
        Http::assertNotSent(fn (HttpRequest $request) => str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/178414/media'));
    }

    public function test_processing_error_is_a_definitive_failure(): void
    {
        $this->fakeMeta(statuses: ['ERROR']);

        $this->runJob($publication = $this->publication())->assertNotReleased();

        $this->assertSame('falhou', $publication->fresh()->status);
        $this->assertSame('media_processing_failed', $publication->fresh()->last_error);
        Http::assertNotSent(fn (HttpRequest $request) => str_contains($request->url(), 'media_publish'));
    }

    public function test_invalid_token_asks_to_reconnect(): void
    {
        Http::fake([self::GRAPH.'/178414/media*' => Http::response(['error' => ['code' => 190, 'error_subcode' => 460, 'message' => 'Error validating access token: token-pagina-ficticio']], 400)]);

        $this->runJob($publication = $this->publication())->assertNotReleased();

        $this->assertSame('reconnect_required', $publication->fresh()->last_error);
        $this->assertSame(SocialConnection::STATUS_EXPIRED, $this->connection->fresh()->status);
        $this->assertSame('meta_190_460', $this->connection->fresh()->last_error);
        foreach ($this->logs as $line) {
            $this->assertStringNotContainsString('token-pagina', $line);
        }
    }

    public function test_daily_post_limit_is_definitive_and_rate_limit_is_retried(): void
    {
        Http::fake([self::GRAPH.'/178414/media*' => Http::sequence()
            ->push(['error' => ['code' => 9, 'error_subcode' => 2207042]], 400)
            ->push(['error' => ['code' => 4]], 400)]);

        $this->runJob($limite = $this->publication())->assertNotReleased();
        $this->assertSame('publish_limit_reached', $limite->fresh()->last_error);

        $this->runJob($taxa = $this->publication())->assertReleased(delay: 60);
        $this->assertSame('rate_limited', $taxa->fresh()->last_error);
    }

    public function test_upload_host_outside_meta_is_refused(): void
    {
        Http::fake([self::GRAPH.'/178414/media*' => Http::response(['id' => '1790001', 'uri' => 'https://atacante.test/captura'])]);

        $this->runJob($publication = $this->publication())->assertReleased();

        $this->assertSame('upload_init_failed', $publication->fresh()->last_error);
        Http::assertNotSent(fn (HttpRequest $request) => str_contains($request->url(), 'atacante.test'));
    }

    public function test_publication_of_another_network_connection_is_refused(): void
    {
        $this->connection->update(['platform' => 'facebook']);

        $this->runJob($publication = $this->publication())->assertNotReleased();

        $this->assertSame('not_connected', $publication->fresh()->last_error);
        Http::assertNothingSent();
    }

    private function publication(): VideoPublication
    {
        return VideoPublication::create([
            'tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'video_id' => $this->video->id,
            'driver' => 'instagram', 'platform' => 'instagram', 'social_connection_id' => $this->connection->id,
            'titulo' => 'Lancamento', 'plataformas' => ['instagram'], 'status' => 'pendente',
        ]);
    }

    private function runJob(VideoPublication $publication): PublishVideoToPlatform
    {
        $job = (new PublishVideoToPlatform($publication->id, $publication->tenant_id))->withFakeQueueInteractions();
        $job->handle(app(NativePublisherRegistry::class));

        return $job;
    }

    private function fakeMeta(array $statuses): void
    {
        $sequence = Http::sequence();
        foreach ($statuses as $status) {
            $sequence->push(['status_code' => $status, 'id' => '1790001']);
        }

        Http::fake([
            self::GRAPH.'/178414/media_publish*' => Http::response(['id' => '1795555']),
            self::GRAPH.'/178414/media?*' => Http::response(['id' => '1790001', 'uri' => self::RUPLOAD]),
            self::RUPLOAD => function (HttpRequest $request) {
                $this->uploads[] = ['headers' => $request->headers(), 'body' => $request->body()];

                return Http::response(['success' => true]);
            },
            self::GRAPH.'/1790001*' => $sequence,
            self::GRAPH.'/1795555*' => Http::response(['permalink' => 'https://www.instagram.com/reel/ABC123/', 'id' => '1795555']),
        ]);
    }
}
