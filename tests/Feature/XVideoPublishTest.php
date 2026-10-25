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
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * Vídeo no X pelo app próprio. Arquivo, tokens e conta fictícios; API simulada.
 */
class XVideoPublishTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://api.x.com/2';

    private const CONTENT = 'conteudo-ficticio-de-video';

    private Tenant $tenant;

    private Cliente $cliente;

    private Video $video;

    private SocialConnection $connection;

    private array $segments = [];

    private array $postBody = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('videos');
        Http::preventStrayRequests();
        Sleep::fake();
        config(['social.meta.poll_attempts' => 3]);

        $this->tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active']);
        $staff = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Staff', 'email' => 'staff@ficticio.test', 'password' => 'secret12345', 'role' => 'admin']);
        $this->cliente = Cliente::create(['tenant_id' => $this->tenant->id, 'name' => 'Cliente A', 'email' => 'a@ficticio.test', 'password' => bcrypt('secret123')]);
        $production = Production::create(['tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'title' => 'Campanha', 'status' => 'ready', 'created_by' => $staff->id]);

        Storage::disk('videos')->put($this->tenant->id.'/x.mp4', self::CONTENT);
        $this->video = Video::create([
            'tenant_id' => $this->tenant->id, 'production_id' => $production->id, 'title' => 'Corte Final', 'status' => 'ready',
            'source_disk' => 'videos', 'source_path' => $this->tenant->id.'/x.mp4', 'source_mime' => 'video/mp4', 'source_size' => strlen(self::CONTENT),
            'sofi_seo_description' => 'Veja em https://clinica-ficticia.test o passo a passo', 'sofi_hashtags' => ['clinica', 'saude', 'sorriso', 'extra'],
        ]);

        $this->connection = SocialConnection::create([
            'tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'platform' => 'x', 'provider' => 'x',
            'external_account_id' => '1234567890', 'handle' => '@clinicaficticia', 'access_token' => 'acesso-x-ficticio',
            'refresh_token' => 'atualizacao-x-ficticia', 'expires_at' => now()->addHour(),
        ]);
    }

    public function test_uploads_in_segments_waits_and_posts_without_links(): void
    {
        $this->fakeX(statuses: [['state' => 'in_progress', 'check_after_secs' => 2], ['state' => 'succeeded']]);

        $this->runJob($publication = $this->publication())->assertNotReleased();

        $publication->refresh();
        $this->assertSame('publicado', $publication->status);
        $this->assertSame('1800000000000000001', $publication->external_id);
        $this->assertSame('https://x.com/clinicaficticia/status/1800000000000000001', $publication->resultado['url']);

        Http::assertSent(fn (HttpRequest $request) => $request->url() === self::API.'/media/upload/initialize'
            && $request['media_category'] === 'tweet_video'
            && $request['total_bytes'] === strlen(self::CONTENT)
            && $request->hasHeader('Authorization', 'Bearer acesso-x-ficticio'));
        $this->assertCount(1, $this->segments);
        $this->assertStringContainsString(self::CONTENT, $this->segments[0]);
        $this->assertStringContainsString('name="segment_index"', $this->segments[0]);
        Http::assertSent(fn (HttpRequest $request) => $request->url() === self::API.'/media/upload/9990001/finalize');
        Sleep::assertSlept(fn ($duration) => (int) $duration->totalSeconds === 2);

        $this->assertSame(['media_ids' => ['9990001']], $this->postBody['media']);
        $this->assertStringNotContainsString('http', $this->postBody['text']);
        $this->assertLessThanOrEqual(280, mb_strlen($this->postBody['text']));
        $this->assertStringContainsString('#sorriso', $this->postBody['text']);
        $this->assertStringNotContainsString('#extra', $this->postBody['text']);
    }

    public function test_slow_processing_resumes_without_uploading_again(): void
    {
        $this->fakeX(statuses: [['state' => 'pending'], ['state' => 'pending'], ['state' => 'pending']]);
        $this->runJob($publication = $this->publication())->assertReleased(delay: 60);
        $this->assertSame('9990001', Cache::get('x-media:'.$publication->id));

        Http::swap(new HttpFactory);
        Http::preventStrayRequests();
        $this->fakeX(statuses: [['state' => 'succeeded']]);
        $this->runJob($publication->fresh());

        $this->assertSame('publicado', $publication->fresh()->status);
        $this->assertCount(1, $this->segments, 'o arquivo foi enviado uma única vez');
    }

    public function test_no_credits_is_a_definitive_failure(): void
    {
        Http::fake([self::API.'/media/upload/initialize' => Http::response(['title' => 'CreditsDepleted', 'detail' => 'x', 'status' => 402], 402)]);

        $this->runJob($publication = $this->publication())->assertNotReleased();

        $this->assertSame('x_credits_exhausted', $publication->fresh()->last_error);
    }

    public function test_rejected_token_asks_to_reconnect_and_rate_limit_is_retried(): void
    {
        Http::fake([self::API.'/media/upload/initialize' => Http::sequence()
            ->push(['title' => 'Unauthorized', 'status' => 401], 401)
            ->push(['title' => 'Too Many Requests', 'status' => 429], 429)]);

        $this->runJob($semAcesso = $this->publication())->assertNotReleased();
        $this->assertSame('reconnect_required', $semAcesso->fresh()->last_error);
        $this->assertSame(SocialConnection::STATUS_EXPIRED, $this->connection->fresh()->status);

        $this->connection->fresh()->update(['status' => SocialConnection::STATUS_ACTIVE]);
        $job = $this->runJob($limitado = $this->publication());
        $this->assertSame('rate_limited', $limitado->fresh()->last_error);
        $job->assertReleased(delay: 60);
    }

    public function test_failed_processing_is_definitive(): void
    {
        $this->fakeX(statuses: [['state' => 'failed', 'error' => ['message' => 'InvalidMedia']]]);

        $this->runJob($publication = $this->publication())->assertNotReleased();

        $this->assertSame('media_processing_failed', $publication->fresh()->last_error);
    }

    private function publication(): VideoPublication
    {
        return VideoPublication::create([
            'tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'video_id' => $this->video->id,
            'driver' => 'x', 'platform' => 'x', 'social_connection_id' => $this->connection->id,
            'titulo' => 'Lancamento', 'plataformas' => ['x'], 'status' => 'pendente',
        ]);
    }

    private function runJob(VideoPublication $publication): PublishVideoToPlatform
    {
        $job = (new PublishVideoToPlatform($publication->id, $publication->tenant_id))->withFakeQueueInteractions();
        $job->handle(app(NativePublisherRegistry::class));

        return $job;
    }

    private function fakeX(array $statuses): void
    {
        $sequence = Http::sequence();
        foreach ($statuses as $info) {
            $sequence->push(['data' => ['id' => '9990001', 'media_key' => '7_9990001', 'processing_info' => $info]]);
        }

        Http::fake([
            self::API.'/media/upload/initialize' => Http::response(['data' => ['id' => '9990001', 'media_key' => '7_9990001', 'expires_after_secs' => 86400]]),
            self::API.'/media/upload/9990001/append' => function (HttpRequest $request) {
                $this->segments[] = $request->body();

                return Http::response('', 204);
            },
            self::API.'/media/upload/9990001/finalize' => Http::response(['data' => ['id' => '9990001', 'processing_info' => ['state' => 'pending', 'check_after_secs' => 1]]]),
            // Só a consulta de status (com query string); as outras rotas de upload têm stub próprio.
            self::API.'/media/upload?*' => $sequence,
            self::API.'/tweets' => function (HttpRequest $request) {
                $this->postBody = $request->data();

                return Http::response(['data' => ['id' => '1800000000000000001', 'text' => 'ok']], 201);
            },
        ]);
    }
}
