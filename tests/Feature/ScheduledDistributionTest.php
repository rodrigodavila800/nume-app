<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Cliente;
use App\Models\Pack;
use App\Models\PackPurchase;
use App\Models\Production;
use App\Models\SocialProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoPublication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduledDistributionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Cliente $cliente;
    private Video $video;
    private ApiKey $apiKey;
    private string $plainToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Produtora Agendamento',
            'slug' => 'produtora-agendamento',
            'status' => 'active',
            'subscription_expires_at' => now()->addYears(2),
        ]);

        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
        ]);

        $this->cliente = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente Agendamento',
            'email' => 'agendamento@cliente.com',
            'password' => bcrypt('Senha@Segura123'),
            'status' => 'active',
        ]);

        $pack = Pack::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pack Enterprise Agendamento',
            'slug' => 'pack-enterprise-agendamento',
            'price' => 800.00,
            'video_quantity' => 10,
            'status' => 'active',
        ]);

        PackPurchase::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'pack_id' => $pack->id,
            'amount' => 800.00,
            'status' => 'paid',
            'video_quantity' => 10,
            'videos_used' => 1,
            'paid_at' => now(),
        ]);

        SocialProfile::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'upload_post_username' => 'usr_sched_123',
            'connected' => true,
            'platforms_connected' => ['instagram', 'tiktok', 'youtube'],
        ]);

        $production = Production::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'created_by' => $user->id,
            'title' => 'Campanha Agendada',
            'status' => 'ready',
        ]);

        $this->video = Video::create([
            'tenant_id' => $this->tenant->id,
            'production_id' => $production->id,
            'title' => 'Vídeo para Agendar',
            'status' => 'ready',
            'video_url' => 'https://storage.nume.app/videos/scheduled-sample.mp4',
        ]);

        $tokenData = ApiKey::createToken($this->tenant, 'API Agendamento');
        $this->apiKey = $tokenData['apiKey'];
        $this->plainToken = $tokenData['plainTextToken'];
    }

    public function test_client_can_schedule_video_publication_via_portal(): void
    {
        $futureTime = now()->addDays(3)->setHour(18)->setMinute(0)->setSecond(0);

        $response = $this->actingAs($this->cliente, 'cliente')
            ->post(route('portal.videos.distribute', $this->video), [
                'video_url' => $this->video->video_url,
                'titulo' => 'Post Agendado para Quinta-Feira',
                'plataformas' => ['instagram', 'tiktok'],
                'scheduled_at' => $futureTime->format('Y-m-d\TH:i'),
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('video_publications', [
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'video_id' => $this->video->id,
            'titulo' => 'Post Agendado para Quinta-Feira',
            'status' => 'agendado',
        ]);

        $publication = VideoPublication::latest()->first();
        $this->assertNotNull($publication->scheduled_at);
        $this->assertEquals('agendado', $publication->status);

        // Vídeo não pode ser marcado como publicado antes da data
        $this->assertEquals('ready', $this->video->fresh()->status);
    }

    public function test_developer_can_schedule_publication_via_api(): void
    {
        $futureTime = now()->addHours(24);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->plainToken)
            ->postJson('/api/v1/social/publish', [
                'email' => $this->cliente->email,
                'video_url' => $this->video->video_url,
                'title' => 'Post via API Agendado',
                'platforms' => ['youtube', 'instagram'],
                'scheduled_at' => $futureTime->toIso8601String(),
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.status', 'agendado');
        $this->assertNotNull($response->json('data.scheduled_at'));

        $this->assertDatabaseHas('video_publications', [
            'tenant_id' => $this->tenant->id,
            'titulo' => 'Post via API Agendado',
            'status' => 'agendado',
        ]);
    }

    public function test_artisan_command_publishes_scheduled_videos_when_time_arrives(): void
    {
        // Publicação que já atingiu o horário (ex: agendada para 5 minutos atrás)
        $pastSchedule = VideoPublication::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'video_id' => $this->video->id,
            'titulo' => 'Vídeo com horário atingido',
            'plataformas' => ['instagram'],
            'status' => 'agendado',
            'scheduled_at' => now()->subMinutes(5),
            'resultado' => [
                'video_url' => $this->video->video_url,
            ],
        ]);

        $this->artisan('videos:publish-scheduled')
            ->expectsOutputToContain('publicações agendadas foram disparadas com sucesso')
            ->assertExitCode(0);

        $this->assertEquals('processando', $pastSchedule->fresh()->status);
        $this->assertNotNull($pastSchedule->fresh()->upload_post_request_id);
        $this->assertEquals('published', $this->video->fresh()->status);
    }

    public function test_artisan_command_ignores_future_scheduled_videos(): void
    {
        // Publicação futura (daqui a 4 dias)
        $futureSchedule = VideoPublication::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'video_id' => $this->video->id,
            'titulo' => 'Vídeo do Futuro',
            'plataformas' => ['tiktok'],
            'status' => 'agendado',
            'scheduled_at' => now()->addDays(4),
            'resultado' => [
                'video_url' => $this->video->video_url,
            ],
        ]);

        $this->artisan('videos:publish-scheduled')
            ->expectsOutputToContain('Nenhuma publicação agendada pendente no momento')
            ->assertExitCode(0);

        // Deve continuar como agendado
        $this->assertEquals('agendado', $futureSchedule->fresh()->status);
    }
}
