<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Notification\SofiQuoteNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuoteNotificationAndCrmTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    protected User $staff;

    protected Cliente $cliente;

    protected Quote $quote;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Produtora Aurora',
            'slug' => 'produtora-aurora',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $this->staff = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Comercial Aurora',
            'email' => 'comercial@aurora.test',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $this->cliente = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Dra. Beatriz Santos',
            'email' => 'beatriz@clinica.test',
            'phone' => '+34 655 999 888',
            'password' => bcrypt('secret123'),
        ]);

        $this->quote = Quote::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'created_by' => $this->staff->id,
            'number' => 'ORC-2026-001',
            'status' => 'draft',
            'client_name' => 'Dra. Beatriz Santos',
            'client_email' => 'beatriz@clinica.test',
            'client_phone' => '+34 655 999 888',
            'project_title' => 'Campanha de Inverno 8 Vídeos',
            'total_amount' => 1200.00,
            'inputs' => ['videos' => 8],
            'calculation' => ['total' => 1200.00],
        ]);
    }

    public function test_service_generates_approval_token_and_whatsapp_message(): void
    {
        $service = app(SofiQuoteNotificationService::class);
        $this->assertNull($this->quote->approval_token);

        $message = $service->getWhatsAppMessage($this->quote);
        $this->quote->refresh();

        $this->assertNotNull($this->quote->approval_token);
        $this->assertStringContainsString('Campanha de Inverno 8 Vídeos', $message);
        $this->assertStringContainsString('1.200,00 €', $message);
        $this->assertStringContainsString('8 vídeos em formato de alta retenção', $message);
        $this->assertStringContainsString($this->quote->approval_token, $message);

        $shareUrl = $service->getWhatsAppShareUrl($this->quote);
        $this->assertStringContainsString('api.whatsapp.com/send', $shareUrl);
        $this->assertStringContainsString('34655999888', $shareUrl);
    }

    public function test_record_whatsapp_updates_status_and_advances_lead_in_crm(): void
    {
        $response = $this->actingAs($this->staff)->post(
            route('quotes.record-whatsapp', $this->quote),
            [],
            ['Accept' => 'application/json']
        );

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $this->quote->refresh();
        $this->assertEquals('sent', $this->quote->status);
        $this->assertNotNull($this->quote->sent_at);

        // Verifica que o Lead no CRM foi criado/atualizado para etapa 'proposta'
        $this->assertDatabaseHas('leads', [
            'tenant_id' => $this->tenant->id,
            'email' => 'beatriz@clinica.test',
            'stage' => 'proposta',
            'estimated_value' => 1200.00,
        ]);

        // Verifica que foi registrada a atividade de WhatsApp
        $this->assertDatabaseHas('lead_activities', [
            'type' => 'whatsapp',
        ]);
    }
}
