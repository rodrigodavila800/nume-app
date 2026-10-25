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
use App\Services\SocialPublishing\LinkedIn\LinkedInVideoPublisher;
use App\Services\SocialPublishing\NativePublisherRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * Vídeo no perfil do LinkedIn pelo app próprio. Arquivo, tokens e membro fictícios; API simulada.
 */
class LinkedInVideoPublishTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://api.linkedin.com/rest';

    private const PART_1 = 'https://www.linkedin.com/dms-uploads/C5505AQ-ficticio/uploadedVideo?parte=1';

    private const PART_2 = 'https://www.linkedin.com/dms-uploads/C5505AQ-ficticio/uploadedVideo?parte=2';

    private const VIDEO_URN = 'urn:li:video:C5505AQ-ficticio';

    private string $content;

    private Tenant $tenant;

    private Cliente $cliente;

    private Video $video;

    private SocialConnection $connection;

    /** @var list<array{url: string, body: string}> */
    private array $uploads = [];

    private array $finalize = [];

    private array $post = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('videos');
        Http::preventStrayRequests();
        Sleep::fake();
        config(['social.linkedin.api_version' => '202610', 'social.meta.poll_attempts' => 3]);

        $this->content = str_repeat('a', 50000).str_repeat('b', 31920); // 81.920 bytes (mínimo do LinkedIn: 75 KB)

        $this->tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active']);
        $staff = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Staff', 'email' => 'staff@ficticio.test', 'password' => 'secret12345', 'role' => 'admin']);
        $this->cliente = Cliente::create(['tenant_id' => $this->tenant->id, 'name' => 'Cliente A', 'email' => 'a@ficticio.test', 'password' => bcrypt('secret123')]);
        $production = Production::create(['tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'title' => 'Campanha', 'status' => 'ready', 'created_by' => $staff->id]);

        Storage::disk('videos')->put($this->tenant->id.'/li.mp4', $this->content);
        $this->video = Video::create([
            'tenant_id' => $this->tenant->id, 'production_id' => $production->id, 'title' => 'Corte Final', 'status' => 'ready',
            'source_disk' => 'videos', 'source_path' => $this->tenant->id.'/li.mp4', 'source_mime' => 'video/mp4',
            'source_size' => strlen($this->content), 'duration_seconds' => 45,
            'sofi_seo_description' => 'Sorriso (novo) em 3 passos @clinica', 'sofi_hashtags' => ['odontologia'],
        ]);

        $this->connection = SocialConnection::create([
            'tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'platform' => 'linkedin', 'provider' => 'linkedin',
            'external_account_id' => 'membro-ficticio', 'display_name' => 'Ana Ficticia', 'access_token' => 'AQV.ficticio',
            'expires_at' => now()->addDays(50),
        ]);
    }

    public function test_uploads_the_parts_finalizes_waits_and_posts_on_the_feed(): void
    {
        $this->fakeLinkedIn(statuses: ['PROCESSING', 'AVAILABLE']);

        $this->runJob($publication = $this->publication())->assertNotReleased();

        $publication->refresh();
        $this->assertSame('publicado', $publication->status);
        $this->assertSame('urn:li:share:6844785523593134080', $publication->external_id);
        $this->assertSame('https://www.linkedin.com/feed/update/urn:li:share:6844785523593134080/', $publication->resultado['url']);

        Http::assertSent(fn (HttpRequest $request) => $request->url() === self::API.'/videos?action=initializeUpload'
            && $request['initializeUploadRequest']['owner'] === 'urn:li:person:membro-ficticio'
            && $request['initializeUploadRequest']['fileSizeBytes'] === 81920
            && $request->hasHeader('LinkedIn-Version', '202610')
            && $request->hasHeader('X-Restli-Protocol-Version', '2.0.0')
            && $request->hasHeader('Authorization', 'Bearer AQV.ficticio'));

        $this->assertSame([
            ['url' => self::PART_1, 'body' => substr($this->content, 0, 50000)],
            ['url' => self::PART_2, 'body' => substr($this->content, 50000)],
        ], $this->uploads);

        $this->assertSame(['etag-parte-1', 'etag-parte-2'], $this->finalize['finalizeUploadRequest']['uploadedPartIds']);
        $this->assertSame('token-sessao-ficticio', $this->finalize['finalizeUploadRequest']['uploadToken']);

        $this->assertSame('urn:li:person:membro-ficticio', $this->post['author']);
        $this->assertSame(self::VIDEO_URN, $this->post['content']['media']['id']);
        $this->assertSame('PUBLIC', $this->post['visibility']);
        $this->assertSame('MAIN_FEED', $this->post['distribution']['feedDistribution']);
        $this->assertSame("Lancamento\n\nSorriso \\(novo\\) em 3 passos \\@clinica\n\n#odontologia", $this->post['commentary']);
    }

    public function test_slow_processing_resumes_without_uploading_again(): void
    {
        $this->fakeLinkedIn(statuses: ['PROCESSING', 'PROCESSING', 'PROCESSING']);
        $this->runJob($publication = $this->publication())->assertReleased(delay: 60);
        $this->assertSame(self::VIDEO_URN, Cache::get('linkedin-video:'.$publication->id));

        Http::swap(new HttpFactory);
        Http::preventStrayRequests();
        $this->fakeLinkedIn(statuses: ['AVAILABLE']);
        $this->runJob($publication->fresh());

        $this->assertSame('publicado', $publication->fresh()->status);
        $this->assertCount(2, $this->uploads, 'as duas partes foram enviadas uma única vez');
        Http::assertNotSent(fn (HttpRequest $request) => str_contains($request->url(), 'initializeUpload'));
    }

    public function test_processing_failure_is_definitive(): void
    {
        $this->fakeLinkedIn(statuses: ['PROCESSING_FAILED'], failure: 'FILE_TYPE_NOT_SUPPORTED');

        $this->runJob($publication = $this->publication())->assertNotReleased();

        $this->assertSame('FILE_TYPE_NOT_SUPPORTED', $publication->fresh()->last_error);
        $this->assertNull(Cache::get('linkedin-video:'.$publication->id));
    }

    public function test_video_outside_linkedin_limits_is_refused_before_any_call(): void
    {
        Storage::disk('videos')->put($this->video->source_path, 'pequeno-demais');
        $this->runJob($pequeno = $this->publication());
        $this->assertSame('linkedin_file_size_invalid', $pequeno->fresh()->last_error);

        Storage::disk('videos')->put($this->video->source_path, $this->content);
        $this->video->update(['duration_seconds' => 2000]);
        $this->runJob($longo = $this->publication());
        $this->assertSame('linkedin_duration_invalid', $longo->fresh()->last_error);

        Http::assertNothingSent();
    }

    public function test_rejected_token_asks_to_reconnect(): void
    {
        Http::fake([self::API.'/videos*' => Http::response(['status' => 401, 'code' => 'REVOKED_ACCESS_TOKEN', 'message' => 'x'], 401)]);

        $this->runJob($publication = $this->publication())->assertNotReleased();

        $this->assertSame('reconnect_required', $publication->fresh()->last_error);
        $this->assertSame(SocialConnection::STATUS_EXPIRED, $this->connection->fresh()->status);
        $this->assertSame('REVOKED_ACCESS_TOKEN', $this->connection->fresh()->last_error);
    }

    public function test_expired_token_is_caught_before_calling_linkedin(): void
    {
        $this->connection->update(['expires_at' => now()->subDay()]);

        $this->runJob($publication = $this->publication());

        $this->assertSame('reconnect_required', $publication->fresh()->last_error);
        Http::assertNothingSent();
    }

    public function test_upload_url_outside_linkedin_is_refused(): void
    {
        $this->fakeLinkedIn(firstPartUrl: 'https://atacante.test/captura');

        $this->runJob($publication = $this->publication())->assertReleased();

        $this->assertSame('upload_init_failed', $publication->fresh()->last_error);
        Http::assertNotSent(fn (HttpRequest $request) => str_contains($request->url(), 'atacante.test'));
    }

    public function test_little_text_escaping_keeps_hashtags(): void
    {
        $this->assertSame('Oi \\@ana \\(teste\\) \\[x\\] \\_y\\_ \\*z\\* \\~ \\| \\{\\} \\\\ #marca', LinkedInVideoPublisher::escapeLittleText('Oi @ana (teste) [x] _y_ *z* ~ | {} \\ #marca'));
    }

    private function publication(): VideoPublication
    {
        return VideoPublication::create([
            'tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'video_id' => $this->video->id,
            'driver' => 'linkedin', 'platform' => 'linkedin', 'social_connection_id' => $this->connection->id,
            'titulo' => 'Lancamento', 'plataformas' => ['linkedin'], 'status' => 'pendente',
        ]);
    }

    private function runJob(VideoPublication $publication): PublishVideoToPlatform
    {
        $job = (new PublishVideoToPlatform($publication->id, $publication->tenant_id))->withFakeQueueInteractions();
        $job->handle(app(NativePublisherRegistry::class));

        return $job;
    }

    private function fakeLinkedIn(array $statuses = ['AVAILABLE'], string $failure = '', string $firstPartUrl = self::PART_1): void
    {
        $sequence = Http::sequence();
        foreach ($statuses as $status) {
            $sequence->push(array_filter(['id' => self::VIDEO_URN, 'status' => $status, 'processingFailureReason' => $failure ?: null]));
        }

        Http::fake([
            self::API.'/videos?action=initializeUpload' => Http::response(['value' => [
                'uploadUrlsExpireAt' => now()->addDays(30)->getTimestampMs(),
                'video' => self::VIDEO_URN,
                'uploadInstructions' => [
                    ['uploadUrl' => $firstPartUrl, 'firstByte' => 0, 'lastByte' => 49999],
                    ['uploadUrl' => self::PART_2, 'firstByte' => 50000, 'lastByte' => 81919],
                ],
                'uploadToken' => 'token-sessao-ficticio',
            ]]),
            'https://www.linkedin.com/dms-uploads/*' => function (HttpRequest $request) {
                $this->uploads[] = ['url' => $request->url(), 'body' => $request->body()];

                return Http::response('', 200, ['ETag' => 'etag-parte-'.count($this->uploads)]);
            },
            self::API.'/videos?action=finalizeUpload' => function (HttpRequest $request) {
                $this->finalize = $request->data();

                return Http::response('', 200);
            },
            self::API.'/videos/*' => $sequence,
            self::API.'/posts' => function (HttpRequest $request) {
                $this->post = $request->data();

                return Http::response('', 201, ['x-restli-id' => 'urn:li:share:6844785523593134080']);
            },
        ]);
    }
}
