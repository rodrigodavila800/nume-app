<?php

namespace Tests\Feature;

use App\Ai\Agents\SofiWhatsAppAgent;
use App\Ai\Tools\EscalateToTeamTool;
use App\Mail\WhatsAppHandoffMail;
use App\Models\Lead;
use App\Models\Pack;
use App\Models\SofiConversation;
use App\Models\SofiMessage;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Crm\LeadService;
use App\Services\WhatsApp\HandoffService;
use App\Services\WhatsApp\MetaWhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
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
        Http::preventStrayRequests();
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
            'messages' => [['from' => '34600000001', 'id' => 'wamid.'.uniqid('', true), 'type' => 'text', 'text' => ['body' => 'E então?']]],
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

    private function incoming(string $text, bool $withAiKey = true, ?string $messageId = null): void
    {
        config([
            'sofi.provider' => 'gemini', 'sofi.model' => null, 'sofi.keys.gemini' => $withAiKey ? 'chave-de-teste' : null,
            'services.whatsapp.app_secret' => 'segredo-ficticio-do-app', 'services.whatsapp.phone_number_id' => '1234567890',
            'services.whatsapp.tenant_slug' => 'produtora-alpha', 'services.whatsapp.access_token' => null,
        ]);

        $body = json_encode(['entry' => [['changes' => [['value' => [
            'metadata' => ['phone_number_id' => '1234567890'],
            'messages' => [['from' => '34600000001', 'id' => $messageId ?? 'wamid.'.uniqid('', true), 'type' => 'text', 'text' => ['body' => $text]]],
        ]]]]]]);

        $this->call('POST', '/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'segredo-ficticio-do-app'),
        ], $body)->assertOk();
    }

    private function humanReply(string $text, \DateTimeInterface $at): void
    {
        $this->conversation->forceFill(['status' => 'human'])->save();
        SofiMessage::create(['sofi_conversation_id' => $this->conversation->id, 'sender' => 'sofi', 'content' => $text, 'metadata' => ['is_human' => true]])
            ->forceFill(['created_at' => $at])->save();
    }

    public function test_replying_from_the_panel_pauses_the_sofi(): void
    {
        config(['services.whatsapp.access_token' => null, 'services.whatsapp.phone_number_id' => null]);

        $this->actingAs($this->admin)->post(route('inbox.reply', $this->conversation), ['message' => 'Oi, aqui é a equipe.'])->assertRedirect();

        $this->assertSame('human', $this->conversation->fresh()->status);
    }

    public function test_the_sofi_stays_quiet_while_the_team_is_handling_the_conversation(): void
    {
        SofiWhatsAppAgent::fake([])->preventStrayPrompts();
        $this->humanReply('Já te mando o orçamento.', now()->subHour());

        $this->incoming('Pode ser amanhã?');

        SofiWhatsAppAgent::assertNeverPrompted();
        $this->assertSame('human', $this->conversation->fresh()->status);
        $this->assertSame(1, $this->conversation->messages()->where('sender', 'user')->count(), 'a mensagem do cliente fica salva');
        $this->assertSame(1, $this->conversation->messages()->where('sender', 'sofi')->count(), 'só a resposta da equipe, nenhuma da Sofi');
        $this->assertStringContainsString('durante o atendimento da equipe', $this->lead->activities()->latest('id')->value('body'));
    }

    public function test_the_sofi_resumes_after_the_pause_ends(): void
    {
        SofiWhatsAppAgent::fake(['Voltei por aqui! Posso ajudar?'])->preventStrayPrompts();
        $this->humanReply('Já te mando o orçamento.', now()->subHours(5));

        $this->incoming('Alguma novidade?');

        SofiWhatsAppAgent::assertPrompted('Alguma novidade?');
        $this->assertSame('active', $this->conversation->fresh()->status);
        $this->assertSame('Voltei por aqui! Posso ajudar?', $this->conversation->messages()->where('sender', 'sofi')->reorder('id', 'desc')->value('content'));
    }

    public function test_the_inbox_flags_a_customer_waiting_after_the_team_replied(): void
    {
        $this->humanReply('Já te mando o orçamento.', now()->subHour());
        SofiMessage::create(['sofi_conversation_id' => $this->conversation->id, 'sender' => 'user', 'content' => 'Pode ser amanhã?']);

        $this->actingAs($this->admin)->get(route('inbox.index'))->assertOk()->assertSee('Cliente respondeu');
    }

    public function test_when_the_ai_fails_the_customer_gets_the_real_packs_and_the_team_is_called(): void
    {
        Pack::create(['tenant_id' => $this->tenant->id, 'name' => 'Reels Starter', 'slug' => 'reels-starter', 'price' => 490, 'video_quantity' => 4, 'status' => 'active']);
        SofiWhatsAppAgent::fake([fn () => throw new \RuntimeException('503 modelo sobrecarregado')])->preventStrayPrompts();

        $this->incoming('Oi gostaria de orçamento');

        $reply = $this->conversation->messages()->where('sender', 'sofi')->reorder('id', 'desc')->value('content');
        $this->assertStringContainsString('*Reels Starter*: 4 vídeos por 490,00 € + IVA', $reply);
        $this->assertStringContainsString('já avisei nossa equipe', $reply);
        $this->assertSame('needs_human', $this->conversation->fresh()->status);
        Mail::assertQueued(WhatsAppHandoffMail::class, fn (WhatsAppHandoffMail $mail) => $mail->reasonLabel === HandoffService::REASONS['ai_unavailable'] && $mail->hasTo('dona@ficticio.test'));
    }

    public function test_without_an_ai_key_the_same_fallback_applies_and_the_team_is_warned_only_once(): void
    {
        Pack::create(['tenant_id' => $this->tenant->id, 'name' => 'Reels Starter', 'slug' => 'reels-starter', 'price' => 490, 'video_quantity' => 4, 'status' => 'active']);

        $this->incoming('Oi gostaria de orçamento', withAiKey: false);
        $this->incoming('Alguém aí?', withAiKey: false);

        $this->assertSame(2, $this->conversation->messages()->where('sender', 'sofi')->count());
        Mail::assertQueued(WhatsAppHandoffMail::class, 1);
    }

    public function test_the_fallback_still_replies_when_the_tenant_has_no_packs(): void
    {
        SofiWhatsAppAgent::fake([fn () => throw new \RuntimeException('503')])->preventStrayPrompts();

        $this->incoming('Oi');

        $reply = $this->conversation->messages()->where('sender', 'sofi')->reorder('id', 'desc')->value('content');
        $this->assertStringContainsString('Produtora Alpha', $reply);
        $this->assertStringNotContainsString('pacotes:', $reply);
    }

    public function test_meta_resending_the_same_message_is_answered_only_once(): void
    {
        SofiWhatsAppAgent::fake(['Resposta única.'])->preventStrayPrompts();

        $this->incoming('Quero um orçamento', messageId: 'wamid.REENVIO');
        $this->incoming('Quero um orçamento', messageId: 'wamid.REENVIO');

        $this->assertSame(1, $this->conversation->messages()->where('sender', 'user')->count());
        $this->assertSame(1, $this->conversation->messages()->where('sender', 'sofi')->count());
        $this->assertSame(1, $this->lead->activities()->where('type', 'whatsapp')->count());
    }

    public function test_a_failed_processing_releases_the_lock_so_the_meta_retry_still_works(): void
    {
        config([
            'services.whatsapp.phone_number_id' => '1234567890', 'services.whatsapp.tenant_slug' => 'produtora-alpha',
            'sofi.provider' => 'gemini', 'sofi.keys.gemini' => 'chave-de-teste', 'services.whatsapp.access_token' => null,
        ]);
        SofiWhatsAppAgent::fake(['Resposta depois do reenvio.'])->preventStrayPrompts();
        $payload = ['entry' => [['changes' => [['value' => [
            'metadata' => ['phone_number_id' => '1234567890'],
            'messages' => [['from' => '34600000001', 'id' => 'wamid.FALHA', 'type' => 'text', 'text' => ['body' => 'Oi']]],
        ]]]]]];
        $lockKey = 'whatsapp:wamid:'.hash('sha256', 'wamid.FALHA');

        $this->app->bind(LeadService::class, fn () => throw new \RuntimeException('banco indisponível'));
        try {
            app(MetaWhatsAppService::class)->processWebhookPayload($payload);
            $this->fail('A falha deveria subir para a Meta reenviar.');
        } catch (\RuntimeException) {
            $this->assertFalse(Cache::has($lockKey), 'a trava foi solta');
        }

        $this->app->offsetUnset(LeadService::class);
        $this->app->forgetInstance(MetaWhatsAppService::class);
        $result = app(MetaWhatsAppService::class)->processWebhookPayload($payload);

        $this->assertSame('success', $result['status']);
        $this->assertTrue(Cache::has($lockKey));
    }

    public function test_status_updates_without_a_message_id_are_not_locked(): void
    {
        $service = app(MetaWhatsAppService::class);
        $statusPayload = ['entry' => [['changes' => [['value' => ['statuses' => [['id' => 'wamid.X', 'status' => 'delivered']]]]]]]];

        $this->assertNull($service->processWebhookPayload($statusPayload));
        $this->assertNull($service->processWebhookPayload($statusPayload));
    }

    public function test_an_ai_failure_logs_the_cause_chain_but_never_the_message_or_the_url(): void
    {
        $lines = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$lines) {
            $lines[] = $event->message;
        });
        $timeout = new ConnectionException('cURL error 28: Operation timed out for https://gemini.exemplo.test/v1?key=SEGREDO-DA-CHAVE');
        SofiWhatsAppAgent::fake([fn () => throw new \RuntimeException('falha do provedor com texto do cliente', 0, $timeout)])->preventStrayPrompts();

        $this->incoming('Pode fechar, meu e-mail é ana@ficticio.test');

        $line = collect($lines)->first(fn (string $l) => str_contains($l, 'Erro na execução do SofiWhatsAppAgent'));
        $this->assertNotNull($line);
        $this->assertStringContainsString('RuntimeException <- Illuminate\Http\Client\ConnectionException timeout', $line);
        $this->assertStringNotContainsString('SEGREDO', $line);
        $this->assertStringNotContainsString('gemini.exemplo', $line);
        $this->assertStringNotContainsString('texto do cliente', $line);
    }
}
