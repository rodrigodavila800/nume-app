<?php

namespace Tests\Feature;

use App\Ai\Agents\SofiWhatsAppAgent;
use App\Jobs\ProcessWhatsAppMessage;
use App\Models\SofiConversation;
use App\Models\Tenant;
use App\Services\WhatsApp\MetaWhatsAppService;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Webhook do WhatsApp responde na hora e a Sofi trabalha na fila. Dados fictícios; IA simulada; sem rede.
 */
class WhatsAppQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Tenant::create(['name' => 'Produtora Alpha', 'slug' => 'produtora-alpha']);
        config([
            'services.whatsapp.app_secret' => 'segredo-ficticio-do-app',
            'services.whatsapp.phone_number_id' => '1234567890',
            'services.whatsapp.tenant_slug' => 'produtora-alpha',
            'services.whatsapp.access_token' => null,
        ]);
    }

    private function payload(string $text = 'Quero um orçamento'): array
    {
        return ['entry' => [['changes' => [['value' => [
            'metadata' => ['phone_number_id' => '1234567890'],
            'messages' => [['from' => '34600000001', 'id' => 'wamid.'.uniqid('', true), 'type' => 'text', 'text' => ['body' => $text]]],
        ]]]]]];
    }

    private function postWebhook(array $payload, string $secret = 'segredo-ficticio-do-app')
    {
        $body = json_encode($payload);

        return $this->call('POST', '/webhooks/whatsapp', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, $secret),
        ], $body);
    }

    public function test_the_webhook_answers_at_once_and_leaves_the_work_to_the_queue(): void
    {
        Queue::fake();
        $payload = $this->payload();

        $this->postWebhook($payload)->assertOk()->assertJson(['status' => 'EVENT_RECEIVED']);

        Queue::assertPushed(ProcessWhatsAppMessage::class, fn (ProcessWhatsAppMessage $job) => $job->payload === $payload);
        $this->assertSame(0, SofiConversation::withoutGlobalScopes()->count(), 'nada é processado antes da fila');
    }

    public function test_an_unsigned_post_never_reaches_the_queue(): void
    {
        Queue::fake();

        $this->postWebhook($this->payload(), 'segredo-errado')->assertStatus(403);

        Queue::assertNothingPushed();
    }

    public function test_the_job_is_encrypted_does_not_retry_and_fits_in_the_queue_retry_window(): void
    {
        $job = new ProcessWhatsAppMessage($this->payload());

        $this->assertInstanceOf(ShouldBeEncrypted::class, $job);
        $this->assertSame(1, $job->tries);
        $this->assertGreaterThan($job->timeout, (int) config('queue.connections.database.retry_after'));
        $this->assertGreaterThan(4 * (int) config('sofi.whatsapp_timeout'), $job->timeout, 'cabe 4 passos de IA');
    }

    public function test_the_job_runs_the_sofi_with_the_whatsapp_ai_timeout(): void
    {
        config(['sofi.provider' => 'gemini', 'sofi.model' => null, 'sofi.keys.gemini' => 'chave-de-teste', 'sofi.whatsapp_timeout' => 30]);
        SofiWhatsAppAgent::fake(['Resposta da fila.'])->preventStrayPrompts();

        (new ProcessWhatsAppMessage($this->payload('Quanto custa um vídeo?')))->handle(app(MetaWhatsAppService::class));

        SofiWhatsAppAgent::assertPrompted(fn ($prompt) => $prompt->prompt === 'Quanto custa um vídeo?' && $prompt->timeout === 30);
        $this->assertSame('Resposta da fila.', SofiConversation::withoutGlobalScopes()->sole()->messages()->where('sender', 'sofi')->reorder('id', 'desc')->value('content'));
    }

    public function test_a_failed_job_logs_only_the_exception_class(): void
    {
        $lines = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$lines) {
            $lines[] = $event->message;
        });

        (new ProcessWhatsAppMessage($this->payload()))->failed(new \RuntimeException('texto do cliente 34600000001'));

        $this->assertContains('[MetaWhatsApp] Processamento da mensagem falhou na fila: RuntimeException', $lines);
        $this->assertStringNotContainsString('34600000001', implode("\n", $lines));
    }
}
