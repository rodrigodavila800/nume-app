<?php

namespace Tests\Feature;

use App\Mcp\Servers\NumeOpsServer;
use App\Mcp\Tools\LeadsByStageTool;
use App\Mcp\Tools\OpenSupportTicketsTool;
use App\Mcp\Tools\OverviewTool;
use App\Mcp\Tools\PendingApprovalsTool;
use App\Mcp\Tools\QuoteRequestsTool;
use App\Mcp\Tools\ScheduleTool;
use App\Models\ApiKey;
use App\Models\Cliente;
use App\Models\Lead;
use App\Models\Production;
use App\Models\Quote;
use App\Models\Roteiro;
use App\Models\SupportTicket;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VideoPublication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpServerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Tenant $otherTenant;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Produtora MCP A',
            'slug' => 'produtora-mcp-a',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $this->otherTenant = Tenant::create([
            'name' => 'Produtora MCP B',
            'slug' => 'produtora-mcp-b',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $created = ApiKey::createToken($this->tenant, 'Chave MCP Teste');
        $this->token = $created['plainTextToken'];
    }

    public function test_mcp_endpoint_requires_valid_api_key(): void
    {
        $this->postJson('/api/v1/mcp', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ])->assertStatus(401);

        $this->withHeader('Authorization', 'Bearer token_invalido')
            ->postJson('/api/v1/mcp', [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'tools/list',
            ])->assertStatus(401);
    }

    public function test_authenticated_mcp_lists_six_read_only_tools(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->postJson('/api/v1/mcp', [
                'jsonrpc' => '2.0',
                'id' => 1,
                'method' => 'tools/list',
            ]);

        $response->assertOk();
        $tools = collect($response->json('result.tools'));

        $this->assertSame(
            ['overview', 'pending_approvals', 'schedule', 'quote_requests', 'leads_by_stage', 'open_support_tickets'],
            $tools->pluck('name')->all()
        );

        foreach ($tools as $tool) {
            $this->assertTrue($tool['annotations']['readOnlyHint'] ?? false);
        }
    }

    public function test_tools_return_only_authenticated_producer_data(): void
    {
        $userA = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Editor A',
            'email' => 'editora@mcp.test',
            'password' => 'secret12345',
            'role' => 'editor',
        ]);
        $userB = User::create([
            'tenant_id' => $this->otherTenant->id,
            'name' => 'Editor B',
            'email' => 'editorb@mcp.test',
            'password' => 'secret12345',
            'role' => 'editor',
        ]);

        $clienteA = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente A',
            'email' => 'a@mcp.test',
            'password' => 'secret12345',
            'company_name' => 'Empresa A',
        ]);
        $clienteB = Cliente::create([
            'tenant_id' => $this->otherTenant->id,
            'name' => 'Cliente B Secreto',
            'email' => 'b@mcp.test',
            'password' => 'secret12345',
            'company_name' => 'Empresa B Secreta',
        ]);

        $prodA = Production::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $clienteA->id,
            'created_by' => $userA->id,
            'title' => 'Campanha Outono A',
            'status' => 'in_production',
            'target_publication_date' => now()->addDays(3)->toDateString(),
        ]);
        $prodB = Production::withoutGlobalScopes()->create([
            'tenant_id' => $this->otherTenant->id,
            'cliente_id' => $clienteB->id,
            'created_by' => $userB->id,
            'title' => 'Campanha Secreta B',
            'status' => 'in_production',
            'target_publication_date' => now()->addDays(2)->toDateString(),
        ]);

        Roteiro::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'production_id' => $prodA->id,
            'created_by' => $userA->id,
            'version' => 1,
            'content' => 'Roteiro A',
            'status' => 'submitted',
            'submitted_at' => now()->subHours(5),
        ]);
        Roteiro::withoutGlobalScopes()->create([
            'tenant_id' => $this->otherTenant->id,
            'production_id' => $prodB->id,
            'created_by' => $userB->id,
            'version' => 1,
            'content' => 'Roteiro Secreto B',
            'status' => 'submitted',
            'submitted_at' => now()->subHours(2),
        ]);

        VideoPublication::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $clienteA->id,
            'titulo' => 'Reels Agendado A',
            'plataformas' => ['instagram'],
            'status' => 'agendado',
            'scheduled_at' => now()->addDay(),
        ]);
        VideoPublication::withoutGlobalScopes()->create([
            'tenant_id' => $this->otherTenant->id,
            'cliente_id' => $clienteB->id,
            'titulo' => 'Reels Secreto B',
            'plataformas' => ['youtube'],
            'status' => 'agendado',
            'scheduled_at' => now()->addDay(),
        ]);

        Quote::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $clienteA->id,
            'number' => 'ORC-A-001',
            'project_title' => 'Pedido de Orçamento A',
            'client_name' => 'Cliente A',
            'status' => Quote::STATUS_REQUESTED,
            'kind' => Quote::KIND_PRODUCTION,
            'inputs' => ['video_quantity' => 4],
            'calculation' => [],
            'valid_until' => now()->addDays(15),
        ]);
        Quote::withoutGlobalScopes()->create([
            'tenant_id' => $this->otherTenant->id,
            'cliente_id' => $clienteB->id,
            'number' => 'ORC-B-001',
            'project_title' => 'Pedido Secreto B',
            'client_name' => 'Cliente B Secreto',
            'status' => Quote::STATUS_REQUESTED,
            'kind' => Quote::KIND_PRODUCTION,
            'inputs' => ['video_quantity' => 8],
            'calculation' => [],
            'valid_until' => now()->addDays(15),
        ]);

        $leadA = Lead::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Lead Qualificado A',
            'source' => 'quote_request',
        ]);
        $leadA->forceFill(['stage' => 'qualificado'])->save();

        $leadB = Lead::withoutGlobalScopes()->create([
            'tenant_id' => $this->otherTenant->id,
            'name' => 'Lead Secreto B',
            'source' => 'quote_request',
        ]);
        $leadB->forceFill(['stage' => 'qualificado'])->save();

        SupportTicket::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $clienteA->id,
            'subject' => 'Dúvida Fatura A',
            'category' => 'financeiro',
            'priority' => 'normal',
        ]);
        SupportTicket::withoutGlobalScopes()->create([
            'tenant_id' => $this->otherTenant->id,
            'cliente_id' => $clienteB->id,
            'subject' => 'Chamado Secreto B',
            'category' => 'financeiro',
            'priority' => 'alta',
        ]);

        app()->instance('current_tenant', $this->tenant);

        NumeOpsServer::tool(OverviewTool::class)
            ->assertOk()
            ->assertSee('Campanha Outono A')
            ->assertDontSee('Campanha Secreta B');

        NumeOpsServer::tool(PendingApprovalsTool::class)
            ->assertOk()
            ->assertSee('Campanha Outono A')
            ->assertDontSee('Campanha Secreta B');

        NumeOpsServer::tool(ScheduleTool::class)
            ->assertOk()
            ->assertSee('Reels Agendado A')
            ->assertDontSee('Reels Secreto B');

        NumeOpsServer::tool(QuoteRequestsTool::class)
            ->assertOk()
            ->assertSee('Pedido de Orçamento A')
            ->assertDontSee('Pedido Secreto B');

        NumeOpsServer::tool(LeadsByStageTool::class, ['stage' => 'qualificado'])
            ->assertOk()
            ->assertSee('Lead Qualificado A')
            ->assertDontSee('Lead Secreto B');

        NumeOpsServer::tool(OpenSupportTicketsTool::class)
            ->assertOk()
            ->assertSee('Dúvida Fatura A')
            ->assertDontSee('Chamado Secreto B');
    }
}
