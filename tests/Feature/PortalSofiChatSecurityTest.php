<?php

namespace Tests\Feature;

use App\Ai\Agents\SofiSupportChatAgent;
use App\Models\AiUsageLog;
use App\Models\Cliente;
use App\Models\Production;
use App\Models\SofiConversation;
use App\Models\SofiMessage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Chat da Sofi no portal: só o cliente logado, só as próprias conversas e produções, com limite de uso.
 * A IA fica simulada (falha) para a Sofi responder pelo motor heurístico, sem rede.
 */
class PortalSofiChatSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Cliente $cliente;

    private Cliente $vizinho;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Http::fake(['*' => Http::response([], 500)]);

        $this->tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        $this->cliente = Cliente::create(['tenant_id' => $this->tenant->id, 'name' => 'Cliente A', 'email' => 'a@ficticio.test', 'password' => bcrypt('secret123')]);
        $this->vizinho = Cliente::create(['tenant_id' => $this->tenant->id, 'name' => 'Cliente B', 'email' => 'b@ficticio.test', 'password' => bcrypt('secret123')]);
    }

    public function test_old_open_api_routes_no_longer_exist(): void
    {
        $this->postJson('/api/v1/sofi/chat', ['message' => 'oi', 'cliente_id' => $this->cliente->id])->assertNotFound();

        $conversation = $this->conversation($this->cliente);
        $this->getJson('/api/v1/sofi/conversations/'.$conversation->id.'/messages')->assertNotFound();
    }

    public function test_chat_requires_a_logged_in_cliente(): void
    {
        $this->postJson(route('portal.sofi.chat'), ['message' => 'oi'])->assertUnauthorized();
        $this->getJson(route('portal.sofi.messages', $this->conversation($this->cliente)->id))->assertUnauthorized();

        $this->assertSame(0, SofiMessage::withoutGlobalScopes()->count());
    }

    public function test_cliente_id_in_the_request_is_ignored(): void
    {
        $this->actingAs($this->cliente, 'cliente')
            ->postJson(route('portal.sofi.chat'), ['message' => 'Como anda meu video?', 'cliente_id' => $this->vizinho->id])
            ->assertOk();

        $this->assertSame([$this->cliente->id], SofiConversation::withoutGlobalScopes()->pluck('cliente_id')->all());
    }

    public function test_another_clientes_conversation_cannot_be_read_or_written(): void
    {
        $alheia = $this->conversation($this->vizinho);
        SofiMessage::create(['tenant_id' => $this->tenant->id, 'sofi_conversation_id' => $alheia->id, 'sender' => 'user', 'content' => 'segredo do vizinho']);

        $this->actingAs($this->cliente, 'cliente')
            ->getJson(route('portal.sofi.messages', $alheia->id))
            ->assertNotFound()
            ->assertDontSee('segredo do vizinho');

        $this->actingAs($this->cliente, 'cliente')
            ->postJson(route('portal.sofi.chat'), ['message' => 'oi', 'conversation_id' => $alheia->id])
            ->assertNotFound();

        $this->assertSame(1, SofiMessage::withoutGlobalScopes()->where('sofi_conversation_id', $alheia->id)->count());
    }

    public function test_another_clientes_production_never_reaches_the_prompt(): void
    {
        $staff = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Staff', 'email' => 'staff@ficticio.test', 'password' => 'secret12345', 'role' => 'admin']);
        $producaoAlheia = Production::create([
            'tenant_id' => $this->tenant->id, 'cliente_id' => $this->vizinho->id, 'title' => 'Lancamento secreto do vizinho',
            'status' => 'draft', 'created_by' => $staff->id,
        ]);

        $this->actingAs($this->cliente, 'cliente')
            ->postJson(route('portal.sofi.chat'), ['message' => 'Resuma a producao', 'production_id' => $producaoAlheia->id])
            ->assertNotFound();

        $this->assertSame(0, SofiConversation::withoutGlobalScopes()->count());
        Http::assertNotSent(fn ($request) => str_contains($request->body(), 'Lancamento secreto'));
    }

    public function test_own_conversation_history_is_returned(): void
    {
        $conversation = $this->conversation($this->cliente);
        SofiMessage::create(['tenant_id' => $this->tenant->id, 'sofi_conversation_id' => $conversation->id, 'sender' => 'user', 'content' => 'minha pergunta']);

        $this->actingAs($this->cliente, 'cliente')
            ->getJson(route('portal.sofi.messages', $conversation->id))
            ->assertOk()
            ->assertJsonPath('messages.0.content', 'minha pergunta');
    }

    public function test_chat_is_rate_limited_per_cliente(): void
    {
        config(['sofi.portal_chat_rate_limits.per_minute' => 2]);

        $this->actingAs($this->cliente, 'cliente');
        $this->postJson(route('portal.sofi.chat'), ['message' => 'um'])->assertOk();
        $this->postJson(route('portal.sofi.chat'), ['message' => 'dois'])->assertOk();
        $this->postJson(route('portal.sofi.chat'), ['message' => 'tres'])->assertStatus(429);
    }

    public function test_chat_uses_support_agent_and_records_usage_for_cliente(): void
    {
        config(['sofi.provider' => 'gemini', 'sofi.model' => null, 'sofi.keys.gemini' => 'chave-ficticia']);
        SofiSupportChatAgent::fake(['Suas faturas ficam na aba Orçamentos do portal.'])->preventStrayPrompts();

        $this->actingAs($this->cliente, 'cliente')
            ->postJson(route('portal.sofi.chat'), ['message' => 'Onde vejo minha fatura?'])
            ->assertOk()
            ->assertJsonPath('reply.content', 'Suas faturas ficam na aba Orçamentos do portal.')
            ->assertJsonPath('reply.metadata.source', 'sdk:gemini');

        SofiSupportChatAgent::assertPrompted(fn ($prompt) => $prompt->prompt === 'Onde vejo minha fatura?');

        $this->assertSame(
            1,
            AiUsageLog::forTenant($this->tenant->id)
                ->where('cliente_id', $this->cliente->id)
                ->where('agent', SofiSupportChatAgent::class)
                ->count()
        );
    }

    private function conversation(Cliente $cliente): SofiConversation
    {
        return SofiConversation::create(['tenant_id' => $cliente->tenant_id, 'cliente_id' => $cliente->id, 'title' => 'Conversa']);
    }
}
