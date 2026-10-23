<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Pack;
use App\Models\PackPurchase;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceManagementAndExportTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $adminUser;
    protected Cliente $cliente;
    protected Pack $pack;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'NUME Creative Studio',
            'slug' => 'nume-studio',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $this->adminUser = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin Nume',
            'email' => 'admin@numecreative.es',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $this->cliente = Cliente::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Alejandro Martínez',
            'email' => 'alejandro@cliente.es',
            'password' => bcrypt('secret123'),
            'status' => 'active',
        ]);

        $this->pack = Pack::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pack TikTok Viral',
            'slug' => 'pack-tiktok-viral',
            'price' => 1000.00,
            'video_quantity' => 10,
            'status' => 'active',
        ]);
    }

    public function test_admin_can_view_invoices_dashboard(): void
    {
        PackPurchase::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'pack_id' => $this->pack->id,
            'video_quantity' => 10,
            'amount' => 1210.00,
            'subtotal' => 1000.00,
            'tax_rate' => 21.0,
            'tax_amount' => 210.00,
            'total_amount' => 1210.00,
            'payment_gateway' => 'bizum',
            'invoice_number' => 'NUM-2026-00001',
            'billing_legal_name' => 'Empresa Alejandro SL',
            'billing_tax_id' => 'B50123456',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.invoices.index'));

        $response->assertOk();
        $response->assertSee('Libro de Facturas');
        $response->assertSee('NUM-2026-00001');
        $response->assertSee('Empresa Alejandro SL');
        $response->assertSee('B50123456');
        $response->assertSee('1.000,00'); // Base imponible
        $response->assertSee('210,00');   // IVA 21%
        $response->assertSee('1.210,00'); // Total
        $response->assertSee('Bizum');
    }

    public function test_admin_can_filter_invoices_by_gateway_and_search(): void
    {
        $bizumPurchase = PackPurchase::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'pack_id' => $this->pack->id,
            'video_quantity' => 10,
            'amount' => 1210.00,
            'subtotal' => 1000.00,
            'tax_rate' => 21.0,
            'tax_amount' => 210.00,
            'total_amount' => 1210.00,
            'payment_gateway' => 'bizum',
            'invoice_number' => 'NUM-2026-00001',
            'billing_legal_name' => 'Cliente Bizum SL',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $stripePurchase = PackPurchase::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'pack_id' => $this->pack->id,
            'video_quantity' => 10,
            'amount' => 605.00,
            'subtotal' => 500.00,
            'tax_rate' => 21.0,
            'tax_amount' => 105.00,
            'total_amount' => 605.00,
            'payment_gateway' => 'stripe',
            'invoice_number' => 'NUM-2026-00002',
            'billing_legal_name' => 'Cliente Stripe SL',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        // Filtro por Bizum
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.invoices.index', ['gateway' => 'bizum']));

        $response->assertOk();
        $response->assertSee('Cliente Bizum SL');
        $response->assertDontSee('Cliente Stripe SL');

        // Filtro por Busca
        $responseSearch = $this->actingAs($this->adminUser)
            ->get(route('admin.invoices.index', ['search' => 'NUM-2026-00002']));

        $responseSearch->assertOk();
        $responseSearch->assertSee('Cliente Stripe SL');
        $responseSearch->assertDontSee('Cliente Bizum SL');
    }

    public function test_admin_can_export_csv_for_gestoria(): void
    {
        PackPurchase::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'pack_id' => $this->pack->id,
            'video_quantity' => 10,
            'amount' => 1210.00,
            'subtotal' => 1000.00,
            'tax_rate' => 21.0,
            'tax_amount' => 210.00,
            'total_amount' => 1210.00,
            'payment_gateway' => 'bizum',
            'gateway_order_reference' => 'BZ-12345',
            'invoice_number' => 'NUM-2026-00001',
            'billing_legal_name' => 'Empresa Export SL',
            'billing_tax_id' => 'B50999888',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.invoices.export'));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="Facturas-Gestoria-', $response->headers->get('Content-Disposition'));

        // Obter conteúdo streamado
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        // Verifica cabeçalhos contábeis espanhóis
        $this->assertStringContainsString('Fecha_Factura;Numero_Factura;Serie;NIF_CIF_Cliente;Razon_Social_Cliente', $content);
        $this->assertStringContainsString('NUM-2026-00001', $content);
        $this->assertStringContainsString('B50999888', $content);
        $this->assertStringContainsString('Empresa Export SL', $content);
        $this->assertStringContainsString('1000,00', $content);
        $this->assertStringContainsString('210,00', $content);
        $this->assertStringContainsString('1210,00', $content);
        $this->assertStringContainsString('BIZUM', $content);
    }

    public function test_admin_can_download_invoice_pdf(): void
    {
        $purchase = PackPurchase::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'pack_id' => $this->pack->id,
            'video_quantity' => 10,
            'amount' => 1210.00,
            'subtotal' => 1000.00,
            'tax_rate' => 21.0,
            'tax_amount' => 210.00,
            'total_amount' => 1210.00,
            'payment_gateway' => 'redsys_card',
            'invoice_number' => 'NUM-2026-00001',
            'billing_legal_name' => 'Descarga SL',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.invoices.download', $purchase));

        $response->assertOk();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('Factura-NUM-2026-00001.pdf', $response->headers->get('Content-Disposition'));
    }

    public function test_tenant_isolation_prevents_viewing_other_tenants_invoices(): void
    {
        $otherTenant = Tenant::create([
            'name' => 'Outra Produtora SL',
            'slug' => 'outra-produtora',
            'status' => 'active',
        ]);

        $purchaseOfOther = PackPurchase::withoutGlobalScope('tenant')->create([
            'tenant_id' => $otherTenant->id,
            'cliente_id' => $this->cliente->id,
            'pack_id' => $this->pack->id,
            'video_quantity' => 10,
            'amount' => 500.00,
            'subtotal' => 500.00,
            'tax_rate' => 21.0,
            'tax_amount' => 105.00,
            'total_amount' => 605.00,
            'payment_gateway' => 'bizum',
            'invoice_number' => 'OUTRA-2026-00099',
            'billing_legal_name' => 'Empresa Secreta SL',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        // Não deve aparecer na listagem
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.invoices.index'));

        $response->assertOk();
        $response->assertDontSee('OUTRA-2026-00099');
        $response->assertDontSee('Empresa Secreta SL');

        // Não pode fazer download do PDF (deve dar 403 ou 404 pelo isolamento multi-tenant)
        $responseDownload = $this->actingAs($this->adminUser)
            ->get(route('admin.invoices.download', $purchaseOfOther));

        $this->assertTrue(in_array($responseDownload->status(), [403, 404], true));
    }
}
