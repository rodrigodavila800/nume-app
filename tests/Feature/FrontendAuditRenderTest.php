<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Cliente;
use App\Models\Pack;
use App\Models\PackPurchase;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Services\Billing\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FrontendAuditRenderTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private Cliente $cliente;

    private Pack $pack;

    private Quote $quote;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Produtora CineArt',
            'slug' => 'cineart',
            'subscription_expires_at' => now()->addMonth(),
        ]);

        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
        ]);

        $this->cliente = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente Corporativo',
            'email' => 'financeiro@corporativo.es',
            'password' => bcrypt('password123'),
        ]);

        // Configurações bancárias e Redsys no tenant
        TenantSetting::set($this->tenant, 'bank_transfer_iban', 'ES9121000418450200051332');
        TenantSetting::set($this->tenant, 'bank_transfer_holder', 'CineArt Studios S.L.');
        TenantSetting::set($this->tenant, 'bank_transfer_account_holder', 'CineArt Studios S.L.');
        TenantSetting::set($this->tenant, 'bank_transfer_bank_name', 'BBVA');
        TenantSetting::set($this->tenant, 'bank_transfer_enabled', '1');
        TenantSetting::set($this->tenant, 'bank_transfer_instructions', 'Enviar comprovante com o código');

        TenantSetting::set($this->tenant, 'redsys_merchant_code', '999008881');
        TenantSetting::set($this->tenant, 'redsys_terminal', '1');
        TenantSetting::set($this->tenant, 'redsys_secret_key', 'sq7HjrUqKpRwXjz57tEsYXppmEAUqqEy');
        TenantSetting::set($this->tenant, 'redsys_enabled', '1');
        TenantSetting::set($this->tenant, 'redsys_card_enabled', '1');
        TenantSetting::set($this->tenant, 'redsys_bizum_enabled', '1');

        // Configurações fiscais
        TenantSetting::set($this->tenant, 'tax_regime', 'general');
        TenantSetting::set($this->tenant, 'standard_iva_rate', 21);
        TenantSetting::set($this->tenant, 'apply_irpf', false);
        TenantSetting::set($this->tenant, 'invoice_series', 'CA-2026');

        $category = Category::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Comerciais',
            'slug' => 'comerciais',
            'type' => 'pack',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->pack = Pack::create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $category->id,
            'name' => 'Pack Publicidade 4K',
            'slug' => 'pack-publicidade-4k',
            'type' => 'one_time',
            'price' => 1500.00,
            'video_quantity' => 2,
            'is_active' => true,
        ]);

        $this->quote = Quote::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->admin->id,
            'number' => 'ORC-2026-0088',
            'project_title' => 'Campaña Primavera 2026',
            'client_name' => 'Cliente Corporativo',
            'client_email' => 'financeiro@corporativo.es',
            'status' => 'sent',
            'valid_until' => now()->addDays(14),
            'approval_token' => Str::random(48),
            'approval_token_expires_at' => now()->addDays(14)->endOfDay(),
            'subtotal' => 2500.00,
            'tax_rate' => 21.00,
            'tax_amount' => 525.00,
            'total_amount' => 3025.00,
            'deposit_amount' => 1512.50,
            'balance_amount' => 1512.50,
            'inputs' => ['videos' => 3, 'prazo' => 15],
            'calculation' => ['total' => 3025.00],
        ]);
    }

    public function test_admin_fiscal_settings_screen_renders_successfully(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.settings.fiscal'));

        $response->assertOk();
        $response->assertViewIs('admin.settings.fiscal');
        $response->assertSee('Perfil Fiscal da Produtora');
        $response->assertSee('Regime Tributário');
        $response->assertSee('Alíquota Padrão de IVA');
        $response->assertSee('Produtora CineArt');
    }

    public function test_admin_redsys_settings_screen_renders_successfully(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.settings.redsys'));

        $response->assertOk();
        $response->assertViewIs('admin.settings.redsys');
        $response->assertSee('TPV Redsys');
        $response->assertSee('Código de Comércio (FUC)');
        $response->assertSee('999008881');
    }

    public function test_admin_bank_transfer_settings_screen_renders_successfully(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.settings.bank-transfer'));

        $response->assertOk();
        $response->assertViewIs('admin.settings.bank_transfer');
        $response->assertSee('Transferência Bancária da Produtora');
        $response->assertSee('Conta Bancária de Depósito');
        $response->assertSee('ES9121000418450200051332');
    }

    public function test_storefront_bank_transfer_instructions_screen_renders_successfully_for_pack(): void
    {
        $purchase = PackPurchase::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'pack_id' => $this->pack->id,
            'amount' => 1500.00,
            'total_amount' => 1815.00,
            'subtotal' => 1500.00,
            'tax_rate' => 21.00,
            'tax_amount' => 315.00,
            'billing_mode' => 'full_upfront',
            'status' => 'pending',
            'payment_gateway' => 'bank_transfer',
            'video_quantity' => 2,
            'bank_transfer_reference' => 'BT-TESTPACK01',
            'expires_at' => now()->addDays(3),
        ]);

        $response = $this->get(route('storefront.bank_transfer', [
            'tenant' => $this->tenant->slug,
            'purchase' => $purchase->id,
        ]));

        $response->assertOk();
        $response->assertViewIs('storefront.bank_transfer');
        $response->assertSee('Instruções para Transferência Bancária');
        $response->assertSee('BT-TESTPACK01');
        $response->assertSee('Pack Publicidade 4K');
        $response->assertSee('ES9121000418450200051332');
    }

    public function test_storefront_bank_transfer_instructions_screen_renders_successfully_for_quote(): void
    {
        $purchase = PackPurchase::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'pack_id' => null,
            'quote_id' => $this->quote->id,
            'amount' => 1512.50,
            'total_amount' => 3025.00,
            'deposit_amount' => 1512.50,
            'balance_amount' => 1512.50,
            'subtotal' => 2500.00,
            'tax_rate' => 21.00,
            'tax_amount' => 525.00,
            'billing_mode' => 'split_50_50',
            'status' => 'pending',
            'payment_gateway' => 'bank_transfer',
            'video_quantity' => 1,
            'bank_transfer_reference' => 'BT-TESTQUOTE01',
            'expires_at' => now()->addDays(3),
        ]);

        $response = $this->get(route('storefront.bank_transfer', [
            'tenant' => $this->tenant->slug,
            'purchase' => $purchase->id,
        ]));

        $response->assertOk();
        $response->assertViewIs('storefront.bank_transfer');
        $response->assertSee('Instruções para Transferência Bancária');
        $response->assertSee('BT-TESTQUOTE01');
        $response->assertSee('Campaña Primavera 2026');
        $response->assertSee('ES9121000418450200051332');
    }

    public function test_portal_bank_transfer_instructions_screen_renders_successfully_for_authenticated_client(): void
    {
        $purchase = PackPurchase::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'pack_id' => $this->pack->id,
            'amount' => 1500.00,
            'total_amount' => 1815.00,
            'subtotal' => 1500.00,
            'tax_rate' => 21.00,
            'tax_amount' => 315.00,
            'billing_mode' => 'full_upfront',
            'status' => 'pending',
            'payment_gateway' => 'bank_transfer',
            'video_quantity' => 2,
            'bank_transfer_reference' => 'BT-PORTAL01',
            'expires_at' => now()->addDays(3),
        ]);

        $response = $this->actingAs($this->cliente, 'cliente')
            ->get(route('portal.packs.bank_transfer', $purchase->id));

        $response->assertOk();
        $response->assertViewIs('portal.packs.bank_transfer');
        $response->assertSee('Instruções para Transferência Bancária');
        $response->assertSee('BT-PORTAL01');
        $response->assertSee('ES9121000418450200051332');
        $response->assertSee('CineArt Studios S.L.');
    }

    public function test_admin_invoices_index_screen_renders_successfully(): void
    {
        // Cria uma compra com fatura emitida para testar a renderização da tabela
        $purchase = PackPurchase::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'pack_id' => $this->pack->id,
            'amount' => 1500.00,
            'total_amount' => 1815.00,
            'subtotal' => 1500.00,
            'tax_rate' => 21.00,
            'tax_amount' => 315.00,
            'billing_mode' => 'full_upfront',
            'status' => 'paid',
            'payment_gateway' => 'redsys_card',
            'video_quantity' => 2,
        ]);

        $invoiceService = app(InvoiceService::class);
        $invoiceNumber = $invoiceService->generateInvoiceNumber($purchase);
        $purchase->update([
            'invoice_number' => $invoiceNumber,
            'invoice_series' => 'CA-2026',
            'paid_at' => now(),
        ]);
        $invoiceService->saveInvoiceFile($purchase);

        $response = $this->actingAs($this->admin)->get(route('admin.invoices.index'));

        $response->assertOk();
        $response->assertViewIs('admin.invoices.index');
        $response->assertSee('Libro de Facturas');
        $response->assertSee('Total Facturado');
        $response->assertSee('Base Imponible');
        $response->assertSee('Cliente Corporativo');
    }

    public function test_quote_public_show_screen_renders_successfully_with_valid_token(): void
    {
        $response = $this->get("/orcamento/{$this->quote->approval_token}");

        $response->assertOk();
        $response->assertViewIs('quotes.public.show');
        $response->assertSee('Campaña Primavera 2026');
        $response->assertSee('ORC-2026-0088');
        $response->assertSee('€ 2.500,00');
        $response->assertSee('€ 525,00');
        $response->assertSee('€ 3.025,00');
        $response->assertSee('€ 1.512,50');
        $response->assertSee('Aprobar Propuesta e Iniciar Grabación');
        $response->assertSee('Transferencia Bancaria');
        $response->assertSee('Tarjeta Bancaria');
        $response->assertSee('Bizum');
    }

    public function test_quote_public_expired_screen_renders_successfully(): void
    {
        $expiredQuote = Quote::create([
            'tenant_id' => $this->tenant->id,
            'number' => 'ORC-2026-EXP1',
            'project_title' => 'Vídeo Institucional Expirado',
            'client_name' => 'Empresa Expirada S.L.',
            'status' => 'sent',
            'valid_until' => now()->subDay(),
            'approval_token' => Str::random(48),
            'approval_token_expires_at' => now()->subDay(),
            'subtotal' => 1000.00,
            'total_amount' => 1210.00,
            'inputs' => ['videos' => 1],
            'calculation' => ['total' => 1210.00],
        ]);

        $response = $this->get("/orcamento/{$expiredQuote->approval_token}");

        $response->assertOk();
        $response->assertViewIs('quotes.public.expired');
        $response->assertSee('Presupuesto Expirado');
        $response->assertSee('Plazo Vencido');
        $response->assertSee('ORC-2026-EXP1');
    }

    public function test_quote_public_rejected_screen_renders_successfully(): void
    {
        $rejectedQuote = Quote::create([
            'tenant_id' => $this->tenant->id,
            'number' => 'ORC-2026-REC1',
            'project_title' => 'Vídeo Comercial Recusado',
            'client_name' => 'Empresa Recusa S.L.',
            'status' => 'rejected',
            'valid_until' => now()->addDays(5),
            'approval_token' => Str::random(48),
            'approval_token_expires_at' => now()->addDays(5),
            'subtotal' => 1000.00,
            'total_amount' => 1210.00,
            'inputs' => ['videos' => 1],
            'calculation' => ['total' => 1210.00],
        ]);

        $response = $this->get("/orcamento/{$rejectedQuote->approval_token}");

        $response->assertOk();
        $response->assertViewIs('quotes.public.rejected');
        $response->assertSee('Presupuesto Declinado');
        $response->assertSee('Propuesta Declinada');
        $response->assertSee('ORC-2026-REC1');
    }

    public function test_quote_public_accepted_screen_renders_successfully(): void
    {
        $acceptedQuote = Quote::create([
            'tenant_id' => $this->tenant->id,
            'number' => 'ORC-2026-OK1',
            'project_title' => 'Vídeo Aprovado e Pago',
            'client_name' => 'Empresa Aprovada S.L.',
            'status' => 'accepted',
            'accepted_at' => now()->subHours(2),
            'valid_until' => now()->addDays(5),
            'approval_token' => Str::random(48),
            'approval_token_expires_at' => now()->addDays(5),
            'subtotal' => 1000.00,
            'total_amount' => 1210.00,
            'inputs' => ['videos' => 1],
            'calculation' => ['total' => 1210.00],
        ]);

        $response = $this->get("/orcamento/{$acceptedQuote->approval_token}");

        $response->assertOk();
        $response->assertViewIs('quotes.public.accepted');
        $response->assertSee('¡Presupuesto Aprobado con Éxito!');
        $response->assertSee('Propuesta Aceptada');
        $response->assertSee('ORC-2026-OK1');
        $response->assertSee('Vídeo Aprovado e Pago');
    }
}
