<?php

namespace Tests\Feature;

use App\Ai\Agents\SofiWhatsAppAgent;
use App\Models\SofiConversation;
use App\Models\Tenant;
use App\Services\WhatsApp\MetaWhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Boas-vindas do WhatsApp: aviso de privacidade (RGPD), 3 botões e resposta ao clique. Dados fictícios;
 * Graph API e IA simulados.
 */
class WhatsAppWelcomeTest extends TestCase
{
    use RefreshDatabase;

    private const MESSAGES = 'https://graph.facebook.com/v21.0/1234567890/messages';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Http::fake([self::MESSAGES => Http::response(['messages' => [['id' => 'wamid.saida']]])]);
        Tenant::create(['name' => 'Produtora Alpha', 'slug' => 'produtora-alpha']);
        config([
            'services.whatsapp.app_secret' => 'segredo-ficticio-do-app',
            'services.whatsapp.phone_number_id' => '1234567890',
            'services.whatsapp.tenant_slug' => 'produtora-alpha',
            'services.whatsapp.access_token' => 'token-whatsapp-ficticio',
            'services.whatsapp.api_version' => 'v21.0',
            'sofi.provider' => 'gemini', 'sofi.model' => null, 'sofi.keys.gemini' => 'chave-de-teste',
        ]);
    }

    private function incoming(array $message): void
    {
        app(MetaWhatsAppService::class)->processWebhookPayload(['entry' => [['changes' => [['value' => [
            'metadata' => ['phone_number_id' => '1234567890'],
            'messages' => [['from' => '34600000001', 'id' => 'wamid.'.uniqid('', true)] + $message],
        ]]]]]]);
    }

    private function text(string $body): void
    {
        $this->incoming(['type' => 'text', 'text' => ['body' => $body]]);
    }

    private function conversation(): SofiConversation
    {
        return SofiConversation::withoutGlobalScopes()->sole();
    }

    public function test_a_greeting_on_first_contact_gets_the_welcome_with_privacy_and_three_buttons_only(): void
    {
        SofiWhatsAppAgent::fake([])->preventStrayPrompts();

        $this->text('Oi, bom dia!');

        SofiWhatsAppAgent::assertNeverPrompted();
        Http::assertSent(function (HttpRequest $request) {
            $buttons = $request['interactive']['action']['buttons'] ?? [];

            return $request->url() === self::MESSAGES
                && $request['type'] === 'interactive'
                && str_contains($request['interactive']['body']['text'], route('legal.privacy'))
                && array_column(array_column($buttons, 'reply'), 'id') === ['sofi_buy', 'sofi_client', 'sofi_team']
                && collect($buttons)->every(fn (array $b) => mb_strlen($b['reply']['title']) <= 20);
        });
        $this->assertTrue($this->conversation()->messages()->where('sender', 'sofi')->sole()->metadata['welcome']);
    }

    public function test_a_real_question_on_first_contact_gets_the_welcome_and_then_the_sofi_answer(): void
    {
        SofiWhatsAppAgent::fake(['Nossos pacotes começam em 490 €.'])->preventStrayPrompts();

        $this->text('Oi, quanto custa um vídeo?');

        SofiWhatsAppAgent::assertPrompted('Oi, quanto custa um vídeo?');
        $replies = $this->conversation()->messages()->where('sender', 'sofi')->pluck('content')->all();
        $this->assertCount(2, $replies);
        $this->assertStringContainsString(route('legal.privacy'), $replies[0]);
        $this->assertSame('Nossos pacotes começam em 490 €.', $replies[1]);
    }

    public function test_the_welcome_goes_only_once_per_conversation(): void
    {
        SofiWhatsAppAgent::fake(['Resposta.', 'Resposta.'])->preventStrayPrompts();

        $this->text('Oi');
        $this->text('Quero um orçamento');
        $this->text('Oi de novo');

        $this->assertSame(1, $this->conversation()->messages()->where('sender', 'sofi')->get()->filter(fn ($m) => ! empty($m->metadata['welcome']))->count());
        SofiWhatsAppAgent::assertPrompted('Oi de novo');
    }

    public function test_clicking_a_button_reaches_the_sofi_as_the_button_title(): void
    {
        SofiWhatsAppAgent::fake(['Ótimo! Qual é o seu negócio?', 'Resposta.'])->preventStrayPrompts();
        $this->text('Oi');

        $this->incoming(['type' => 'interactive', 'interactive' => ['type' => 'button_reply', 'button_reply' => ['id' => 'sofi_buy', 'title' => 'Quero contratar']]]);

        SofiWhatsAppAgent::assertPrompted('Quero contratar');
        $this->assertSame('Quero contratar', $this->conversation()->messages()->where('sender', 'user')->reorder('id', 'desc')->value('content'));
    }

    public function test_other_message_types_are_still_ignored(): void
    {
        SofiWhatsAppAgent::fake([])->preventStrayPrompts();

        $this->incoming(['type' => 'image', 'image' => ['id' => 'midia-1']]);
        $this->incoming(['type' => 'interactive', 'interactive' => ['type' => 'list_reply', 'list_reply' => ['id' => 'x', 'title' => 'Y']]]);

        $this->assertSame(0, SofiConversation::withoutGlobalScopes()->count());
        Http::assertNothingSent();
    }
}
