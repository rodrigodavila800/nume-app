<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\SupportTicket;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Suporte: cliente abre e acompanha; equipe responde, anota e muda status. Dados fictícios.
 */
class SupportTicketsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $editor;

    private Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        $this->editor = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Editora', 'email' => 'editora@ficticio.test', 'password' => 'secret12345', 'role' => 'editor']);
        $this->cliente = Cliente::create(['tenant_id' => $this->tenant->id, 'name' => 'Ana', 'email' => 'ana@ficticio.test', 'password' => 'secret12345']);
    }

    private function openTicket(): SupportTicket
    {
        $this->actingAs($this->cliente, 'cliente')->post(route('portal.support.store'), [
            'subject' => 'Fatura com NIF errado', 'category' => 'financeiro', 'body' => 'O NIF saiu com um dígito trocado.',
        ])->assertRedirect();

        return SupportTicket::forTenant($this->tenant->id)->sole();
    }

    public function test_client_opens_a_ticket_and_the_team_sees_it(): void
    {
        $ticket = $this->openTicket();
        $this->assertSame('aberto', $ticket->status);
        $this->assertSame('portal', $ticket->source);

        $this->actingAs($this->editor)->get(route('support.index'))->assertOk()->assertSee('Fatura com NIF errado');
    }

    public function test_internal_notes_never_reach_the_client(): void
    {
        $ticket = $this->openTicket();

        $this->actingAs($this->editor)->post(route('support.reply', $ticket), ['body' => 'Nota: conferir com a gestoria', 'internal' => '1']);
        $this->assertSame('aberto', $ticket->fresh()->status, 'nota interna não muda status');

        $this->actingAs($this->editor)->post(route('support.reply', $ticket), ['body' => 'Corrigimos e reenviamos a fatura.']);
        $this->assertSame('aguardando_cliente', $ticket->fresh()->status);
        $this->assertSame($this->editor->id, $ticket->fresh()->assigned_to);

        $this->actingAs($this->cliente, 'cliente')->get(route('portal.support.show', $ticket->id))
            ->assertOk()
            ->assertSee('Corrigimos e reenviamos a fatura.')
            ->assertDontSee('conferir com a gestoria');
    }

    public function test_client_reply_reopens_and_closed_ticket_refuses_reply(): void
    {
        $ticket = $this->openTicket();
        $this->actingAs($this->editor)->patch(route('support.update', $ticket), ['status' => 'resolvido', 'priority' => 'alta']);
        $this->assertNotNull($ticket->fresh()->resolved_at);

        $this->actingAs($this->cliente, 'cliente')->post(route('portal.support.reply', $ticket->id), ['body' => 'Ainda está errado.']);
        $this->assertSame('aberto', $ticket->fresh()->status);

        $this->actingAs($this->editor)->patch(route('support.update', $ticket), ['status' => 'fechado', 'priority' => 'alta']);
        $this->actingAs($this->cliente, 'cliente')->post(route('portal.support.reply', $ticket->id), ['body' => 'Mais uma'])->assertSessionHas('error');
    }

    public function test_tickets_are_private_to_their_client_and_producer(): void
    {
        $ticket = $this->openTicket();
        $outro = Cliente::create(['tenant_id' => $this->tenant->id, 'name' => 'Outro', 'email' => 'outro@ficticio.test', 'password' => 'secret12345']);
        $outraProdutora = Tenant::create(['name' => 'Produtora B', 'slug' => 'produtora-b', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        $editorB = User::create(['tenant_id' => $outraProdutora->id, 'name' => 'Editor B', 'email' => 'b@ficticio.test', 'password' => 'secret12345', 'role' => 'editor']);

        $this->actingAs($outro, 'cliente')->get(route('portal.support.show', $ticket->id))->assertNotFound();
        $this->actingAs($outro, 'cliente')->post(route('portal.support.reply', $ticket->id), ['body' => 'x'])->assertNotFound();
        $this->actingAs($editorB)->get(route('support.show', $ticket))->assertNotFound();
    }

    public function test_chat_shortcut_opens_the_form_marked_as_chat(): void
    {
        $this->actingAs($this->cliente, 'cliente')->get(route('portal.support.create', ['origem' => 'chat', 'assunto' => 'Dúvida do chat']))
            ->assertOk()->assertSee('Dúvida do chat')->assertSee('name="from_chat"', false);

        $this->actingAs($this->cliente, 'cliente')->post(route('portal.support.store'), [
            'subject' => 'Do chat', 'category' => 'duvida', 'body' => 'Mensagem', 'from_chat' => '1',
        ]);
        $this->assertSame('chat', SupportTicket::forTenant($this->tenant->id)->sole()->source);
    }

    public function test_views_have_no_inline_css(): void
    {
        foreach (['support/index', 'support/show', 'portal/support/index', 'portal/support/create', 'portal/support/show'] as $view) {
            $this->assertStringNotContainsString('style="', file_get_contents(resource_path("views/{$view}.blade.php")), $view);
        }
    }
}
