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
use App\Services\SocialPublishing\OAuth\GoogleOAuthClient;
use App\Services\SocialPublishing\PublishException;
use App\Services\SocialPublishing\YouTube\YouTubeUploader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Envio nativo ao YouTube (job + upload resumable). Arquivo, tokens e canal fictícios; Google simulado.
 */
class YouTubeNativePublishTest extends TestCase
{
    use RefreshDatabase;

    private const CONTENT = 'conteudo-ficticio-de-video';

    private const SESSION = YouTubeUploader::UPLOAD_URL.'?uploadType=resumable&upload_id=sessao-ficticia';

    private Tenant $tenant;

    private Cliente $cliente;

    private Video $video;

    private SocialConnection $connection;

    private array $logs = [];

    /** @var list<array{0: string, 1: string}> Content-Range e corpo de cada PUT na sessão de upload */
    private array $uploads = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('videos');
        Http::preventStrayRequests();
        config([
            'services.google.client_id' => 'cliente-oauth-ficticio',
            'services.google.client_secret' => 'segredo-ficticio',
            'social.youtube.privacy' => 'private',
        ]);
        Event::listen(MessageLogged::class, function (MessageLogged $event) {
            $this->logs[] = $event->message.json_encode($event->context);
        });

        $this->tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active']);
        $staff = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Staff', 'email' => 'staff@ficticio.test', 'password' => 'secret12345', 'role' => 'admin']);
        $this->cliente = Cliente::create(['tenant_id' => $this->tenant->id, 'name' => 'Cliente A', 'email' => 'a@ficticio.test', 'password' => bcrypt('secret123')]);
        $production = Production::create(['tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'title' => 'Campanha Ficticia', 'status' => 'ready', 'created_by' => $staff->id]);

        $path = $this->tenant->id.'/video-ficticio.mp4';
        Storage::disk('videos')->put($path, self::CONTENT);
        $this->video = Video::create([
            'tenant_id' => $this->tenant->id, 'production_id' => $production->id, 'title' => 'Corte Final', 'status' => 'ready',
            'source_disk' => 'videos', 'source_path' => $path, 'source_mime' => 'video/mp4', 'source_size' => strlen(self::CONTENT),
            'sofi_seo_description' => 'Descricao <b>SEO</b> ficticia', 'sofi_hashtags' => ['saude', '#clinica'],
            'sofi_seo_keywords' => 'clinica, saude bucal, ,implante',
        ]);

        $this->connection = SocialConnection::create([
            'tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'platform' => 'youtube', 'provider' => 'google',
            'external_account_id' => 'UC-canal-ficticio', 'access_token' => 'ya29.acesso-ficticio', 'refresh_token' => '1//atualizacao-ficticia',
            'expires_at' => now()->addHour(),
        ]);
    }

    public function test_uploads_the_hosted_file_with_sofi_metadata_and_marks_published(): void
    {
        $this->fakeYouTube();
        $publication = $this->publication();

        $job = $this->runJob($publication);

        $job->assertNotReleased();
        $publication->refresh();
        $this->assertSame('publicado', $publication->status);
        $this->assertSame('dQw4w9WgXcQ', $publication->external_id);
        $this->assertSame(1, $publication->attempts);
        $this->assertSame('https://youtu.be/dQw4w9WgXcQ', $publication->resultado['url']);
        $this->assertNotNull($publication->publicado_em);
        $this->assertSame('published', $this->video->fresh()->status);

        Http::assertSent(function (HttpRequest $request) {
            return $request->method() === 'POST'
                && str_starts_with($request->url(), YouTubeUploader::UPLOAD_URL.'?uploadType=resumable')
                && $request->hasHeader('Authorization', 'Bearer ya29.acesso-ficticio')
                && $request->hasHeader('X-Upload-Content-Length', (string) strlen(self::CONTENT))
                && $request['snippet']['title'] === 'Lancamento no canal'
                && $request['snippet']['description'] === "Descricao bSEO/b ficticia\n\n#saude #clinica"
                && $request['snippet']['tags'] === ['clinica', 'saude bucal', 'implante']
                && $request['status']['privacyStatus'] === 'private'
                && $request['status']['selfDeclaredMadeForKids'] === false;
        });
        $this->assertSame([['bytes 0-25/26', self::CONTENT]], $this->uploads);

        $this->assertNull(Cache::get('youtube-upload-session:'.$publication->id));
        $this->assertStringNotContainsString('upload_id', json_encode($publication->resultado));
        $this->assertLogsAreClean();
    }

    public function test_expiring_token_is_refreshed_before_uploading(): void
    {
        $this->connection->update(['expires_at' => now()->addMinute()]);
        $this->fakeYouTube(token: ['access_token' => 'ya29.renovado-ficticio']);

        $this->runJob($publication = $this->publication());

        $this->assertSame('publicado', $publication->fresh()->status);
        $this->assertSame('ya29.renovado-ficticio', $this->connection->fresh()->access_token);
        $this->assertSame('1//atualizacao-ficticia', $this->connection->fresh()->refresh_token);
        Http::assertSent(fn (HttpRequest $request) => $request->url() === GoogleOAuthClient::TOKEN_URL && $request['grant_type'] === 'refresh_token');
        Http::assertSent(fn (HttpRequest $request) => $request->method() === 'PUT' && $request->hasHeader('Authorization', 'Bearer ya29.renovado-ficticio'));
    }

    public function test_token_rejected_by_youtube_is_refreshed_once_and_the_upload_continues(): void
    {
        Http::fake([
            GoogleOAuthClient::TOKEN_URL => Http::response(['access_token' => 'ya29.renovado-ficticio', 'expires_in' => 3599, 'scope' => '']),
            YouTubeUploader::UPLOAD_URL.'?uploadType=resumable&part=*' => Http::sequence()
                ->push(['error' => ['code' => 401, 'status' => 'UNAUTHENTICATED']], 401)
                ->push([], 200, ['Location' => self::SESSION]),
            self::SESSION => Http::response(['id' => 'dQw4w9WgXcQ']),
        ]);

        $this->runJob($publication = $this->publication());

        $this->assertSame('publicado', $publication->fresh()->status);
        $this->assertSame('ya29.renovado-ficticio', $this->connection->fresh()->access_token);
    }

    public function test_revoked_access_marks_the_connection_for_reconnection_without_retrying(): void
    {
        $this->connection->update(['expires_at' => now()->subMinute()]);
        Http::fake([GoogleOAuthClient::TOKEN_URL => Http::response(['error' => 'invalid_grant'], 400)]);

        $job = $this->runJob($publication = $this->publication());

        $job->assertNotReleased();
        $this->assertSame('falhou', $publication->fresh()->status);
        $this->assertSame('reconnect_required', $publication->fresh()->last_error);
        $this->assertSame(SocialConnection::STATUS_EXPIRED, $this->connection->fresh()->status);
        $this->assertSame('invalid_grant', $this->connection->fresh()->last_error);
        $this->assertLogsAreClean();
    }

    public function test_daily_quota_is_a_definitive_failure(): void
    {
        Http::fake([YouTubeUploader::UPLOAD_URL.'*' => Http::response(['error' => ['code' => 403, 'errors' => [['reason' => 'quotaExceeded']]]], 403)]);

        $job = $this->runJob($publication = $this->publication());

        $job->assertNotReleased();
        $this->assertSame('falhou', $publication->fresh()->status);
        $this->assertSame('quotaExceeded', $publication->fresh()->last_error);
    }

    public function test_interrupted_upload_is_retried_later_and_keeps_the_session_encrypted(): void
    {
        Http::fake([
            YouTubeUploader::UPLOAD_URL.'?uploadType=resumable&part=*' => Http::response([], 200, ['Location' => self::SESSION]),
            self::SESSION => Http::response('', 503),
        ]);

        $job = $this->runJob($publication = $this->publication());

        $job->assertReleased(delay: 60);
        $publication->refresh();
        $this->assertSame('processando', $publication->status);
        $this->assertSame('upload_interrupted', $publication->last_error);

        $cached = Cache::get('youtube-upload-session:'.$publication->id);
        $this->assertStringNotContainsString('sessao-ficticia', $cached);
        $this->assertSame(self::SESSION, Crypt::decryptString($cached));
        $this->assertLogsAreClean();
    }

    public function test_next_attempt_resumes_from_the_bytes_google_already_has(): void
    {
        $publication = $this->publication(['status' => 'processando', 'attempts' => 1]);
        Cache::put('youtube-upload-session:'.$publication->id, Crypt::encryptString(self::SESSION), now()->addDay());
        Http::fake([
            self::SESSION => function (HttpRequest $request) {
                $this->uploads[] = [$request->header('Content-Range')[0], $request->body()];

                return count($this->uploads) === 1
                    ? Http::response('', 308, ['Range' => 'bytes=0-9'])
                    : Http::response(['id' => 'dQw4w9WgXcQ'], 201);
            },
        ]);

        $this->runJob($publication);

        $this->assertSame('publicado', $publication->fresh()->status);
        $this->assertSame([['bytes */26', ''], ['bytes 10-25/26', substr(self::CONTENT, 10)]], $this->uploads);
        Http::assertNotSent(fn (HttpRequest $request) => $request->method() === 'POST');
    }

    public function test_finished_or_already_uploaded_publications_are_not_sent_again(): void
    {
        $done = $this->publication(['status' => 'publicado']);
        $failed = $this->publication(['status' => 'falhou']);
        $uploaded = $this->publication(['status' => 'processando', 'external_id' => 'dQw4w9WgXcQ']);

        foreach ([$done, $failed, $uploaded] as $publication) {
            $this->runJob($publication);
        }

        Http::assertNothingSent();
        $this->assertSame('falhou', $failed->fresh()->status);
        $this->assertSame('publicado', $uploaded->fresh()->status);
    }

    public function test_job_never_reads_another_tenants_publication(): void
    {
        $outro = Tenant::create(['name' => 'Produtora B', 'slug' => 'produtora-b', 'status' => 'active']);
        $publication = $this->publication();

        (new PublishVideoToPlatform($publication->id, $outro->id))->withFakeQueueInteractions()->handle(app(YouTubeUploader::class));

        Http::assertNothingSent();
        $this->assertSame('pendente', $publication->fresh()->status);
    }

    public function test_missing_file_or_inactive_connection_fail_without_calling_google(): void
    {
        $semArquivo = $this->publication();
        Storage::disk('videos')->delete($this->video->source_path);
        $this->runJob($semArquivo);
        $this->assertSame('source_missing', $semArquivo->fresh()->last_error);

        Storage::disk('videos')->put($this->video->source_path, self::CONTENT);
        $this->connection->update(['status' => SocialConnection::STATUS_REVOKED]);
        $semConta = $this->publication();
        $this->runJob($semConta);
        $this->assertSame('reconnect_required', $semConta->fresh()->last_error);

        Http::assertNothingSent();
    }

    public function test_failed_hook_marks_the_publication_as_failed(): void
    {
        $publication = $this->publication(['status' => 'processando']);

        (new PublishVideoToPlatform($publication->id, $this->tenant->id))->failed(new PublishException('upload_interrupted', retryable: true));

        $this->assertSame('falhou', $publication->fresh()->status);
        $this->assertSame('upload_interrupted', $publication->fresh()->last_error);
    }

    public function test_job_runs_on_the_long_upload_connection(): void
    {
        $job = new PublishVideoToPlatform(1, $this->tenant->id);

        $this->assertSame('social-uploads', $job->connection);
        $this->assertSame('social-uploads', $job->queue);
        $this->assertGreaterThan($job->timeout, config('queue.connections.social-uploads.retry_after'));
    }

    private function publication(array $attributes = []): VideoPublication
    {
        return VideoPublication::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'video_id' => $this->video->id,
            'driver' => 'youtube',
            'platform' => 'youtube',
            'social_connection_id' => $this->connection->id,
            'titulo' => 'Lancamento no canal',
            'plataformas' => ['youtube'],
            'status' => 'pendente',
            'resultado' => ['driver' => 'youtube_native'],
        ], $attributes));
    }

    private function runJob(VideoPublication $publication): PublishVideoToPlatform
    {
        $job = (new PublishVideoToPlatform($publication->id, $publication->tenant_id))->withFakeQueueInteractions();
        $job->handle(app(YouTubeUploader::class));

        return $job;
    }

    private function fakeYouTube(array $token = []): void
    {
        Http::fake([
            GoogleOAuthClient::TOKEN_URL => Http::response(array_merge(['access_token' => 'ya29.renovado-ficticio', 'expires_in' => 3599, 'scope' => 'https://www.googleapis.com/auth/youtube.upload'], $token)),
            YouTubeUploader::UPLOAD_URL.'?uploadType=resumable&part=*' => Http::response([], 200, ['Location' => self::SESSION]),
            self::SESSION => function (HttpRequest $request) {
                $this->uploads[] = [$request->header('Content-Range')[0], $request->body()];

                return Http::response(['id' => 'dQw4w9WgXcQ', 'status' => ['uploadStatus' => 'uploaded']]);
            },
        ]);
    }

    private function assertLogsAreClean(): void
    {
        foreach ($this->logs as $line) {
            $this->assertStringNotContainsString('acesso-ficticio', $line);
            $this->assertStringNotContainsString('atualizacao-ficticia', $line);
            $this->assertStringNotContainsString('renovado-ficticio', $line);
            $this->assertStringNotContainsString('sessao-ficticia', $line);
        }
    }
}
