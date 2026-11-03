<?php

namespace Tests\Feature;

use App\Ai\Agents\SofiWhatsAppAgent;
use App\Ai\Tools\EscalateToTeamTool;
use App\Mail\WhatsAppHandoffMail;
use App\Models\Lead;
use App\Models\SofiConversation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\WhatsApp\HandoffService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

/**
 * A Sofi chama a equipe: conversa marcada, CRM e e-mail aos administradores. Dados fictícios; e-mail e IA simulados.
 */
class WhatsAppHandoffTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private SofiConversation $conversation;

    private Lead $lead;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->tenant = Tenant::create(['name' => 'Produtora Alpha', 'slug' => 'produtora-alpha', 'status' => 'active', 'subscription_expires_at' => now()->addMonth()]);
        $this->admin = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Dona', 'email' => 'dona@ficticio.test', 'password' => 'secret12345', 'role' => 'admin']);
        User::create(['tenant_id' => $this->tenant->id, 'name' => 'Editor', 'email' => 'editor@ficticio.test', 'password' => 'secret12345', 'role' => 'editor']);

        $this->conversation = $this->conversation($this->tenant, '34600000001');
        $this->lead = Lead::create(['tenant_id' => $this->tenant->id, 'name' => 'Carlos', 'phone' => '34600000001', 'source' => 'whatsapp']);
    }

    private function conversation(Tenant $tenant, string $phone): SofiConversation
    {
        $conversation = new SofiConversation(['title' => 'Atendimento', 'channel' => 'whatsapp', 'contact_phone' => $phone, 'status' => 'active']);
        $conversation->forceFill(['tenant_id' => $tenant->id])->save();

        return $conversation;
    }

    private function escalate(array $arguments, ?int $conversationId = null, ?int $tenantId = null): string
    {
        $tool = new EscalateToTeamTool($tenantId ?? $this->tenant->id, $conversationId ?? $this->conversation->id, $this->lead->id);

        return (string) $tool->handle(new Request($arguments));
    }

    public function test_escalation_marks_the_conversation_logs_the_crm_and_emails_only_admins(): void
    {
        $result = $this->escalate(['reason' => 'discount', 'summary' => 'Quer 20% de desconto para 12 vídeos.']);

        $this->assertStringContainsString('Equipe avisada', $result);
        $this->assertSame('needs_human', $this->conversation->fresh()->status);
        $this->assertDatabaseHas('lead_activities', ['lead_id' => $this->lead->id, 'type' => 'whatsapp']);
        $this->assertStringContainsString('Desconto ou condição especial', $this->lead->activities()->latest('id')->value('body'));

        Mail::assertQueued(WhatsAppHandoffMail::class, 1);
        Mail::assertQueued(WhatsAppHandoffMail::class, fn (WhatsAppHandoffMail $mail) => $mail->hasTo('dona@ficticio.test')
            && ! $mail->hasTo('editor@ficticio.test')
            && $mail->contactPhone === '34600000001'
            && $mail->panelUrl === route('inbox.index', ['conversation_id' => $this->conversation->id]));
    }

    public function test_the_team_is_warned_once_per_episode(): void
    {
        $this->escalate(['reason' => 'complaint', 'summary' => 'Reclama do prazo.']);
        $second = $this->escalate(['reason' => 'complaint', 'summary' => 'Reclama do prazo de novo.']);

        $this->assertStringContainsString('já estava avisada', $second);
        Mail::assertQueued(WhatsAppHandoffMail::class, 1);
    }

    public function test_unknown_reason_becomes_other_and_the_summary_is_cleaned_and_limited(): void
    {
        $this->escalate(['reason' => 'qualquer-coisa', 'summary' => "<b>Texto</b>\n\n com   quebras ".str_repeat('x', 400)]);

        Mail::assertQueued(WhatsAppHandoffMail::class, function (WhatsAppHandoffMail $mail) {
            return $mail->reasonLabel === HandoffService::REASONS['other']
                && ! str_contains($mail->summary, '<b>')
                && ! str_contains($mail->summary, "\n")
                && mb_strlen($mail->summary) <= 303;
        });
    }

    public function test_conversation_of_another_tenant_or_missing_is_never_touched(): void
    {
        $other = Tenant::create(['name' => 'Produtora Beta', 'slug' => 'produtora-beta']);
        $foreign = $this->conversation($other, '34600000009');

        $this->assertStringContainsString('Não consegui acionar', $this->escalate(['reason' => 'other', 'summary' => 'x'], $foreign->id));
        $this->assertStringContainsString('Não consegui acionar', (string) (new EscalateToTeamTool($this->tenant->id, null, null))->handle(new Request(['reason' => 'other'])));

        $this->assertSame('active', $foreign->fresh()->status);
        Mail::assertNothingQueued();
    }

    public function test_a_mail_failure_never_breaks_the_service_and_never_leaks_into_the_log(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp secreto'));

        $notified = app(HandoffService::class)->request($this->conversation, $this->lead, 'discount', 'Resumo');

        $this->assertTrue($notified);
        $this->assertSame('needs_human', $this->conversation->fresh()->status);
    }

    public function test_the_email_shows_contact_reason_summary_and_link_in_plain_text(): void
    {
        $mail = new WhatsAppHandoffMail('Produtora Alpha', 'Carlos', '34600000001', 'Desconto ou condição especial', '<script>alert(1)</script> Quer desconto', 'https://app.ficticio.test/inbox?conversation_id=7');

        $html = $mail->render();

        $this->assertStringContainsString('+34600000001', $html);
        $this->assertStringContainsString('Desconto ou condição especial', $html);
        $this->assertStringContainsString('https://app.ficticio.test/inbox?conversation_id=7', $html);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    }

    public function test_the_inbox_lists_conversations_that_need_a_person_first_with_a_badge(): void
    {
        $waiting = $this->conversation($this->tenant, '34600000002');
        $waiting->forceFill(['status' => 'needs_human'])->save();
        $this->conversation->touch();

        $this->actingAs($this->admin)->get(route('inbox.index'))
            ->assertOk()
            ->assertSee('Precisa de você')
            ->assertSeeInOrder(['+34600000002', '+34600000001']);
    }

    public function test_the_agent_knows_the_team_was_already_called(): void
    {
        config([
            'sofi.provider' => 'gemini', 'sofi.model' => null, 'sofi.keys.gemini' => 'chave-de-teste',
            'services.whatsapp.verify_token' => 'token', 'services.whatsapp.app_secret' => 'segredo-ficticio-do-app',
            'services.whatsapp.phone_number_id' => '1234567890', 'services.whatsapp.tenant_slug' => 'produtora-alpha',
            'services.whatsapp.access_token' => null,
        ]);
        $this->conversation->forceFill(['status' => 'needs_human'])->save();
        SofiWhatsAppAgent::fake(['Resposta de teste.'])->preventStrayPrompts();

        $body = json_encode(['entry' => [['changes' => [['value' => [
            'metadata' => ['phone_number_id' => '1234567890'],
            'messages' => [['from' => '34600000001', 'id' => 'wamid.teste', 'type' => 'text', 'text' => ['body' => 'E então?']]],
        ]]]]]]);
        $this->call('POST', '/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'segredo-ficticio-do-app'),
        ], $body)->assertOk();

        SofiWhatsAppAgent::assertPrompted(fn ($prompt) => str_contains($prompt->agent->context, 'A EQUIPE JÁ FOI AVISADA'));
    }

    public function test_the_whatsapp_agent_has_the_escalation_tool_registered(): void
    {
        config(['sofi.provider' => 'gemini', 'sofi.model' => null, 'sofi.keys.gemini' => 'chave-de-teste']);
        SofiWhatsAppAgent::fake([
            new ToolCall('call_1', 'EscalateToTeamTool', ['reason' => 'human_requested', 'summary' => 'Pediu para falar com uma pessoa.']),
            'A equipe foi avisada.',
        ])->preventStrayPrompts();

        $agent = new SofiWhatsAppAgent($this->tenant->id, null, '', false, [], $this->lead->id, '34600000001', '', $this->conversation->id);

        $this->assertSame('A equipe foi avisada.', (string) $agent->prompt('Quero falar com uma pessoa'));
        $this->assertSame('needs_human', $this->conversation->fresh()->status);
        Mail::assertQueued(WhatsAppHandoffMail::class, 1);
    }
}
