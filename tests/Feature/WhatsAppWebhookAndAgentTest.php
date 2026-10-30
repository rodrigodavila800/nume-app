<?php

namespace Tests\Feature;

use App\Ai\Agents\SofiWhatsAppAgent;
use App\Ai\Tools\CheckProductionStatusTool;
use App\Ai\Tools\QualifyLeadTool;
use App\Models\AiUsageLog;
use App\Models\Cliente;
use App\Models\Lead;
use App\Models\Production;
use App\Models\SofiConversation;
use App\Models\Tenant;
use App\Models\User;
use App\Services\WhatsApp\MetaWhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class WhatsAppWebhookAndAgentTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Produtora Alpha', 'slug' => 'produtora-alpha']);
        config([
            'services.whatsapp.verify_token' => 'teste_token_secreto_whatsapp',
            'services.whatsapp.app_secret' => 'segredo-ficticio-do-app',
            'services.whatsapp.phone_number_id' => '1234567890',
            'services.whatsapp.tenant_slug' => 'produtora-alpha',
            'services.whatsapp.access_token' => null,
        ]);
    }

    /**
     * POST assinado como a Meta assina (HMAC SHA-256 do corpo bruto com o App Secret).
     */
    private function postSigned(array $payload, ?string $secret = 'segredo-ficticio-do-app')
    {
        $body = json_encode($payload);
        $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        if ($secret !== null) {
            $headers['HTTP_X_HUB_SIGNATURE_256'] = 'sha256='.hash_hmac('sha256', $body, $secret);
        }

        return $this->call('POST', '/webhooks/whatsapp', [], [], [], $headers, $body);
    }

    private function messagePayload(string $from, string $text, string $phoneNumberId = '1234567890'): array
    {
        return ['entry' => [['changes' => [['value' => [
            'metadata' => ['phone_number_id' => $phoneNumberId],
            'messages' => [['from' => $from, 'id' => 'wamid.teste', 'timestamp' => '1730222000', 'type' => 'text', 'text' => ['body' => $text]]],
        ]]]]]];
    }

    public function test_webhook_verification_returns_challenge_on_valid_token(): void
    {
        $response = $this->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=teste_token_secreto_whatsapp&hub_challenge=11559955');

        $response->assertStatus(200);
        $this->assertSame('11559955', $response->getContent());
    }

    public function test_webhook_verification_fails_on_invalid_token(): void
    {
        $response = $this->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=token_errado&hub_challenge=11559955');

        $response->assertStatus(403);
    }

    public function test_incoming_whatsapp_message_creates_lead_and_conversation_and_replies(): void
    {
        $cliente = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'João Silva',
            'phone' => '5511999998888',
            'email' => 'joao@silva.com',
            'password' => bcrypt('secret123'),
        ]);

        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'metadata' => [
                                    'phone_number_id' => '1234567890',
                                ],
                                'messages' => [
                                    [
                                        'from' => '5511999998888',
                                        'id' => 'wamid.HBgL...',
                                        'timestamp' => '1730222000',
                                        'type' => 'text',
                                        'text' => [
                                            'body' => 'Olá! Gostaria de saber como está o andamento do meu vídeo.',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postSigned($payload);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'EVENT_RECEIVED']);

        // Verifica se a conversa no canal whatsapp foi criada
        $conversation = SofiConversation::where('channel', 'whatsapp')
            ->where('contact_phone', '5511999998888')
            ->first();

        $this->assertNotNull($conversation);
        $this->assertSame($this->tenant->id, $conversation->tenant_id);
        $this->assertSame($cliente->id, $conversation->cliente_id);

        // Verifica se as mensagens (usuário e sofi) foram salvas
        $messages = $conversation->messages()->get();
        $this->assertCount(2, $messages);
        $this->assertSame('user', $messages[0]->sender);
        $this->assertSame('sofi', $messages[1]->sender);

        // Verifica se o lead existe no CRM com a atividade registrada
        $lead = Lead::where('phone', '5511999998888')->first();
        $this->assertNotNull($lead);
        $this->assertSame('whatsapp', $lead->source);
        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'type' => 'whatsapp',
        ]);
    }

    public function test_incoming_whatsapp_message_reuses_existing_open_lead_without_creating_duplicate(): void
    {
        // Cria um lead já existente em 'qualificado' com telefone local formatado
        $lead = new Lead([
            'tenant_id' => $this->tenant->id,
            'name' => 'Rodrigo Dávila',
            'company' => 'Desenvolvimento de Sistemas',
            'phone' => '(91) 8258-6886',
            'source' => 'quote_request',
        ]);
        $lead->forceFill(['stage' => 'qualificado'])->save();

        $payload = [
            'entry' => [
                [
                    'changes' => [
                        [
                            'value' => [
                                'metadata' => ['phone_number_id' => '1234567890'],
                                'contacts' => [
                                    ['profile' => ['name' => 'Rodrigo D’ávila'], 'wa_id' => '559182586886'],
                                ],
                                'messages' => [
                                    [
                                        'from' => '559182586886',
                                        'id' => 'wamid.HBgL123...',
                                        'timestamp' => '1730222010',
                                        'type' => 'text',
                                        'text' => ['body' => 'Olá Sofi, como está meu projeto?'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->postSigned($payload);
        $response->assertStatus(200);

        // Deve manter o mesmo lead em 'qualificado' e NÃO criar um novo em 'novo'
        $this->assertSame(1, Lead::where('tenant_id', $this->tenant->id)->count());
        $lead->refresh();
        $this->assertSame('qualificado', $lead->stage);

        // Atividade registrada no mesmo lead
        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'type' => 'whatsapp',
        ]);
    }

    public function test_post_without_or_with_wrong_signature_is_refused_and_nothing_is_saved(): void
    {
        $payload = $this->messagePayload('5511999998888', 'Como está meu vídeo?');

        $this->postSigned($payload, secret: null)->assertStatus(403);
        $this->postSigned($payload, secret: 'segredo-errado')->assertStatus(403);

        config(['services.whatsapp.app_secret' => null]);
        $this->postSigned($payload)->assertStatus(403);

        $this->assertSame(0, SofiConversation::withoutGlobalScopes()->count());
        $this->assertSame(0, Lead::withoutGlobalScopes()->count());
    }

    public function test_reply_comes_from_the_ai_agent_and_usage_is_recorded(): void
    {
        config(['sofi.provider' => 'gemini', 'sofi.model' => null, 'sofi.keys.gemini' => 'chave-de-teste']);
        SofiWhatsAppAgent::fake(['Olá! Aqui é a Sofi, respondendo pela IA.'])->preventStrayPrompts();

        $this->postSigned($this->messagePayload('5511999998888', 'Quais pacotes vocês têm?'))->assertStatus(200);

        $conversation = SofiConversation::withoutGlobalScopes()->where('contact_phone', '5511999998888')->sole();
        $this->assertSame('Olá! Aqui é a Sofi, respondendo pela IA.', $conversation->messages()->where('sender', 'sofi')->value('content'));
        SofiWhatsAppAgent::assertPrompted('Quais pacotes vocês têm?');
        $this->assertSame(1, AiUsageLog::forTenant($this->tenant->id)->where('agent', SofiWhatsAppAgent::class)->count());
    }

    public function test_message_to_an_unknown_phone_number_id_is_ignored(): void
    {
        $this->postSigned($this->messagePayload('5511999998888', 'Olá', phoneNumberId: '999'))->assertStatus(200);

        $this->assertSame(0, SofiConversation::withoutGlobalScopes()->count());
    }

    public function test_verification_fails_when_no_token_is_configured(): void
    {
        config(['services.whatsapp.verify_token' => null]);

        $this->get('/webhooks/whatsapp?hub_mode=subscribe&hub_verify_token=&hub_challenge=1')->assertStatus(403);
    }

    public function test_client_is_identified_only_by_an_unambiguous_full_number(): void
    {
        $service = app(MetaWhatsAppService::class);
        $ana = Cliente::create(['tenant_id' => $this->tenant->id, 'name' => 'Ana', 'phone' => '+34 664 274 151', 'email' => 'ana@ficticio.test', 'password' => 'secret12345']);

        $carla = Cliente::create(['tenant_id' => $this->tenant->id, 'name' => 'Carla', 'phone' => '(91) 98258-6886', 'email' => 'carla@ficticio.test', 'password' => 'secret12345']);

        // A Meta manda o número completo, com código do país.
        $this->assertSame($ana->id, $service->findCliente($this->tenant->id, '34664274151')?->id, 'mesmo número sem formatação');
        $this->assertSame($carla->id, $service->findCliente($this->tenant->id, '5591982586886')?->id, 'gravado sem código do país');
        $this->assertNull($service->findCliente($this->tenant->id, '5511664274151'), 'mesmo final, outro número');
        $this->assertNull($service->findCliente($this->tenant->id, '74151'), 'número curto demais');

        // Dois clientes com o mesmo número nacional em países diferentes: na dúvida, ninguém.
        Cliente::create(['tenant_id' => $this->tenant->id, 'name' => 'Beto', 'phone' => '664274151', 'email' => 'beto@ficticio.test', 'password' => 'secret12345']);
        $this->assertNull($service->findCliente($this->tenant->id, '34664274151'));
    }

    public function test_status_tool_never_lists_productions_for_an_unidentified_contact(): void
    {
        $staff = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Staff', 'email' => 'staff@ficticio.test', 'password' => 'secret12345', 'role' => 'admin']);
        $ana = Cliente::create(['tenant_id' => $this->tenant->id, 'name' => 'Ana', 'email' => 'ana@ficticio.test', 'password' => 'secret12345']);
        $production = Production::create(['tenant_id' => $this->tenant->id, 'cliente_id' => $ana->id, 'created_by' => $staff->id, 'title' => 'Campanha secreta da Ana', 'status' => 'draft']);

        $anonymous = (string) (new CheckProductionStatusTool($this->tenant->id, null))->handle(new Request([]));
        $this->assertStringNotContainsString('Campanha secreta', $anonymous);

        $own = (string) (new CheckProductionStatusTool($this->tenant->id, $ana->id))->handle(new Request([]));
        $this->assertStringContainsString('Campanha secreta da Ana', $own);
        $this->assertStringContainsString($production->uuid, $own, 'link pelo uuid');
        $this->assertStringNotContainsString('"id"', $own);
    }

    public function test_qualify_lead_tool_moves_lead_to_qualificado_and_updates_data(): void
    {
        $lead = Lead::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Carlos Silva',
            'phone' => '34664274151',
            'source' => 'whatsapp',
        ]);

        $tool = new QualifyLeadTool($this->tenant->id, $lead->id, '34664274151');
        $request = new Request([
            'company' => 'Clínica Dental Zaragoza',
            'niche' => 'Saúde & Odontologia',
            'video_goal' => 'Reels semanais para captação de pacientes',
            'pack_name' => 'Pack Impulso Local',
            'estimated_budget' => 490.00,
            'notes' => 'Quer iniciar gravações já na próxima quinzena.',
        ]);

        $result = $tool->handle($request);

        $this->assertStringContainsString('qualificado com sucesso', (string) $result);

        $lead->refresh();
        $this->assertSame('qualificado', $lead->stage);
        $this->assertSame('Clínica Dental Zaragoza', $lead->company);
        $this->assertEquals(490.00, $lead->estimated_value);
        $this->assertStringContainsString('próxima quinzena', $lead->notes);

        $this->assertDatabaseHas('lead_activities', [
            'lead_id' => $lead->id,
            'type' => 'whatsapp',
        ]);
    }
}
