<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Lead;
use App\Models\SofiConversation;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppWebhookAndAgentTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Produtora Alpha', 'slug' => 'produtora-alpha']);
        config(['services.whatsapp.verify_token' => 'teste_token_secreto_whatsapp']);
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

        $response = $this->postJson('/webhooks/whatsapp', $payload);

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

        $response = $this->postJson('/webhooks/whatsapp', $payload);
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
}
