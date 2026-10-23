<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Cliente;
use App\Models\Production;
use App\Models\Roteiro;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoPublication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAssistantApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private Cliente $clienteA;
    private Cliente $clienteB;
    private ApiKey $apiKeyA;
    private string $rawKeyA = 'nume_live_alpha1234567890abcdef12345678';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name' => 'Produtora Alpha',
            'slug' => 'produtora-alpha',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Produtora Beta',
            'slug' => 'produtora-beta',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $this->userA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Staff Alpha',
            'email' => 'alpha@nume.test',
            'password' => bcrypt('secret123'),
        ]);

        $this->clienteA = Cliente::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Cliente Alpha',
            'email' => 'cliente@alpha.test',
            'company_name' => 'Alpha Motors',
            'password' => bcrypt('secret123'),
        ]);

        $this->clienteB = Cliente::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Cliente Beta',
            'email' => 'cliente@beta.test',
            'company_name' => 'Beta Seguros',
            'password' => bcrypt('secret123'),
        ]);

        $this->apiKeyA = ApiKey::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->userA->id,
            'name' => 'ChatGPT Sofi Ops Connector',
            'key_hash' => hash('sha256', $this->rawKeyA),
            'prefix' => 'nume_live_alph',
            'is_active' => true,
        ]);
    }

    public function test_public_assistant_endpoints_return_openapi_and_instructions(): void
    {
        $schemaResponse = $this->getJson(route('api.assistant.openapi'));
        $schemaResponse->assertStatus(200);
        $schemaResponse->assertJsonPath('openapi', '3.1.0');
        $schemaResponse->assertJsonPath('info.title', 'NUME Sofi Ops Assistant API');

        $instructionsResponse = $this->getJson(route('api.assistant.instructions'));
        $instructionsResponse->assertStatus(200);
        $instructionsResponse->assertJsonPath('assistant_name', 'Sofi Ops Copilot');
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson(route('api.assistant.overview'));

        $response->assertStatus(401);
    }

    public function test_assistant_can_fetch_operational_overview(): void
    {
        $production = Production::create([
            'tenant_id' => $this->tenantA->id,
            'cliente_id' => $this->clienteA->id,
            'created_by' => $this->userA->id,
            'title' => 'Vídeo Campanha Alpha',
            'status' => 'in_production',
            'target_publication_date' => now()->addDays(3),
        ]);

        Roteiro::create([
            'tenant_id' => $this->tenantA->id,
            'production_id' => $production->id,
            'created_by' => $this->userA->id,
            'content' => 'Roteiro de teste',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->rawKeyA}",
        ])->getJson(route('api.assistant.overview'));

        $response->assertStatus(200);
        $response->assertJsonPath('summary.pending_approvals', 1);
        $response->assertJsonPath('summary.active_productions', 1);
        $response->assertJsonPath('upcoming_deadlines.0.title', 'Vídeo Campanha Alpha');
    }

    public function test_assistant_can_fetch_pending_approvals_with_magic_links(): void
    {
        $production = Production::create([
            'tenant_id' => $this->tenantA->id,
            'cliente_id' => $this->clienteA->id,
            'created_by' => $this->userA->id,
            'title' => 'Reels de Conversão Alpha',
            'status' => 'in_production',
        ]);

        $roteiro = Roteiro::create([
            'tenant_id' => $this->tenantA->id,
            'production_id' => $production->id,
            'created_by' => $this->userA->id,
            'content' => 'Roteiro para aprovar',
            'status' => 'submitted',
            'submitted_at' => now()->subHours(10),
        ]);

        $response = $this->withHeaders([
            'X-API-Key' => $this->rawKeyA,
        ])->getJson(route('api.assistant.pending_approvals'));

        $response->assertStatus(200);
        $response->assertJsonPath('total_pending', 1);
        $response->assertJsonPath('items.0.production_title', 'Reels de Conversão Alpha');
        $this->assertStringContainsString('magic/roteiros', $response->json('items.0.magic_approval_url'));
        $this->assertStringContainsString('api.whatsapp.com', $response->json('items.0.whatsapp_share_url'));
    }

    public function test_tenant_isolation_prevents_leaking_data(): void
    {
        // Criação no Tenant B (não deve aparecer para a API Key do Tenant A)
        $productionB = Production::create([
            'tenant_id' => $this->tenantB->id,
            'cliente_id' => $this->clienteB->id,
            'created_by' => $this->userA->id,
            'title' => 'Produção Confidencial B',
            'status' => 'in_production',
        ]);

        Roteiro::create([
            'tenant_id' => $this->tenantB->id,
            'production_id' => $productionB->id,
            'created_by' => $this->userA->id,
            'content' => 'Roteiro Secreto B',
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => "Bearer {$this->rawKeyA}",
        ])->getJson(route('api.assistant.pending_approvals'));

        $response->assertStatus(200);
        $response->assertJsonPath('total_pending', 0);
    }
}
