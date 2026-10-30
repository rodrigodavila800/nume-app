<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\SofiConversation;
use App\Models\SofiMessage;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppInboxTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;

    protected Tenant $tenantB;

    protected User $staffA;

    protected User $staffB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name' => 'Produtora Alpha',
            'slug' => 'produtora-alpha',
            'status' => 'active',
            'subscription_expires_at' => now()->addMonth(),
        ]);
        $this->tenantB = Tenant::create([
            'name' => 'Produtora Beta',
            'slug' => 'produtora-beta',
            'status' => 'active',
            'subscription_expires_at' => now()->addMonth(),
        ]);

        $this->staffA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'role' => 'admin',
        ]);

        $this->staffB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'role' => 'admin',
        ]);
    }

    public function test_staff_can_view_inbox_and_see_conversations(): void
    {
        $conv = SofiConversation::create([
            'tenant_id' => $this->tenantA->id,
            'channel' => 'whatsapp',
            'contact_phone' => '559182586886',
            'title' => 'Atendimento Rodrigo',
        ]);

        SofiMessage::create([
            'sofi_conversation_id' => $conv->id,
            'sender' => 'user',
            'content' => 'Olá Sofi, quais os pacotes?',
        ]);

        SofiMessage::create([
            'sofi_conversation_id' => $conv->id,
            'sender' => 'sofi',
            'content' => 'Temos o pacote de Reels por 490€!',
        ]);

        $response = $this->actingAs($this->staffA)->get(route('inbox.index'));

        $response->assertOk();
        $response->assertSee('Conversas & WhatsApp');
        $response->assertSee('559182586886');
        $response->assertSee('Olá Sofi, quais os pacotes?');
        $response->assertSee('Temos o pacote de Reels por 490€!');
    }

    public function test_staff_can_send_manual_reply_from_panel(): void
    {
        $conv = SofiConversation::create([
            'tenant_id' => $this->tenantA->id,
            'channel' => 'whatsapp',
            'contact_phone' => '559182586886',
            'title' => 'Atendimento Rodrigo',
        ]);

        $lead = Lead::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Rodrigo Dávila',
            'phone' => '559182586886',
            'source' => 'whatsapp',
        ]);

        $response = $this->actingAs($this->staffA)->post(route('inbox.reply', $conv), [
            'message' => 'Olá Rodrigo, vi que a Sofi te apresentou os pacotes. Podemos agendar uma reunião amanhã às 15h?',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('sofi_messages', [
            'sofi_conversation_id' => $conv->id,
            'sender' => 'sofi',
            'content' => 'Olá Rodrigo, vi que a Sofi te apresentou os pacotes. Podemos agendar uma reunião amanhã às 15h?',
        ]);

        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'type' => 'whatsapp',
        ]);
    }

    public function test_tenant_isolation_prevents_staff_from_replying_to_other_tenant_conversation(): void
    {
        $convB = SofiConversation::create([
            'tenant_id' => $this->tenantB->id,
            'channel' => 'whatsapp',
            'contact_phone' => '34600112233',
            'title' => 'Cliente Beta',
        ]);

        $response = $this->actingAs($this->staffA)->post(route('inbox.reply', $convB), [
            'message' => 'Tentativa de invadir conversa de outro tenant',
        ]);

        $response->assertNotFound();
    }
}
