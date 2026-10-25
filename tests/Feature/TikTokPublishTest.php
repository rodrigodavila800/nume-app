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
use App\Services\SocialPublishing\TikTok\TikTokPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * Direct Post no TikTok pelo app próprio. Arquivo, tokens e conta fictícios; API simulada.
 */
class TikTokPublishTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://open.tiktokapis.com';

    private const UPLOAD = 'https://open-upload.tiktokapis.com/upload/?upload_id=sessao-ficticia&upload_token=t';

    private const CONTENT = 'conteudo-ficticio-de-video';

    private Tenant $tenant;

    private Cliente $cliente;

    private Video $video;

    private SocialConnection $connection;

    /** @var list<array{range: string, body: string}> */
    private array $uploads = [];

    private array $initBody = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('videos');
        Http::preventStrayRequests();
        Sleep::fake();
        config([
            'services.tiktok.client_key' => 'chave-tiktok-ficticia',
            'services.tiktok.client_secret' => 'segredo-tiktok-ficticio',
            'social.meta.poll_attempts' => 3,
        ]);

        $this->tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active']);
        $staff = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Staff', 'email' => 'staff@ficticio.test', 'password' => 'secret12345', 'role' => 'admin']);
        $this->cliente = Cliente::create(['tenant_id' => $this->tenant->id, 'name' => 'Cliente A', 'email' => 'a@ficticio.test', 'password' => bcrypt('secret123')]);
        $production = Production::create(['tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'title' => 'Campanha', 'status' => 'ready', 'created_by' => $staff->id]);

        Storage::disk('videos')->put($this->tenant->id.'/tk.mp4', self::CONTENT);
        $this->video = Video::create([
            'tenant_id' => $this->tenant->id, 'production_id' => $production->id, 'title' => 'Corte Final', 'status' => 'ready',
            'source_disk' => 'videos', 'source_path' => $this->tenant->id.'/tk.mp4', 'source_mime' => 'video/mp4',
            'source_size' => strlen(self::CONTENT), 'duration_seconds' => 30,
        ]);

        $this->connection = SocialConnection::create([
            'tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'platform' => 'tiktok', 'provider' => 'tiktok',
            'external_account_id' => 'open-id-ficticio', 'handle' => '@clinicaficticia', 'access_token' => 'act.ficticio',
            'refresh_token' => 'rft.ficticio', 'expires_at' => now()->addHours(20),
        ]);
    }

    public function test_publishes_with_the_cliente_choices_and_waits_for_completion(): void
    {
        $this->fakeTikTok(statuses: ['PROCESSING_UPLOAD', 'PUBLISH_COMPLETE']);

        $this->runJob($publication = $this->publication())->assertNotReleased();

        $publication->refresh();
        $this->assertSame('publicado', $publication->status);
        $this->assertSame('7300000000000000001', $publication->external_id);
        $this->assertSame('https://www.tiktok.com/@clinicaficticia/video/7300000000000000001', $publication->resultado['url']);

        $this->assertSame('Legenda escolhida pelo cliente #clinica', $this->initBody['post_info']['title']);
        $this->assertSame('PUBLIC_TO_EVERYONE', $this->initBody['post_info']['privacy_level']);
        $this->assertFalse($this->initBody['post_info']['disable_comment']);
        $this->assertTrue($this->initBody['post_info']['disable_duet'], 'dueto não marcado pelo cliente fica desativado');
        $this->assertTrue($this->initBody['post_info']['disable_stitch'], 'stitch desativado pelo criador fica desativado');
        $this->assertTrue($this->initBody['post_info']['brand_organic_toggle']);
        $this->assertFalse($this->initBody['post_info']['brand_content_toggle']);
        $this->assertSame(['source' => 'FILE_UPLOAD', 'video_size' => 26, 'chunk_size' => 26, 'total_chunk_count' => 1], $this->initBody['source_info']);

        $this->assertSame([['range' => 'bytes 0-25/26', 'body' => self::CONTENT]], $this->uploads);
        Http::assertSent(fn (HttpRequest $request) => $request->url() === self::API.'/v2/post/publish/status/fetch/' && $request['publish_id'] === 'v_pub_ficticio');
    }

    public function test_private_post_links_to_the_profile(): void
    {
        $this->fakeTikTok(statuses: ['PUBLISH_COMPLETE'], postIds: []);

        $this->runJob($publication = $this->publication(['privacy_level' => 'SELF_ONLY', 'brand_organic' => false]));

        $this->assertSame('v_pub_ficticio', $publication->fresh()->external_id);
        $this->assertSame('https://www.tiktok.com/@clinicaficticia', $publication->fresh()->resultado['url']);
    }

    public function test_choices_are_checked_again_against_fresh_creator_info(): void
    {
        $this->fakeTikTok(creator: ['privacy_level_options' => ['FOLLOWER_OF_CREATOR', 'MUTUAL_FOLLOW_FRIENDS', 'SELF_ONLY']]);
        $this->runJob($privacidade = $this->publication());
        $this->assertSame('privacy_level_option_mismatch', $privacidade->fresh()->last_error);

        $this->runJob($marca = $this->publication(['privacy_level' => 'SELF_ONLY', 'brand_content' => true]));
        $this->assertSame('branded_content_cannot_be_private', $marca->fresh()->last_error);

        Http::swap(new HttpFactory);
        Http::preventStrayRequests();
        $this->fakeTikTok(creator: ['max_video_post_duration_sec' => 15]);
        $this->runJob($longo = $this->publication());
        $this->assertSame('tiktok_duration_invalid', $longo->fresh()->last_error);

        Http::assertNotSent(fn (HttpRequest $request) => str_contains($request->url(), '/video/init/'));
    }

    public function test_publication_without_cliente_choices_is_never_sent(): void
    {
        $publication = $this->publication();
        $publication->update(['resultado' => ['driver' => 'tiktok_native']]);

        $this->runJob($publication)->assertNotReleased();

        $this->assertSame('tiktok_options_missing', $publication->fresh()->last_error);
        Http::assertNothingSent();
    }

    public function test_slow_processing_resumes_without_uploading_again(): void
    {
        $this->fakeTikTok(statuses: ['PROCESSING_UPLOAD', 'PROCESSING_UPLOAD', 'PROCESSING_UPLOAD']);
        $this->runJob($publication = $this->publication())->assertReleased(delay: 60);
        $this->assertSame('v_pub_ficticio', Cache::get('tiktok-publish:'.$publication->id));

        Http::swap(new HttpFactory);
        Http::preventStrayRequests();
        $this->fakeTikTok(statuses: ['PUBLISH_COMPLETE']);
        $this->runJob($publication->fresh());

        $this->assertSame('publicado', $publication->fresh()->status);
        $this->assertCount(1, $this->uploads);
        Http::assertNotSent(fn (HttpRequest $request) => str_contains($request->url(), '/video/init/'));
    }

    public function test_failed_publish_reports_the_tiktok_reason(): void
    {
        $this->fakeTikTok(statuses: ['FAILED'], failReason: 'video_pull_failed');

        $this->runJob($publication = $this->publication())->assertNotReleased();

        $this->assertSame('video_pull_failed', $publication->fresh()->last_error);
    }

    public function test_errors_reported_with_http_200_are_still_errors(): void
    {
        $this->fakeTikTok(creatorError: 'spam_risk_too_many_posts');

        $this->runJob($publication = $this->publication())->assertNotReleased();

        $this->assertSame('publish_limit_reached', $publication->fresh()->last_error);
    }

    public function test_rejected_token_is_refreshed_once_before_asking_to_reconnect(): void
    {
        Http::fake([
            self::API.'/v2/oauth/token/' => Http::response(['access_token' => 'act.novo', 'refresh_token' => 'rft.novo', 'expires_in' => 86400, 'open_id' => 'open-id-ficticio', 'scope' => 'video.publish']),
            self::API.'/v2/post/publish/creator_info/query/' => Http::response(['data' => [], 'error' => ['code' => 'access_token_invalid', 'message' => 'x']], 401),
        ]);

        $this->runJob($publication = $this->publication())->assertNotReleased();

        $this->assertSame('reconnect_required', $publication->fresh()->last_error);
        $this->assertSame(SocialConnection::STATUS_EXPIRED, $this->connection->fresh()->status);
        Http::assertSent(fn (HttpRequest $request) => $request->url() === self::API.'/v2/oauth/token/');
        Http::assertSentCount(3);
    }

    public function test_upload_url_outside_tiktok_is_refused(): void
    {
        $this->fakeTikTok(uploadUrl: 'https://atacante.test/captura');

        $this->runJob($publication = $this->publication())->assertReleased();

        $this->assertSame('upload_init_failed', $publication->fresh()->last_error);
        Http::assertNotSent(fn (HttpRequest $request) => str_contains($request->url(), 'atacante.test'));
    }

    public function test_big_files_are_split_in_chunks_with_the_rest_in_the_last_one(): void
    {
        config(['social.tiktok.chunk_mb' => 10]);
        $publisher = app(TikTokPublisher::class);

        $this->assertSame([26, 1], $publisher->chunks(26));
        $this->assertSame([64 * 1048576, 1], $publisher->chunks(64 * 1048576));
        $this->assertSame([10 * 1048576, 10], $publisher->chunks(105 * 1048576), 'a última parte leva 15 MB');
    }

    private function publication(array $options = []): VideoPublication
    {
        return VideoPublication::create([
            'tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'video_id' => $this->video->id,
            'driver' => 'tiktok', 'platform' => 'tiktok', 'social_connection_id' => $this->connection->id,
            'titulo' => 'Lancamento', 'plataformas' => ['tiktok'], 'status' => 'pendente',
            'resultado' => ['driver' => 'tiktok_native', 'tiktok' => array_merge([
                'title' => 'Legenda escolhida pelo cliente #clinica',
                'privacy_level' => 'PUBLIC_TO_EVERYONE',
                'allow_comment' => true, 'allow_duet' => false, 'allow_stitch' => true,
                'brand_organic' => true, 'brand_content' => false,
            ], $options)],
        ]);
    }

    private function runJob(VideoPublication $publication): PublishVideoToPlatform
    {
        $job = (new PublishVideoToPlatform($publication->id, $publication->tenant_id))->withFakeQueueInteractions();
        $job->handle(app(NativePublisherRegistry::class));

        return $job;
    }

    private function fakeTikTok(
        array $statuses = ['PUBLISH_COMPLETE'],
        array $creator = [],
        ?string $creatorError = null,
        array $postIds = [7300000000000000001],
        string $failReason = '',
        string $uploadUrl = self::UPLOAD,
    ): void {
        $sequence = Http::sequence();
        foreach ($statuses as $status) {
            $sequence->push(['data' => ['status' => $status, 'fail_reason' => $failReason, 'publicaly_available_post_id' => $status === 'PUBLISH_COMPLETE' ? $postIds : [], 'uploaded_bytes' => 26], 'error' => ['code' => 'ok']]);
        }

        Http::fake([
            self::API.'/v2/post/publish/creator_info/query/' => Http::response($creatorError
                ? ['data' => [], 'error' => ['code' => $creatorError, 'message' => 'x']]
                : ['data' => array_merge([
                    'creator_username' => 'clinicaficticia', 'creator_nickname' => 'Clinica Ficticia',
                    'privacy_level_options' => ['PUBLIC_TO_EVERYONE', 'MUTUAL_FOLLOW_FRIENDS', 'SELF_ONLY'],
                    'comment_disabled' => false, 'duet_disabled' => false, 'stitch_disabled' => true, 'max_video_post_duration_sec' => 600,
                ], $creator), 'error' => ['code' => 'ok']]),
            self::API.'/v2/post/publish/video/init/' => function (HttpRequest $request) use ($uploadUrl) {
                $this->initBody = json_decode($request->body(), true);

                return Http::response(['data' => ['publish_id' => 'v_pub_ficticio', 'upload_url' => $uploadUrl], 'error' => ['code' => 'ok']]);
            },
            'https://open-upload.tiktokapis.com/*' => function (HttpRequest $request) {
                $this->uploads[] = ['range' => $request->header('Content-Range')[0], 'body' => $request->body()];

                return Http::response('', 201);
            },
            self::API.'/v2/post/publish/status/fetch/' => $sequence,
        ]);
    }
}
