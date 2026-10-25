<?php

namespace Tests\Feature;

use App\Jobs\PublishVideoToPlatform;
use App\Models\Cliente;
use App\Models\PackPurchase;
use App\Models\Production;
use App\Models\SocialConnection;
use App\Models\SocialProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoPublication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Disparo pelo portal, pelo painel e pelo agendador com YouTube nativo + Upload-Post (simulado, sem chave).
 * Dados fictícios, disco falso, fila falsa, sem rede.
 */
class NativeDistributionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private Cliente $cliente;

    private Production $production;

    private Video $video;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('videos');
        Queue::fake();
        Http::preventStrayRequests();
        config(['upload_post.api_key' => null, 'social.routing.youtube' => 'native']);

        $this->tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        $this->staff = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Staff', 'email' => 'staff@ficticio.test', 'password' => 'secret12345', 'role' => 'admin']);
        $this->cliente = Cliente::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Cliente A', 'email' => 'a@ficticio.test', 'password' => bcrypt('secret123'),
            'brand_niche' => 'Saude', 'website' => 'https://clinica-ficticia.test',
        ]);
        PackPurchase::create([
            'tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'status' => 'paid',
            'amount' => 500, 'video_quantity' => 5, 'videos_used' => 0,
        ]);
        $this->production = Production::create(['tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'title' => 'Campanha', 'status' => 'ready', 'created_by' => $this->staff->id]);

        $path = $this->tenant->id.'/video-ficticio.mp4';
        Storage::disk('videos')->put($path, 'conteudo-ficticio');
        $this->video = Video::create([
            'tenant_id' => $this->tenant->id, 'production_id' => $this->production->id, 'title' => 'Corte Final', 'status' => 'ready',
            'source_disk' => 'videos', 'source_path' => $path, 'source_mime' => 'video/mp4', 'source_size' => 17,
        ]);
    }

    public function test_portal_sends_connected_youtube_natively_and_the_rest_through_upload_post(): void
    {
        $connection = $this->connectYouTube();

        $this->actingAs($this->cliente, 'cliente')
            ->postJson(route('portal.videos.distribute', $this->video), [
                'titulo' => 'Lancamento',
                'plataformas' => ['youtube', 'instagram'],
                'video_url' => 'https://atacante.test/outro.mp4',
            ])
            ->assertStatus(202)
            ->assertJsonCount(2, 'publications');

        $native = VideoPublication::forTenant($this->tenant->id)->where('driver', 'youtube')->sole();
        $this->assertSame('pendente', $native->status);
        $this->assertSame('youtube', $native->platform);
        $this->assertSame($connection->id, $native->social_connection_id);
        $this->assertSame(['youtube'], $native->plataformas);

        $uploadPost = VideoPublication::forTenant($this->tenant->id)->where('driver', 'upload_post')->sole();
        $this->assertSame(['instagram'], $uploadPost->plataformas);
        $this->assertStringNotContainsString('atacante.test', json_encode($uploadPost->resultado));

        Queue::assertPushed(PublishVideoToPlatform::class, fn (PublishVideoToPlatform $job) => $job->publicationId === $native->id
            && $job->tenantId === $this->tenant->id
            && $job->connection === 'social-uploads');
        Queue::assertPushed(PublishVideoToPlatform::class, 1);

        // Um envio com duas redes continua valendo uma unidade da cota.
        $native->update(['status' => 'processando']);
        $this->assertSame(1, $this->cliente->fresh()->getDistributionsUsedCount());
    }

    public function test_without_a_connected_channel_youtube_keeps_going_through_upload_post(): void
    {
        SocialProfile::create([
            'tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'upload_post_username' => 'perfil-ficticio',
            'connected' => true, 'platforms_connected' => ['youtube'],
        ]);

        $this->actingAs($this->cliente, 'cliente')
            ->postJson(route('portal.videos.distribute', $this->video), ['titulo' => 'Lancamento', 'plataformas' => ['youtube']])
            ->assertStatus(202);

        $this->assertSame(['youtube'], VideoPublication::forTenant($this->tenant->id)->sole()->plataformas);
        $this->assertSame('upload_post', VideoPublication::forTenant($this->tenant->id)->sole()->driver);
        Queue::assertNothingPushed();
    }

    public function test_escrow_blocks_native_publishing_from_the_portal(): void
    {
        $this->connectYouTube();
        $purchase = PackPurchase::create([
            'tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'status' => 'paid',
            'amount' => 500, 'video_quantity' => 5, 'videos_used' => 0,
            'billing_mode' => 'split_50_50', 'balance_amount' => 500, 'balance_status' => 'pending',
        ]);
        $this->production->update(['pack_purchase_id' => $purchase->id]);

        $this->actingAs($this->cliente, 'cliente')
            ->postJson(route('portal.videos.distribute', $this->video), ['titulo' => 'Lancamento', 'plataformas' => ['youtube']])
            ->assertForbidden();

        $this->assertSame(0, VideoPublication::forTenant($this->tenant->id)->count());
        Queue::assertNothingPushed();
    }

    public function test_cliente_without_any_connected_network_cannot_publish(): void
    {
        $this->actingAs($this->cliente, 'cliente')
            ->postJson(route('portal.videos.distribute', $this->video), ['titulo' => 'Lancamento', 'plataformas' => ['youtube']])
            ->assertForbidden();

        $this->connectYouTube()->update(['status' => SocialConnection::STATUS_REVOKED]);

        $this->actingAs($this->cliente, 'cliente')
            ->postJson(route('portal.videos.distribute', $this->video), ['titulo' => 'Lancamento', 'plataformas' => ['youtube']])
            ->assertForbidden();

        $this->assertSame(0, VideoPublication::forTenant($this->tenant->id)->count());
    }

    public function test_link_only_video_never_goes_native(): void
    {
        $this->connectYouTube();
        $link = Video::create([
            'tenant_id' => $this->tenant->id, 'production_id' => $this->production->id, 'title' => 'So link', 'status' => 'ready',
            'video_url' => 'https://cdn.ficticio.test/video.mp4',
        ]);

        $this->actingAs($this->cliente, 'cliente')
            ->postJson(route('portal.videos.distribute', $link), [
                'titulo' => 'Lancamento', 'plataformas' => ['youtube'], 'video_url' => 'https://cdn.ficticio.test/video.mp4',
            ])
            ->assertStatus(202);

        $this->assertSame('upload_post', VideoPublication::forTenant($this->tenant->id)->sole()->driver);
        Queue::assertNothingPushed();
    }

    public function test_staff_panel_uses_the_same_split(): void
    {
        $this->connectYouTube();

        $this->actingAs($this->staff)
            ->post(route('videos.distribute', [$this->production, $this->video]), ['plataformas' => ['youtube', 'tiktok']])
            ->assertRedirect()
            ->assertSessionHas('success');

        $native = VideoPublication::forTenant($this->tenant->id)->where('driver', 'youtube')->sole();
        $this->assertSame($this->staff->id, $native->user_id);
        $this->assertSame(['tiktok'], VideoPublication::forTenant($this->tenant->id)->where('driver', 'upload_post')->sole()->plataformas);
        Queue::assertPushed(PublishVideoToPlatform::class, 1);
    }

    public function test_scheduled_native_publication_is_dispatched_once_when_due(): void
    {
        $this->connectYouTube();

        $this->actingAs($this->cliente, 'cliente')
            ->postJson(route('portal.videos.distribute', $this->video), [
                'titulo' => 'Lancamento', 'plataformas' => ['youtube', 'instagram'],
                'scheduled_at' => now()->addHour()->format('Y-m-d\TH:i'),
            ])
            ->assertStatus(202);

        $native = VideoPublication::forTenant($this->tenant->id)->where('driver', 'youtube')->sole();
        $this->assertSame('agendado', $native->status);
        Queue::assertNothingPushed();

        $this->artisan('videos:publish-scheduled')->assertSuccessful();
        Queue::assertNothingPushed();

        $this->travel(2)->hours();
        $this->artisan('videos:publish-scheduled')->assertSuccessful();
        $this->artisan('videos:publish-scheduled')->assertSuccessful();

        Queue::assertPushed(PublishVideoToPlatform::class, 1);
        $this->assertSame('pendente', $native->fresh()->status);

        // A linha do Upload-Post segue o caminho antigo e não toca na nativa.
        $uploadPost = VideoPublication::forTenant($this->tenant->id)->where('driver', 'upload_post')->sole();
        $this->assertSame('processando', $uploadPost->status);
        $this->assertSame(['instagram'], $uploadPost->plataformas);
    }

    private function connectYouTube(): SocialConnection
    {
        return SocialConnection::create([
            'tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'platform' => 'youtube', 'provider' => 'google',
            'external_account_id' => 'UC-canal-ficticio', 'access_token' => 'ya29.ficticio', 'refresh_token' => '1//ficticio',
            'expires_at' => now()->addHour(),
        ]);
    }
}
