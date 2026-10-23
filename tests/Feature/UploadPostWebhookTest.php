<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Production;
use App\Models\SocialProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoPublication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UploadPostWebhookTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Cliente $cliente;
    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Produtora Webhook Teste',
            'slug' => 'produtora-wh',
            'is_active' => true,
        ]);

        $this->staff = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Staff Editor',
            'email' => 'staff.wh@produtora.com',
            'password' => bcrypt('Senha@Segura123'),
            'role' => 'editor',
        ]);

        $this->cliente = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente Wh Test',
            'email' => 'cliente.wh@teste.com',
            'password' => bcrypt('Senha@Segura123'),
        ]);
    }

    public function test_webhook_updates_publication_to_completed_and_publishes_video(): void
    {
        $production = Production::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'created_by' => $this->staff->id,
            'title' => 'Campanha Webhook',
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'status' => 'approved',
        ]);

        $video = Video::create([
            'tenant_id' => $this->tenant->id,
            'production_id' => $production->id,
            'title' => 'Vídeo Campanha WH',
            'video_url' => 'https://storage.nume.app/videos/campanha-wh.mp4',
            'status' => 'ready',
        ]);

        $publication = VideoPublication::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'video_id' => $video->id,
            'upload_post_request_id' => 'req_test_123456',
            'titulo' => 'Post via Webhook',
            'plataformas' => ['instagram', 'tiktok'],
            'status' => 'processando',
        ]);

        $payload = [
            'request_id' => 'req_test_123456',
            'status' => 'completed',
            'results' => [
                'instagram' => ['status' => 'success', 'url' => 'https://instagram.com/p/xyz123'],
                'tiktok' => ['status' => 'success', 'url' => 'https://tiktok.com/@nume/video/456'],
            ],
        ];

        $response = $this->postJson(route('upload-post.webhook'), $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'processed');

        $this->assertEquals('publicado', $publication->fresh()->status);
        $this->assertNotNull($publication->fresh()->publicado_em);
        $this->assertEquals('published', $video->fresh()->status);
    }

    public function test_webhook_rejects_invalid_hmac_signature_when_secret_is_configured(): void
    {
        config(['upload_post.webhook_secret' => 'super_secret_webhook_key_123']);

        $payload = ['request_id' => 'req_invalid_sig', 'status' => 'completed'];

        $response = $this->postJson(route('upload-post.webhook'), $payload, [
            'X-Upload-Post-Signature' => 'invalid_signature_hash',
        ]);

        $response->assertStatus(401);
    }

    public function test_webhook_accepts_valid_hmac_signature(): void
    {
        $secret = 'super_secret_webhook_key_123';
        config(['upload_post.webhook_secret' => $secret]);

        $data = ['request_id' => 'req_valid_sig', 'status' => 'completed'];
        $rawContent = json_encode($data);
        $validSignature = hash_hmac('sha256', $rawContent, $secret);

        $response = $this->call(
            'POST',
            route('upload-post.webhook'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_UPLOAD_POST_SIGNATURE' => $validSignature,
            ],
            $rawContent
        );

        $response->assertStatus(200);
    }

    public function test_webhook_updates_account_connection_status(): void
    {
        $profile = SocialProfile::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'upload_post_username' => 'user_uuid_webhook_profile',
            'connected' => false,
            'platforms_connected' => [],
        ]);

        $payload = [
            'user' => 'user_uuid_webhook_profile',
            'social_accounts' => [
                'instagram' => ['connected' => true, 'handle' => '@marca.oficial'],
                'tiktok' => ['connected' => true, 'handle' => '@marca.oficial'],
            ],
        ];

        $response = $this->postJson(route('upload-post.webhook'), $payload);

        $response->assertStatus(200);
        $this->assertTrue($profile->fresh()->connected);
        $this->assertEquals(['instagram', 'tiktok'], $profile->fresh()->platforms_connected);
    }
}
