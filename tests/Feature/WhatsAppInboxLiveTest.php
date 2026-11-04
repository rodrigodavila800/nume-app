<?php

namespace Tests\Feature;

use App\Models\SofiConversation;
use App\Models\SofiMessage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Atualização automática do painel de conversas (consulta periódica). Dados fictícios.
 */
class WhatsAppInboxLiveTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $staff;

    private SofiConversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Produtora Alpha', 'slug' => 'produtora-alpha', 'status' => 'active', 'subscription_expires_at' => now()->addMonth()]);
        $this->staff = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'admin']);
        $this->conversation = $this->conversationFor($this->tenant, '34600000001');
    }

    private function conversationFor(Tenant $tenant, string $phone): SofiConversation
    {
        $conversation = new SofiConversation(['title' => 'Atendimento', 'channel' => 'whatsapp', 'contact_phone' => $phone, 'status' => 'active']);
        $conversation->forceFill(['tenant_id' => $tenant->id])->save();

        return $conversation;
    }

    private function message(SofiConversation $conversation, string $content, string $sender = 'user'): SofiMessage
    {
        return SofiMessage::create(['sofi_conversation_id' => $conversation->id, 'sender' => $sender, 'content' => $content]);
    }

    private function poll(array $query)
    {
        return $this->actingAs($this->staff)->getJson(route('inbox.poll', $query))->assertOk();
    }

    public function test_only_messages_after_the_last_id_come_back(): void
    {
        $old = $this->message($this->conversation, 'Mensagem antiga');
        $this->message($this->conversation, 'Quanto custa?');
        $reply = $this->message($this->conversation, 'Os pacotes começam em 490 €.', 'sofi');

        $data = $this->poll(['conversation_id' => $this->conversation->id, 'after' => $old->id])->json();

        $this->assertStringContainsString('Quanto custa?', $data['messages_html']);
        $this->assertStringContainsString('Os pacotes começam em 490 €.', $data['messages_html']);
        $this->assertStringNotContainsString('Mensagem antiga', $data['messages_html']);
        $this->assertSame($reply->id, $data['last_id']);
        $this->assertSame('active', $data['status']);
    }

    public function test_the_list_is_sent_only_when_something_changed(): void
    {
        $this->message($this->conversation, 'Oi');
        $first = $this->poll(['conversation_id' => $this->conversation->id])->json();
        $this->assertNotNull($first['list_html']);

        $same = $this->poll(['conversation_id' => $this->conversation->id, 'after' => $first['last_id'], 'signature' => $first['signature']])->json();
        $this->assertNull($same['list_html']);
        $this->assertSame('', $same['messages_html']);

        $other = $this->conversationFor($this->tenant, '34600000002');
        $this->message($other, 'Mensagem em outra conversa');
        $changed = $this->poll(['conversation_id' => $this->conversation->id, 'after' => $first['last_id'], 'signature' => $first['signature']])->json();
        $this->assertNotNull($changed['list_html']);
        $this->assertStringContainsString('+34600000002', $changed['list_html']);
    }

    public function test_a_conversation_of_another_tenant_never_leaks(): void
    {
        $other = Tenant::create(['name' => 'Produtora Beta', 'slug' => 'produtora-beta']);
        $foreign = $this->conversationFor($other, '34600000009');
        $this->message($foreign, 'Segredo da outra produtora');

        $data = $this->poll(['conversation_id' => $foreign->id])->json();

        $this->assertSame('', $data['messages_html']);
        $this->assertNull($data['status']);
        $this->assertStringNotContainsString('34600000009', (string) $data['list_html']);
    }

    public function test_guests_cannot_poll_and_the_page_starts_the_live_refresh(): void
    {
        $this->getJson(route('inbox.poll'))->assertUnauthorized();

        $this->actingAs($this->staff)->get(route('inbox.index', ['conversation_id' => $this->conversation->id]))
            ->assertOk()
            ->assertSee(json_encode(route('inbox.poll')), false)
            ->assertSee('setInterval(refreshInbox, 4000)', false);
    }
}
