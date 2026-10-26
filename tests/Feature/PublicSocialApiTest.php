<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Cliente;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoPublication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSocialApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private ApiKey $apiKey;
    private string $plainToken;
    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Produtora API Partner',
            'slug' => 'produtora-api-partner',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $tokenData = ApiKey::createToken($this->tenant, 'Chave de Testes API');
        $this->apiKey = $tokenData['apiKey'];
        $this->plainToken = $tokenData['plainTextToken'];

        $this->cliente = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Alejandro Martínez',
            'email' => 'alejandro@empresa.com',
            'password' => bcrypt('secret123'),
            'status' => 'active',
        ]);

        $pack = \App\Models\Pack::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pack B2B Enterprise',
            'slug' => 'pack-b2b',
            'price' => 1000.00,
            'video_quantity' => 20,
            'status' => 'active',
        ]);

        \App\Models\PackPurchase::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'pack_id' => $pack->id,
            'amount' => 1000.00,
            'status' => 'paid',
            'video_quantity' => 20,
            'videos_used' => 2,
            'paid_at' => now(),
        ]);
    }

    public function test_public_docs_and_openapi_json_are_accessible_without_auth(): void
    {
        $docsResponse = $this->get('/api/v1/docs');
        $docsResponse->assertOk();
        $docsResponse->assertSee('Nume Social Engine Developer API');

        $jsonResponse = $this->get('/api/v1/docs.json');
        $jsonResponse->assertOk();
        $jsonResponse->assertJsonPath('openapi', '3.0.0');
        $jsonResponse->assertJsonPath('info.title', 'Nume Social Engine Public API');
    }

    public function test_unauthenticated_requests_are_rejected(): void
    {
        $response = $this->postJson('/api/v1/social/connect-url', [
            'email' => 'semtoken@teste.com',
        ]);

        $response->assertStatus(401);
        $response->assertJsonPath('error', 'Unauthorized');
    }

    public function test_invalid_or_revoked_token_is_rejected(): void
    {
        // Token inválido
        $badResponse = $this->withHeader('Authorization', 'Bearer token_invalido')
            ->postJson('/api/v1/social/connect-url', ['email' => 'test@test.com']);
        $badResponse->assertStatus(401);

        // Token revogado
        $this->apiKey->revoke();
        $revokedResponse = $this->withHeader('Authorization', 'Bearer ' . $this->plainToken)
            ->postJson('/api/v1/social/connect-url', ['email' => 'test@test.com']);
        $revokedResponse->assertStatus(401);
    }

    public function test_inactive_tenant_token_is_forbidden(): void
    {
        $this->tenant->update(['status' => 'suspended']);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->plainToken)
            ->postJson('/api/v1/social/connect-url', ['email' => 'test@test.com']);

        $response->assertStatus(403);
        $response->assertJsonPath('error', 'Forbidden');
    }

    public function test_generate_connect_url_via_api(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->plainToken)
            ->postJson('/api/v1/social/connect-url', [
                'email' => 'novo.cliente@empresa.com',
                'name' => 'Novo Cliente B2B',
                'redirect_url' => 'https://meuerp.com/social-return',
            ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $this->assertNotEmpty($response->json('data.connect_url'));
        $this->assertEquals('novo.cliente@empresa.com', $response->json('data.email'));

        $this->assertDatabaseHas('clientes', [
            'tenant_id' => $this->tenant->id,
            'email' => 'novo.cliente@empresa.com',
        ]);
    }

    private function productionVideo(Cliente $cliente, array $attributes = []): Video
    {
        $staff = User::firstOrCreate(['email' => 'staff@ficticio.test'], ['tenant_id' => $this->tenant->id, 'name' => 'Staff', 'password' => 'secret12345', 'role' => 'admin']);
        $production = \App\Models\Production::create(['tenant_id' => $this->tenant->id, 'cliente_id' => $cliente->id, 'title' => 'Campanha', 'status' => 'ready', 'created_by' => $staff->id]);

        return Video::create(array_merge([
            'tenant_id' => $this->tenant->id, 'production_id' => $production->id, 'title' => 'Corte final', 'status' => 'ready',
            'video_url' => 'https://cdn.ficticio.test/campanha-b2b.mp4',
        ], $attributes));
    }

    private function api(array $payload)
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->plainToken)->postJson('/api/v1/social/publish', $payload);
    }

    public function test_publish_video_via_api(): void
    {
        $video = $this->productionVideo($this->cliente);

        $response = $this->api([
            'email' => $this->cliente->email,
            'video_id' => $video->id,
            'video_url' => 'https://cdn.ficticio.test/campanha-b2b.mp4',
            'title' => 'Campanha de Lançamento NUME #viral',
            'platforms' => ['instagram', 'youtube'],
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('data.video_id', $video->id);
        $this->assertNotNull($response->json('data.publications.0.publication_id'));

        $this->assertDatabaseHas('video_publications', [
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'video_id' => $video->id,
            'titulo' => 'Campanha de Lançamento NUME #viral',
        ]);
    }

    public function test_api_sends_hosted_video_natively_through_the_upload_queue(): void
    {
        \Illuminate\Support\Facades\Storage::fake('videos');
        \Illuminate\Support\Facades\Queue::fake();
        config(['social.routing.youtube' => 'native']);
        \Illuminate\Support\Facades\Storage::disk('videos')->put('v.mp4', 'conteudo-ficticio');
        $video = $this->productionVideo($this->cliente, ['video_url' => null, 'source_disk' => 'videos', 'source_path' => 'v.mp4', 'source_mime' => 'video/mp4', 'source_size' => 17]);
        \App\Models\SocialConnection::create([
            'tenant_id' => $this->tenant->id, 'cliente_id' => $this->cliente->id, 'platform' => 'youtube', 'provider' => 'google',
            'external_account_id' => 'UC-ficticio', 'access_token' => 'token-ficticio', 'refresh_token' => 'refresh-ficticio', 'expires_at' => now()->addHour(),
        ]);

        // A URL digitada é ignorada: vídeo hospedado sai do próprio arquivo.
        $this->api(['client_id' => $this->cliente->id, 'video_id' => $video->id, 'video_url' => 'https://evil.example/outro.mp4', 'title' => 'Nativo', 'platforms' => ['youtube']])
            ->assertStatus(201)
            ->assertJsonPath('data.requires_portal', []);

        $publication = VideoPublication::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->sole();
        $this->assertSame('youtube', $publication->driver);
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\PublishVideoToPlatform::class);
    }

    public function test_api_cannot_publish_a_video_of_another_client(): void
    {
        $outro = Cliente::create(['tenant_id' => $this->tenant->id, 'name' => 'Outro', 'email' => 'outro@empresa.com', 'password' => bcrypt('secret123')]);
        $video = $this->productionVideo($outro);

        $this->api(['email' => $this->cliente->email, 'video_id' => $video->id, 'title' => 'X', 'platforms' => ['instagram']])
            ->assertStatus(404);
        $this->assertSame(0, VideoPublication::withoutGlobalScopes()->count());
    }

    public function test_api_requires_a_video_id(): void
    {
        $this->api(['email' => $this->cliente->email, 'video_url' => 'https://cdn.ficticio.test/x.mp4', 'title' => 'X', 'platforms' => ['instagram']])
            ->assertStatus(422)->assertJsonValidationErrors('video_id');
    }

    public function test_get_publication_status_via_api(): void
    {
        $publication = VideoPublication::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'upload_post_request_id' => 'req_b2b_99182',
            'titulo' => 'Vídeo Status Check',
            'plataformas' => ['instagram'],
            'status' => 'processando',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->plainToken)
            ->getJson("/api/v1/social/publications/{$publication->id}");

        $response->assertOk();
        $response->assertJsonPath('data.publication_id', $publication->id);
        $response->assertJsonPath('data.title', 'Vídeo Status Check');
    }

    public function test_get_client_profile_via_api(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->plainToken)
            ->getJson("/api/v1/social/profiles/{$this->cliente->id}");

        $response->assertOk();
        $response->assertJsonPath('data.client_id', $this->cliente->id);
        $response->assertJsonPath('data.email', $this->cliente->email);
        $this->assertArrayHasKey('quota', $response->json('data'));
    }

    public function test_quota_exhausted_rejects_publish_via_api(): void
    {
        // Cria cliente sem pack nem addon
        $clientSemPlano = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente Free',
            'email' => 'free@cliente.com',
            'password' => bcrypt('secret123'),
            'status' => 'active',
        ]);

        $response = $this->api([
            'email' => $clientSemPlano->email,
            'video_id' => $this->productionVideo($clientSemPlano)->id,
            'title' => 'Vídeo Tentativa Sem Quota',
            'platforms' => ['instagram'],
        ]);

        $response->assertStatus(403);
        $response->assertJsonPath('error', 'Quota Exceeded');
    }

    public function test_can_download_nocode_integration_templates(): void
    {
        $makeResponse = $this->get('/api/v1/integrations/make');
        $makeResponse->assertOk();
        $makeResponse->assertHeader('content-type', 'application/json');

        $n8nResponse = $this->get('/api/v1/integrations/n8n');
        $n8nResponse->assertOk();
        $n8nResponse->assertHeader('content-type', 'application/json');
    }
}
