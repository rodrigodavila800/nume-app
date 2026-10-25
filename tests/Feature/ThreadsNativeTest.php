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
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * Threads pelo app próprio: conexão, envio de vídeo e renovação do token. Tudo fictício; API simulada.
 */
class ThreadsNativeTest extends TestCase
{
    use RefreshDatabase;

    private const HOST = 'https://graph.threads.com';

    private Tenant $tenant;

    private Cliente $cliente;

    private array $logs = [];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('videos');
        Http::preventStrayRequests();
        Sleep::fake();
        config([
            'services.threads.client_id' => 'app-threads-ficticio',
            'services.threads.client_secret' => 'segredo-threads-ficticio',
            'services.threads.redirect' => null,
            'services.meta.client_secret' => 'segredo-meta-ficticio',
            'social.threads.api_version' => 'v1.0',
            'social.meta.poll_attempts' => 3,
        ]);
        Event::listen(MessageLogged::class, function (MessageLogged $event) {
            $this->logs[] = $event->message.json_encode($event->context);
        });

        $this->tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active']);
        $this->cliente = Cliente::create(['tenant_id' => $this->tenant->id, 'name' => 'Cliente A', 'email' => 'a@ficticio.test', 'password' => bcrypt('secret123')]);
    }

    public function test_connect_exchanges_for_a_long_lived_token_and_stores_the_profile(): void
    {
        $location = $this->actingAs($this->cliente, 'cliente')->get(route('portal.social.oauth.redirect', 'threads'))->headers->get('Location');
        $this->assertStringStartsWith('https://threads.com/oauth/authorize?', $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('threads_basic,threads_content_publish', $query['scope']);
        $this->assertSame(route('portal.social.oauth.callback', 'threads'), $query['redirect_uri']);

        Http::fake([
            self::HOST.'/oauth/access_token' => Http::response(['access_token' => 'token-curto-threads', 'user_id' => 4242]),
            self::HOST.'/access_token*' => Http::response(['access_token' => 'token-longo-threads', 'token_type' => 'bearer', 'expires_in' => 5183944]),
            self::HOST.'/v1.0/me*' => Http::response(['id' => '4242', 'username' => 'clinicaficticia', 'threads_profile_picture_url' => 'https://scontent.ficticio.test/t.jpg']),
        ]);

        $this->get(route('portal.social.oauth.callback', ['platform' => 'threads', 'state' => $query['state'], 'code' => 'codigo-ficticio']))
            ->assertRedirect(route('portal.social.index'))
            ->assertSessionHas('success', 'Threads @clinicaficticia conectado com sucesso!');

        $connection = SocialConnection::forTenant($this->tenant->id)->sole();
        $this->assertSame('threads', $connection->platform);
        $this->assertSame('threads', $connection->provider);
        $this->assertSame('4242', $connection->external_account_id);
        $this->assertSame('token-longo-threads', $connection->access_token);
        $this->assertTrue($connection->expires_at->between(now()->addDays(59), now()->addDays(61)));
        $this->assertStringNotContainsString('token-longo', DB::table('social_connections')->value('access_token'));

        Http::assertSent(fn (HttpRequest $request) => $request->url() === self::HOST.'/oauth/access_token' && $request['code'] === 'codigo-ficticio');
        Http::assertSent(fn (HttpRequest $request) => str_starts_with($request->url(), self::HOST.'/v1.0/me') && $request->hasHeader('Authorization', 'Bearer token-longo-threads'));

        foreach ($this->logs as $line) {
            $this->assertStringNotContainsString('token-', $line);
        }
    }

    public function test_publishes_the_video_by_signed_url_and_waits_for_processing(): void
    {
        [$publication, $video] = $this->publication();
        Http::fake([
            self::HOST.'/v1.0/4242/threads_publish*' => Http::response(['id' => '7001']),
            self::HOST.'/v1.0/4242/threads*' => Http::response(['id' => '6001']),
            self::HOST.'/v1.0/6001*' => Http::sequence()->push(['status' => 'IN_PROGRESS'])->push(['status' => 'FINISHED']),
            self::HOST.'/v1.0/7001*' => Http::response(['permalink' => 'https://www.threads.com/@clinicaficticia/post/XYZ']),
        ]);

        $this->runJob($publication)->assertNotReleased();

        $publication->refresh();
        $this->assertSame('publicado', $publication->status);
        $this->assertSame('7001', $publication->external_id);
        $this->assertSame('https://www.threads.com/@clinicaficticia/post/XYZ', $publication->resultado['url']);

        Http::assertSent(function (HttpRequest $request) use ($video) {
            return str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/4242/threads')
                && $request['media_type'] === 'VIDEO'
                && str_contains($request['video_url'], '/media/videos/'.$video->id)
                && str_contains($request['video_url'], 'signature=')
                && mb_strlen($request['text']) <= 500
                && $request->hasHeader('Authorization', 'Bearer token-threads-ficticio');
        });
        Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), 'threads_publish') && $request['creation_id'] === '6001');
    }

    public function test_threads_text_keeps_one_tag_and_500_characters(): void
    {
        [$publication, $video] = $this->publication();
        $video->update(['sofi_seo_description' => str_repeat('a', 900), 'sofi_hashtags' => ['clinica', 'saude', 'sorriso']]);
        Http::fake([
            self::HOST.'/v1.0/4242/threads_publish*' => Http::response(['id' => '7001']),
            self::HOST.'/v1.0/4242/threads*' => Http::response(['id' => '6001']),
            self::HOST.'/v1.0/*' => Http::response(['status' => 'FINISHED']),
        ]);

        $this->runJob($publication);

        Http::assertSent(fn (HttpRequest $request) => str_ends_with(parse_url($request->url(), PHP_URL_PATH), '/4242/threads')
            && mb_strlen($request['text']) === 500
            && str_ends_with($request['text'], "\n\n#clinica")
            && ! str_contains($request['text'], '#saude'));
    }

    public function test_weekly_refresh_renews_threads_tokens_and_flags_expired_ones(): void
    {
        $ok = $this->connection(['last_refreshed_at' => now()->subDays(8), 'access_token' => 'token-velho']);
        $vencido = $this->connection(['external_account_id' => '5555', 'last_refreshed_at' => now()->subDays(8), 'access_token' => 'token-revogado']);
        $recente = $this->connection(['external_account_id' => '6666', 'last_refreshed_at' => now()->subDays(2)]);

        Http::fake([self::HOST.'/refresh_access_token*' => function (HttpRequest $request) {
            return str_contains($request->url(), 'token-revogado')
                ? Http::response(['error' => ['code' => 190, 'message' => 'expired']], 400)
                : Http::response(['access_token' => 'token-renovado', 'token_type' => 'bearer', 'expires_in' => 5183944]);
        }]);

        $this->artisan('social:refresh-tokens')->expectsOutputToContain('Renovadas: 1. Precisam reconectar: 1.')->assertSuccessful();

        $this->assertSame('token-renovado', $ok->fresh()->access_token);
        $this->assertTrue($ok->fresh()->expires_at->isAfter(now()->addDays(59)));
        $this->assertSame(SocialConnection::STATUS_EXPIRED, $vencido->fresh()->status);
        $this->assertSame('token-threads-ficticio', $recente->fresh()->access_token);
        Http::assertSentCount(2);
    }

    public function test_meta_page_connections_are_never_refreshed(): void
    {
        SocialConnection::create([
            'tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'platform' => 'facebook', 'provider' => 'meta',
            'external_account_id' => '555', 'access_token' => 'token-pagina', 'last_refreshed_at' => now()->subYear(),
        ]);

        $this->artisan('social:refresh-tokens')->expectsOutputToContain('Renovadas: 0.')->assertSuccessful();

        Http::assertNothingSent();
    }

    private function connection(array $attributes = []): SocialConnection
    {
        return SocialConnection::create(array_merge([
            'tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'platform' => 'threads', 'provider' => 'threads',
            'external_account_id' => '4242', 'handle' => '@clinicaficticia', 'access_token' => 'token-threads-ficticio',
            'expires_at' => now()->addDays(50), 'last_refreshed_at' => now(),
        ], $attributes));
    }

    /**
     * @return array{0: VideoPublication, 1: Video}
     */
    private function publication(): array
    {
        $staff = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Staff', 'email' => 'staff@ficticio.test', 'password' => 'secret12345', 'role' => 'admin']);
        $production = Production::create(['tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'title' => 'Campanha', 'status' => 'ready', 'created_by' => $staff->id]);
        Storage::disk('videos')->put($this->tenant->id.'/v.mp4', 'conteudo-ficticio');
        $video = Video::create([
            'tenant_id' => $this->tenant->id, 'production_id' => $production->id, 'title' => 'Corte', 'status' => 'ready',
            'source_disk' => 'videos', 'source_path' => $this->tenant->id.'/v.mp4', 'source_mime' => 'video/mp4', 'source_size' => 17,
            'sofi_hashtags' => ['clinica'],
        ]);
        $connection = $this->connection();

        $publication = VideoPublication::create([
            'tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'video_id' => $video->id,
            'driver' => 'threads', 'platform' => 'threads', 'social_connection_id' => $connection->id,
            'titulo' => 'Lancamento', 'plataformas' => ['threads'], 'status' => 'pendente',
        ]);

        return [$publication, $video];
    }

    private function runJob(VideoPublication $publication): PublishVideoToPlatform
    {
        $job = (new PublishVideoToPlatform($publication->id, $publication->tenant_id))->withFakeQueueInteractions();
        $job->handle(app(NativePublisherRegistry::class));

        return $job;
    }
}
