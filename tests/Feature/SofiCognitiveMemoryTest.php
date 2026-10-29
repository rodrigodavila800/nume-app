<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Lead;
use App\Models\Production;
use App\Models\Roteiro;
use App\Models\SofiConversation;
use App\Models\SofiMessage;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Ai\SofiMemoryDistillationService;
use App\Services\Crm\LeadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SofiCognitiveMemoryTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected Cliente $cliente;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Produtora Alpha', 'slug' => 'produtora-alpha']);
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->cliente = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Empresa Beta',
            'company_name' => 'Beta Corp',
            'email' => 'beta@example.com',
            'phone' => '+34600112233',
            'password' => bcrypt('secret123'),
            'brand_niche' => 'Tecnologia B2B',
            'target_audience' => 'CTOs e Gerentes de TI',
        ]);
    }

    public function test_script_rejection_adds_anti_pattern_to_memory(): void
    {
        $production = Production::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'title' => 'Vídeo Institucional Beta',
            'status' => 'script_ready',
            'created_by' => $this->user->id,
        ]);

        $roteiro = Roteiro::create([
            'tenant_id' => $this->tenant->id,
            'production_id' => $production->id,
            'created_by' => $this->user->id,
            'status' => 'submitted',
            'content' => 'Corpo teste do roteiro em texto.',
            'structure' => ['hook' => 'Gancho teste', 'body' => 'Corpo teste'],
        ]);

        $this->actingAs($this->cliente, 'cliente')
            ->post(route('portal.roteiros.reject', $roteiro), [
                'rejection_reason' => 'Não gostamos de linguagem informal nem de gírias modernas.',
            ])
            ->assertRedirect();

        $roteiro->refresh();
        $this->assertSame('rejected', $roteiro->status);

        // Verifica a memória cognitiva do cliente
        $memory = $this->cliente->aiMemory()->first();
        $this->assertNotNull($memory);
        $this->assertNotEmpty($memory->anti_patterns);
        $this->assertStringContainsString('Não gostamos de linguagem informal', $memory->anti_patterns[0]);
    }

    public function test_script_approval_adds_positive_pattern_and_exemplar(): void
    {
        $production = Production::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'title' => 'Vídeo Produto',
            'status' => 'script_ready',
            'created_by' => $this->user->id,
        ]);

        $roteiro = Roteiro::create([
            'tenant_id' => $this->tenant->id,
            'production_id' => $production->id,
            'created_by' => $this->user->id,
            'status' => 'submitted',
            'content' => 'Seu faturamento estagnou no último trimestre? Apresentação da solução...',
            'structure' => [
                'hook' => 'Seu faturamento estagnou no último trimestre?',
                'body' => 'Apresentação da solução...',
                'cta' => 'Agende uma demonstração no link.',
            ],
        ]);

        $this->actingAs($this->cliente, 'cliente')
            ->post(route('portal.roteiros.approve', $roteiro))
            ->assertRedirect();

        $roteiro->refresh();
        $this->assertSame('approved', $roteiro->status);

        $memory = $this->cliente->aiMemory()->first();
        $this->assertNotNull($memory);
        $this->assertNotEmpty($memory->positive_patterns);
        $this->assertNotEmpty($memory->few_shot_exemplars);
        $this->assertStringContainsString('faturamento estagnou', $memory->positive_patterns[0]);
    }

    public function test_crm_lead_loss_adds_crm_learning(): void
    {
        $lead = Lead::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'name' => 'Lead Beta',
            'email' => 'lead@beta.com',
            'source' => 'quote',
            'stage' => 'proposta',
        ]);

        $leadService = app(LeadService::class);
        $leadService->moveTo($lead, 'perdido', $this->user, 'Orçamento acima do teto estipulado pelo conselho.');

        $lead->refresh();
        $this->assertSame('perdido', $lead->stage);

        $memory = $this->cliente->aiMemory()->first();
        $this->assertNotNull($memory);
        $this->assertNotEmpty($memory->crm_learnings);
        $this->assertStringContainsString('acima do teto estipulado', $memory->crm_learnings[0]);
    }

    public function test_chat_messages_are_distilled_into_learnings(): void
    {
        $conversation = SofiConversation::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'title' => 'Dúvidas sobre entrega',
            'channel' => 'portal',
            'status' => 'active',
        ]);

        SofiMessage::create([
            'sofi_conversation_id' => $conversation->id,
            'sender' => 'user',
            'content' => 'Qual é o prazo exato de entrega após a gravação externa?',
        ]);

        SofiMessage::create([
            'sofi_conversation_id' => $conversation->id,
            'sender' => 'sofi',
            'content' => 'O prazo médio é de 5 dias úteis.',
        ]);

        $distillationService = app(SofiMemoryDistillationService::class);
        $distillationService->distillFromChat($conversation);

        $memory = $this->cliente->aiMemory()->first();
        $this->assertNotNull($memory);
        $this->assertNotEmpty($memory->chat_learnings);
        $this->assertStringContainsString('prazo exato de entrega', $memory->chat_learnings[0]);

        $promptFormatted = $memory->formatForPrompt();
        $this->assertStringContainsString('DÚVIDAS & TÓPICOS FREQUENTES', $promptFormatted);
    }
}
