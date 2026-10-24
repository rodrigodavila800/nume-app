<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Pack;
use App\Models\PackPurchase;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Services\Billing\InvoiceService;
use App\Services\Billing\TaxCalculationService;
use App\Services\Billing\TenantFiscalSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TenantFiscalSettingsAndInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private User $adminA;

    private User $adminB;

    private User $memberA;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Storage::fake('local');

        $this->tenantA = Tenant::create([
            'name' => 'Produtora Alpha',
            'slug' => 'produtora-alpha',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Produtora Beta',
            'slug' => 'produtora-beta',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $this->adminA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Admin Alpha',
            'email' => 'admin@alpha.test',
            'password' => 'secret12345',
            'role' => 'admin',
        ]);

        $this->adminB = User::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Admin Beta',
            'email' => 'admin@beta.test',
            'password' => 'secret12345',
            'role' => 'admin',
        ]);

        $this->memberA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Editor Alpha',
            'email' => 'editor@alpha.test',
            'password' => 'secret12345',
            'role' => 'editor',
        ]);
    }

    public function test_tenant_fiscal_settings_falls_back_to_global_settings_when_not_customized(): void
    {
        Setting::set('fiscal_company_name', 'Nume Global S.L.');
        Setting::set('fiscal_irpf_rate', '15.00');
        Setting::set('tax_default_iva_rate', '21.00');

        $fiscal = TenantFiscalSettings::for($this->tenantA);

        $this->assertSame('Nume Global S.L.', $fiscal->companyName);
        $this->assertSame(15.0, $fiscal->irpfRate);
        $this->assertSame(21.0, $fiscal->ivaRate);
    }

    public function test_two_tenants_with_different_fiscal_regimes_calculate_taxes_differently(): void
    {
        // Tenant A: Autônomo com IRPF 15%
        TenantSetting::set($this->tenantA, 'fiscal_regime', 'autonomo');
        TenantSetting::set($this->tenantA, 'fiscal_irpf_rate', '15.00');
        TenantSetting::set($this->tenantA, 'tax_default_iva_rate', '21.00');

        // Tenant B: Sociedade Mercantil S.L. (sem retenção de IRPF na fatura)
        TenantSetting::set($this->tenantB, 'fiscal_regime', 'empresa_sl');
        TenantSetting::set($this->tenantB, 'tax_default_iva_rate', '21.00');

        $taxService = new TaxCalculationService;

        $fiscalA = TenantFiscalSettings::for($this->tenantA);
        $taxDataA = $taxService->calculate(
            subtotal: 1000.00,
            countryCode: 'ES',
            taxId: 'B99999999',
            isCompany: true,
            irpfRate: $fiscalA->irpfRate,
            standardIvaRate: $fiscalA->ivaRate
        );

        $fiscalB = TenantFiscalSettings::for($this->tenantB);
        $taxDataB = $taxService->calculate(
            subtotal: 1000.00,
            countryCode: 'ES',
            taxId: 'B99999999',
            isCompany: true,
            irpfRate: $fiscalB->irpfRate,
            standardIvaRate: $fiscalB->ivaRate
        );

        // Produtora A retém 150 EUR de IRPF (Total: 1000 + 210 - 150 = 1060)
        $this->assertEquals(15.0, $taxDataA['irpf_rate']);
        $this->assertEquals(150.00, $taxDataA['irpf_amount']);
        $this->assertEquals(1060.00, $taxDataA['total_amount']);

        // Produtora B não retém IRPF (Total: 1000 + 210 = 1210)
        $this->assertEquals(0.0, $taxDataB['irpf_rate']);
        $this->assertEquals(0.00, $taxDataB['irpf_amount']);
        $this->assertEquals(1210.00, $taxDataB['total_amount']);
    }

    public function test_invoice_sequences_generates_atomic_numbers_per_tenant_without_collision(): void
    {
        TenantSetting::set($this->tenantA, 'fiscal_invoice_series', 'ALPHA-2026-');
        TenantSetting::set($this->tenantB, 'fiscal_invoice_series', 'BETA-2026-');

        $packA = Pack::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Pack A',
            'slug' => 'pack-a',
            'price' => 500,
            'video_quantity' => 4,
            'status' => 'active',
        ]);

        $packB = Pack::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Pack B',
            'slug' => 'pack-b',
            'price' => 500,
            'video_quantity' => 4,
            'status' => 'active',
        ]);

        $clienteA = Cliente::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Cliente A',
            'email' => 'clientea@test.es',
            'password' => 'secret',
        ]);

        $clienteB = Cliente::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Cliente B',
            'email' => 'clienteb@test.es',
            'password' => 'secret',
        ]);

        $purchaseA1 = PackPurchase::create([
            'tenant_id' => $this->tenantA->id,
            'cliente_id' => $clienteA->id,
            'pack_id' => $packA->id,
            'amount' => 500,
            'video_quantity' => 4,
            'status' => 'paid',
            'paid_at' => now(),
            'payment_gateway' => 'stripe',
        ]);

        $purchaseA2 = PackPurchase::create([
            'tenant_id' => $this->tenantA->id,
            'cliente_id' => $clienteA->id,
            'pack_id' => $packA->id,
            'amount' => 500,
            'video_quantity' => 4,
            'status' => 'paid',
            'paid_at' => now(),
            'payment_gateway' => 'stripe',
        ]);

        $purchaseB1 = PackPurchase::create([
            'tenant_id' => $this->tenantB->id,
            'cliente_id' => $clienteB->id,
            'pack_id' => $packB->id,
            'amount' => 500,
            'video_quantity' => 4,
            'status' => 'paid',
            'paid_at' => now(),
            'payment_gateway' => 'stripe',
        ]);

        $service = new InvoiceService;

        $numA1 = $service->generateInvoiceNumber($purchaseA1);
        $numA2 = $service->generateInvoiceNumber($purchaseA2);
        $numB1 = $service->generateInvoiceNumber($purchaseB1);

        $this->assertSame('ALPHA-2026-00001', $numA1);
        $this->assertSame('ALPHA-2026-00002', $numA2);
        $this->assertSame('BETA-2026-00001', $numB1);
    }

    public function test_invoice_saves_snapshot_and_pdf_in_tenant_specific_directory(): void
    {
        TenantSetting::set($this->tenantA, 'fiscal_company_name', 'Alpha Produções Eireli');
        TenantSetting::set($this->tenantA, 'fiscal_company_cif', 'B98765432');
        TenantSetting::set($this->tenantA, 'fiscal_invoice_series', 'ALP-2026-');

        $pack = Pack::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Pack Vídeos Teste',
            'slug' => 'pack-videos-teste',
            'price' => 800,
            'video_quantity' => 4,
            'status' => 'active',
        ]);

        $cliente = Cliente::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Cliente Fatura Teste',
            'email' => 'cliente.fatura@test.es',
            'password' => 'secret',
        ]);

        $purchase = PackPurchase::create([
            'tenant_id' => $this->tenantA->id,
            'cliente_id' => $cliente->id,
            'pack_id' => $pack->id,
            'amount' => 800,
            'subtotal' => 800,
            'tax_rate' => 21,
            'tax_amount' => 168,
            'total_amount' => 968,
            'video_quantity' => 4,
            'status' => 'paid',
            'paid_at' => now(),
            'payment_gateway' => 'stripe',
        ]);

        $service = new InvoiceService;
        $filePath = $service->saveInvoiceFile($purchase);

        $this->assertStringStartsWith('invoices/'.$this->tenantA->id.'/', $filePath);
        Storage::disk('local')->assertExists($filePath);

        $purchase->refresh();
        $this->assertNotNull($purchase->invoice_snapshot);
        $this->assertSame('Alpha Produções Eireli', $purchase->invoice_snapshot['company']['name']);
        $this->assertSame('B98765432', $purchase->invoice_snapshot['company']['cif']);
    }

    public function test_invoice_snapshot_guarantees_historical_immutability(): void
    {
        TenantSetting::set($this->tenantA, 'fiscal_company_name', 'Nome Original 2026');

        $pack = Pack::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Pack Histórico',
            'slug' => 'pack-historico',
            'price' => 300,
            'video_quantity' => 2,
            'status' => 'active',
        ]);

        $cliente = Cliente::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Cliente Histórico',
            'email' => 'historico@test.es',
            'password' => 'secret',
        ]);

        $purchase = PackPurchase::create([
            'tenant_id' => $this->tenantA->id,
            'cliente_id' => $cliente->id,
            'pack_id' => $pack->id,
            'amount' => 300,
            'video_quantity' => 2,
            'status' => 'paid',
            'paid_at' => now(),
            'payment_gateway' => 'stripe',
        ]);

        $service = new InvoiceService;
        $initialData = $service->prepareInvoiceData($purchase);

        $this->assertSame('Nome Original 2026', $initialData['companyName']);

        // Altera as configurações do tenant
        TenantSetting::set($this->tenantA, 'fiscal_company_name', 'Novo Nome Mutado 2027');

        // A fatura já emitida continua retornando o nome original gravado no snapshot
        $reloadedData = $service->prepareInvoiceData($purchase);
        $this->assertSame('Nome Original 2026', $reloadedData['companyName']);
    }

    public function test_fiscal_settings_screen_restricted_to_admin_of_same_tenant(): void
    {
        // 1. Não autenticado -> redireciona para login
        $this->get(route('admin.settings.fiscal'))
            ->assertRedirect(route('login'));

        // 2. Membro não admin do mesmo tenant -> 403
        $this->actingAs($this->memberA)
            ->get(route('admin.settings.fiscal'))
            ->assertForbidden();

        // 3. Admin do tenant A -> acessa com 200 e vê o nome da produtora Alpha
        $response = $this->actingAs($this->adminA)
            ->get(route('admin.settings.fiscal'));

        $response->assertOk();
        $response->assertSee('Produtora Alpha');

        // 4. Admin do tenant A salva seus dados fiscais
        $postResponse = $this->actingAs($this->adminA)
            ->post(route('admin.settings.fiscal.update'), [
                'fiscal_regime' => 'autonomo',
                'fiscal_irpf_rate' => 15,
                'tax_default_iva_rate' => 21,
                'fiscal_company_name' => 'Estúdio Alpha Audiovisual',
                'fiscal_company_cif' => 'B55544433',
            ]);

        $postResponse->assertRedirect(route('admin.settings.fiscal'));

        // Confere no banco que foi gravado para o tenant A, e NÃO para o tenant B
        $this->assertSame('Estúdio Alpha Audiovisual', TenantSetting::get($this->tenantA, 'fiscal_company_name'));
        $this->assertNull(TenantSetting::get($this->tenantB, 'fiscal_company_name'));
    }

    public function test_invoice_generation_supports_null_pack_id_with_quote_fallback(): void
    {
        $cliente = Cliente::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Cliente Corporativo Sem Pack',
            'email' => 'corporativo@test.com',
            'password' => bcrypt('password'),
        ]);

        $quote = \App\Models\Quote::create([
            'tenant_id' => $this->tenantA->id,
            'cliente_id' => $cliente->id,
            'user_id' => $this->adminA->id,
            'client_name' => 'Cliente Corporativo Sem Pack',
            'client_email' => 'corporativo@test.com',
            'project_title' => 'Campanha Publicitária de Verão',
            'inputs' => [],
            'calculation' => [],
            'subtotal' => 2000.00,
            'total_amount' => 2420.00,
            'tax_rate' => 21.00,
            'tax_amount' => 420.00,
            'deposit_amount' => 1210.00,
            'balance_amount' => 1210.00,
            'status' => 'sent',
        ]);

        $purchase = PackPurchase::create([
            'tenant_id' => $this->tenantA->id,
            'cliente_id' => $cliente->id,
            'pack_id' => null, // Compra originada de orçamento sem pacote de catálogo
            'quote_id' => $quote->id,
            'video_quantity' => 1,
            'amount' => 1210.00,
            'subtotal' => 1000.00,
            'tax_rate' => 21.00,
            'tax_amount' => 210.00,
            'total_amount' => 1210.00,
            'payment_gateway' => 'stripe',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $service = app(InvoiceService::class);
        $data = $service->prepareInvoiceData($purchase);

        $this->assertNotNull($purchase->invoice_number);
        $this->assertSame('Campanha Publicitária de Verão', $data['pack']->name ?? $data['packData']['name']);

        // Valida que a renderização do PDF ocorre sem erros com pack_id null
        $pdf = $service->renderPdf($purchase);
        $this->assertNotEmpty($pdf->output());

        // Valida a listagem administrativa de faturas
        $response = $this->actingAs($this->adminA)->get(route('admin.invoices.index'));
        $response->assertOk();
        $response->assertSee('Campanha Publicitária de Verão');
    }
}
