<?php

namespace Tests\Feature;

use App\Mail\QuoteProposalMail;
use App\Models\Cliente;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Quotes\QuoteCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class QuoteModuleTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name' => 'NUME Zaragoza',
            'slug' => 'nume-zaragoza',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Produtora B',
            'slug' => 'produtora-b',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $this->userA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Staff A',
            'email' => 'staff-a@nume.es',
            'password' => 'secret12345',
            'role' => 'admin',
        ]);

        $this->userB = User::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Staff B',
            'email' => 'staff-b@produtora.es',
            'password' => 'secret12345',
            'role' => 'admin',
        ]);
    }

    public function test_quote_calculator_produces_exact_math_as_artifact(): void
    {
        $calculator = new QuoteCalculatorService();

        $inputs = [
            'videos' => 4,
            'pre' => 2,
            'gravacao' => 4,
            'edicao' => 12,
            'motion' => 0,
            'filmmakers' => 2,
            'valor_hora' => 12.04,
            'valor_hora_editor' => 14.85,
            'drone' => false,
            'drone_valor' => 150,
            'iva' => 21,
            'margem' => 0,
            'valor_unit' => 0,
            'adicional' => 0,
            'cambios' => 3,
            'prazo' => 15,
            'extras' => [],
        ];

        $res = $calculator->calculate($inputs);

        // h_grav = 4 * 2 = 8
        $this->assertEquals(8, $res['h_grav']);
        // horas = 2 + 8 + 12 + 0 = 22
        $this->assertEquals(22, $res['horas']);
        // h_fm = 2 + 8 = 10
        $this->assertEquals(10, $res['h_fm']);
        // custo_fm = 10 * 12.04 = 120.40
        $this->assertEquals(120.40, $res['custo_fm']);
        // h_ed = 12 + 0 = 12
        $this->assertEquals(12, $res['h_ed']);
        // custo_ed = 12 * 14.85 = 178.20
        $this->assertEquals(178.20, $res['custo_ed']);
        // trabalho = 120.40 + 178.20 = 298.60
        $this->assertEquals(298.60, $res['trabalho']);
        $this->assertEquals(298.60, $res['preco_base']);
        $this->assertEquals(298.60, $res['preco']);
        // iva = 298.60 * 0.21 = 62.706
        $this->assertEqualsWithDelta(62.706, $res['iva'], 0.01);
        // total = 361.306
        $this->assertEqualsWithDelta(361.31, $res['total_com_iva'], 0.01);
        $this->assertEqualsWithDelta(180.65, $res['sinal'], 0.02);
    }

    public function test_staff_can_view_quotes_index_and_create(): void
    {
        $response = $this->actingAs($this->userA)->get(route('quotes.index'));
        $response->assertOk();
        $response->assertSee('Orçamentos & Propostas Estratégicas');

        $createResponse = $this->actingAs($this->userA)->get(route('quotes.create'));
        $createResponse->assertOk();
        $createResponse->assertSee('Orçamentos & Propostas NUME');
    }

    public function test_staff_can_store_and_update_quote(): void
    {
        $cliente = Cliente::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Bodega Zaragoza',
            'email' => 'contato@bodega.es',
            'password' => 'secret12345',
        ]);

        $payload = [
            'cliente_id' => $cliente->id,
            'client_name' => 'Bodega Zaragoza S.L.',
            'client_contact' => '+34 976 123 456',
            'client_email' => 'contato@bodega.es',
            'project_title' => 'Vídeos Vendimia 2026',
            'language' => 'es',
            'request_text' => 'Precisamos de 4 vídeos curtos da vinícola.',
            'inputs' => [
                'videos' => 4,
                'pre' => 2,
                'gravacao' => 4,
                'edicao' => 12,
                'motion' => 0,
                'filmmakers' => 2,
                'valor_hora' => 12.04,
                'valor_hora_editor' => 14.85,
                'drone' => 1,
                'drone_valor' => 150,
                'iva' => 21,
                'margem' => 10,
                'valor_unit' => 0,
                'adicional' => 50,
                'cambios' => 3,
                'prazo' => 15,
                'extras' => [
                    ['nome' => 'Locución profesional', 'valor' => 80],
                ],
            ],
        ];

        $response = $this->actingAs($this->userA)->post(route('quotes.store'), $payload);
        $response->assertSessionHasNoErrors();

        $quote = Quote::withoutGlobalScope('tenant')->where('tenant_id', $this->tenantA->id)->first();
        $this->assertNotNull($quote);
        $this->assertEquals('Bodega Zaragoza S.L.', $quote->client_name);
        $this->assertEquals('draft', $quote->status);
        $this->assertNotEmpty($quote->uuid);
        $this->assertStringStartsWith('NUME-', $quote->number);

        $response->assertRedirect(route('quotes.edit', $quote));

        // Update
        $payload['client_name'] = 'Bodega Zaragoza Actualizada';
        $updateResponse = $this->actingAs($this->userA)->put(route('quotes.update', $quote), $payload);
        $updateResponse->assertSessionHasNoErrors();

        $quote->refresh();
        $this->assertEquals('Bodega Zaragoza Actualizada', $quote->client_name);
    }

    public function test_sofi_estimator_endpoint_returns_json(): void
    {
        $response = $this->actingAs($this->userA)->postJson(route('quotes.estimate'), [
            'description' => 'Precisamos de 6 vídeos curtos para nossa empresa, gravação o dia todo com drone.',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'reading' => [
                'pedido' => [
                    'videos',
                    'pre',
                    'gravacao',
                    'edicao',
                    'filmmakers',
                    'drone',
                ],
                'resumo',
                'suposicoes',
                'duvidas',
            ],
            'calc',
        ]);

        $data = $response->json();
        $this->assertEquals(6, $data['reading']['pedido']['videos']);
        $this->assertTrue($data['reading']['pedido']['drone']);
    }

    public function test_quote_pdf_can_be_downloaded(): void
    {
        $quote = Quote::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->userA->id,
            'client_name' => 'Cliente Teste PDF',
            'project_title' => 'Campaña Primavera',
            'inputs' => [
                'videos' => 4,
                'pre' => 2,
                'gravacao' => 4,
                'edicao' => 12,
                'motion' => 0,
                'filmmakers' => 2,
                'valor_hora' => 12.04,
                'valor_hora_editor' => 14.85,
                'drone' => false,
                'drone_valor' => 150,
                'iva' => 21,
                'margem' => 0,
                'valor_unit' => 0,
                'adicional' => 0,
                'cambios' => 3,
                'prazo' => 15,
                'extras' => [],
            ],
            'calculation' => [
                'horas' => 22,
                'preco' => 298.60,
                'iva' => 62.71,
                'total_com_iva' => 361.31,
                'sinal' => 180.65,
                'na_entrega' => 180.66,
            ],
            'subtotal' => 298.60,
            'total_amount' => 361.31,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->userA)->get(route('quotes.pdf', $quote));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $this->assertNotEmpty($response->getContent());
    }

    public function test_quote_can_be_sent_via_smtp_mail(): void
    {
        Mail::fake();

        $quote = Quote::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->userA->id,
            'client_name' => 'Restaurante El Pilar',
            'client_email' => 'info@elpilar.es',
            'project_title' => 'Vídeos Gastro 2026',
            'inputs' => [
                'videos' => 2,
                'pre' => 1,
                'gravacao' => 4,
                'edicao' => 6,
                'motion' => 0,
                'filmmakers' => 1,
                'valor_hora' => 12.04,
                'valor_hora_editor' => 14.85,
                'drone' => false,
                'drone_valor' => 150,
                'iva' => 21,
                'margem' => 0,
                'valor_unit' => 0,
                'adicional' => 0,
                'cambios' => 3,
                'prazo' => 10,
                'extras' => [],
            ],
            'calculation' => [
                'horas' => 11,
                'preco' => 149.30,
                'iva' => 31.35,
                'total_com_iva' => 180.65,
                'sinal' => 90.33,
                'na_entrega' => 90.32,
            ],
            'subtotal' => 149.30,
            'total_amount' => 180.65,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->userA)->postJson(route('quotes.send', $quote), [
            'email' => 'cliente@elpilar.es',
            'subject' => 'Presupuesto Vídeos Gastro · NUME',
            'message' => 'Adjunto encontrará la propuesta.',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        Mail::assertSent(QuoteProposalMail::class, function ($mail) {
            return $mail->hasTo('cliente@elpilar.es');
        });

        $quote->refresh();
        $this->assertEquals('sent', $quote->status);
        $this->assertNotNull($quote->sent_at);
        $this->assertEquals('cliente@elpilar.es', $quote->client_email);
    }

    public function test_tenant_isolation_prevents_idor(): void
    {
        $quoteA = Quote::create([
            'tenant_id' => $this->tenantA->id,
            'user_id' => $this->userA->id,
            'client_name' => 'Cliente Secreto Tenant A',
            'inputs' => ['videos' => 1, 'prazo' => 10],
            'calculation' => ['preco' => 100, 'total_com_iva' => 121],
            'subtotal' => 100,
            'total_amount' => 121,
        ]);

        // User B from Tenant B cannot access Quote A (403 Forbidden via Policy or 404 via Scope)
        $response = $this->actingAs($this->userB)->get(route('quotes.edit', $quoteA));
        $this->assertTrue(in_array($response->status(), [403, 404]));

        // User B cannot download PDF of Quote A
        $pdfResponse = $this->actingAs($this->userB)->get(route('quotes.pdf', $quoteA));
        $this->assertTrue(in_array($pdfResponse->status(), [403, 404]));

        // User B cannot send Quote A
        $sendResponse = $this->actingAs($this->userB)->postJson(route('quotes.send', $quoteA), [
            'email' => 'hacker@tenant-b.com',
        ]);
        $this->assertTrue(in_array($sendResponse->status(), [403, 404]));
    }
}
