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
 * Pin de vídeo no Pinterest pelo app próprio. Arquivo, tokens e conta fictícios; API e S3 simulados.
 */
class PinterestVideoPublishTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://api.pinterest.com/v5';

    private const S3 = 'https://pinterest-media-upload.s3-accelerate.amazonaws.com/';

    private const CONTENT = 'conteudo-ficticio-de-video';

    private Tenant $tenant;

    private Cliente $cliente;

    private Video $video;

    private SocialConnection $connection;

    private array $s3Requests = [];

    private array $pinBody = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('videos');
        Http::preventStrayRequests();
        Sleep::fake();
        config(['social.pinterest.api_base' => self::API, 'social.meta.poll_attempts' => 3]);

        $this->tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active']);
        $staff = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Staff', 'email' => 'staff@ficticio.test', 'password' => 'secret12345', 'role' => 'admin']);
        $this->cliente = Cliente::create(['tenant_id' => $this->tenant->id, 'name' => 'Cliente A', 'email' => 'a@ficticio.test', 'password' => bcrypt('secret123')]);
        $production = Production::create(['tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'title' => 'Campanha', 'status' => 'ready', 'created_by' => $staff->id]);

        Storage::disk('videos')->put($this->tenant->id.'/pin.mp4', self::CONTENT);
        $this->video = Video::create([
            'tenant_id' => $this->tenant->id, 'production_id' => $production->id, 'title' => 'Corte Final', 'status' => 'ready',
            'source_disk' => 'videos', 'source_path' => $this->tenant->id.'/pin.mp4', 'source_mime' => 'video/mp4', 'source_size' => strlen(self::CONTENT),
        ]);

        $this->connection = SocialConnection::create([
            'tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'platform' => 'pinterest', 'provider' => 'pinterest',
            'external_account_id' => 'conta-ficticia', 'handle' => '@clinicaficticia', 'access_token' => 'pina.ficticio',
            'refresh_token' => 'pinr.ficticio', 'expires_at' => now()->addDays(20),
        ]);
    }

    public function test_registers_uploads_to_s3_waits_and_creates_the_pin_on_the_chosen_board(): void
    {
        $this->fakePinterest(statuses: ['registered', 'succeeded']);

        $this->runJob($publication = $this->publication())->assertNotReleased();

        $publication->refresh();
        $this->assertSame('publicado', $publication->status);
        $this->assertSame('987654321', $publication->external_id);
        $this->assertSame('https://www.pinterest.com/pin/987654321/', $publication->resultado['url']);

        // S3: campos assinados primeiro, arquivo por último, e nenhum token do Pinterest.
        $this->assertCount(1, $this->s3Requests);
        $body = $this->s3Requests[0]['body'];
        $this->assertFalse($this->s3Requests[0]['authorization']);
        $this->assertStringContainsString('name="x-amz-signature"', $body);
        $this->assertStringContainsString(self::CONTENT, $body);
        $this->assertLessThan(strpos($body, 'name="file"'), strpos($body, 'name="policy"'));

        $this->assertSame('5550001', $this->pinBody['board_id']);
        $this->assertSame('Sorriso novo', $this->pinBody['title']);
        $this->assertSame('Descricao do pin #clinica', $this->pinBody['description']);
        $this->assertSame('https://clinica-ficticia.test/agendar', $this->pinBody['link']);
        $this->assertSame(['source_type' => 'video_id', 'media_id' => '1112223334', 'cover_image_key_frame_time' => 1], $this->pinBody['media_source']);

        Http::assertSent(fn (HttpRequest $request) => $request->url() === self::API.'/media' && $request->hasHeader('Authorization', 'Bearer pina.ficticio'));
    }

    public function test_slow_processing_resumes_without_uploading_again(): void
    {
        $this->fakePinterest(statuses: ['processing', 'processing', 'processing']);
        $this->runJob($publication = $this->publication())->assertReleased(delay: 60);
        $this->assertSame('1112223334', Cache::get('pinterest-media:'.$publication->id));

        Http::swap(new HttpFactory);
        Http::preventStrayRequests();
        $this->fakePinterest(statuses: ['succeeded']);
        $this->runJob($publication->fresh());

        $this->assertSame('publicado', $publication->fresh()->status);
        $this->assertCount(1, $this->s3Requests, 'o arquivo foi enviado ao S3 uma única vez');
    }

    public function test_failed_media_is_definitive(): void
    {
        $this->fakePinterest(statuses: ['failed']);

        $this->runJob($publication = $this->publication())->assertNotReleased();

        $this->assertSame('media_processing_failed', $publication->fresh()->last_error);
    }

    public function test_publication_without_a_chosen_board_is_never_sent(): void
    {
        $publication = $this->publication();
        $publication->update(['resultado' => ['driver' => 'pinterest_native']]);

        $this->runJob($publication)->assertNotReleased();

        $this->assertSame('pinterest_options_missing', $publication->fresh()->last_error);
        Http::assertNothingSent();
    }

    public function test_upload_destination_outside_s3_is_refused(): void
    {
        Http::fake([self::API.'/media' => Http::response(['media_id' => '1112223334', 'media_type' => 'video', 'upload_url' => 'https://atacante.test/captura', 'upload_parameters' => []])]);

        $this->runJob($publication = $this->publication())->assertReleased();

        $this->assertSame('upload_init_failed', $publication->fresh()->last_error);
        Http::assertNotSent(fn (HttpRequest $request) => str_contains($request->url(), 'atacante.test'));
    }

    public function test_revoked_access_asks_to_reconnect(): void
    {
        Http::fake([self::API.'/media' => Http::response(['code' => 2, 'message' => 'Authentication failed.'], 401)]);

        $this->runJob($publication = $this->publication())->assertNotReleased();

        $this->assertSame('reconnect_required', $publication->fresh()->last_error);
        $this->assertSame(SocialConnection::STATUS_EXPIRED, $this->connection->fresh()->status);
    }

    private function publication(): VideoPublication
    {
        return VideoPublication::create([
            'tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'video_id' => $this->video->id,
            'driver' => 'pinterest', 'platform' => 'pinterest', 'social_connection_id' => $this->connection->id,
            'titulo' => 'Sorriso novo', 'plataformas' => ['pinterest'], 'status' => 'pendente',
            'resultado' => ['driver' => 'pinterest_native', 'pinterest' => [
                'board_id' => '5550001', 'title' => 'Sorriso novo', 'description' => 'Descricao do pin #clinica',
                'link' => 'https://clinica-ficticia.test/agendar',
            ]],
        ]);
    }

    private function runJob(VideoPublication $publication): PublishVideoToPlatform
    {
        $job = (new PublishVideoToPlatform($publication->id, $publication->tenant_id))->withFakeQueueInteractions();
        $job->handle(app(NativePublisherRegistry::class));

        return $job;
    }

    private function fakePinterest(array $statuses): void
    {
        $sequence = Http::sequence();
        foreach ($statuses as $status) {
            $sequence->push(['media_id' => '1112223334', 'media_type' => 'video', 'status' => $status]);
        }

        Http::fake([
            self::API.'/media' => Http::response([
                'media_id' => '1112223334', 'media_type' => 'video', 'upload_url' => self::S3,
                'upload_parameters' => [
                    'x-amz-date' => '20261025T000000Z', 'x-amz-signature' => 'assinatura-ficticia', 'key' => 'uploads/x.mp4',
                    'policy' => 'politica-ficticia', 'x-amz-credential' => 'credencial-ficticia', 'Content-Type' => 'multipart/form-data',
                ],
            ], 201),
            self::S3 => function (HttpRequest $request) {
                $this->s3Requests[] = ['body' => $request->body(), 'authorization' => $request->hasHeader('Authorization')];

                return Http::response('', 204);
            },
            self::API.'/media/*' => $sequence,
            self::API.'/pins' => function (HttpRequest $request) {
                $this->pinBody = $request->data();

                return Http::response(['id' => '987654321', 'board_id' => '5550001'], 201);
            },
        ]);
    }
}
