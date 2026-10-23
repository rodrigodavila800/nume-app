<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Pack;
use App\Models\PackPurchase;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminInvoiceEscrowAndFiscalTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $adminUser;
    private Cliente $cliente;
    private Pack $pack5050;
    private Pack $packUpfront;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Produtora Fiscal Zaragoza S.L.',
            'slug' => 'produtora-fiscal-zaragoza',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $this->adminUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
        ]);

        $this->cliente = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Empresa Cliente S.A.',
            'email' => 'financeiro@empresacliente.es',
            'password' => bcrypt('password123'),
        ]);

        $this->pack5050 = Pack::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pacote Produção 50/50',
            'slug' => 'pacote-producao-50-50',
            'price' => 1000.00,
            'currency' => 'eur',
            'billing_type' => 'one_time',
            'billing_mode' => 'split_50_50',
            'deposit_percentage' => 50,
            'video_quantity' => 2,
            'status' => 'active',
        ]);

        $this->packUpfront = Pack::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pacote Upfront 100%',
            'slug' => 'pacote-upfront-100',
            'price' => 600.00,
            'currency' => 'eur',
            'billing_type' => 'one_time',
            'billing_mode' => 'full_upfront',
            'video_quantity' => 1,
            'status' => 'active',
        ]);
    }

    public function test_admin_invoices_index_renders_metrics_including_escrow_pending_balance(): void
    {
        // Compra 1: 50/50 com saldo pendente de 605 EUR (com IVA 21%)
        PackPurchase::create([
            'tenant_id' => $this->tenant->id,
            'pack_id' => $this->pack5050->id,
            'cliente_id' => $this->cliente->id,
            'subtotal' => 1000.00,
            'tax_rate' => 21.00,
            'tax_amount' => 210.00,
            'total_amount' => 1210.00,
            'amount' => 605.00,
            'billing_mode' => 'split_50_50',
            'deposit_amount' => 605.00,
            'deposit_paid_at' => now(),
            'balance_amount' => 605.00,
            'balance_status' => 'pending',
            'payment_gateway' => 'stripe',
            'status' => 'paid',
            'video_quantity' => 2,
            'remaining_videos' => 2,
            'invoice_number' => 'NUM-2026-00001',
            'paid_at' => now(),
        ]);

        // Compra 2: 100% upfront já quitado
        PackPurchase::create([
            'tenant_id' => $this->tenant->id,
            'pack_id' => $this->packUpfront->id,
            'cliente_id' => $this->cliente->id,
            'subtotal' => 600.00,
            'tax_rate' => 21.00,
            'tax_amount' => 126.00,
            'total_amount' => 726.00,
            'amount' => 726.00,
            'billing_mode' => 'full_upfront',
            'deposit_amount' => 726.00,
            'balance_amount' => 0.00,
            'balance_status' => 'not_applicable',
            'payment_gateway' => 'stripe',
            'status' => 'paid',
            'video_quantity' => 1,
            'remaining_videos' => 1,
            'invoice_number' => 'NUM-2026-00002',
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.invoices.index'));

        $response->assertOk();
        $response->assertSee('Custodia Activa');
        $response->assertSee('605,00'); // Saldo de custódia pendente
        $response->assertSee('50/50 Split');
        $response->assertSee('Saldo Pendente: € 605,00');
    }

    public function test_admin_invoices_filters_by_billing_mode_and_escrow_status(): void
    {
        // Compra A: 50/50 Pendente
        PackPurchase::create([
            'tenant_id' => $this->tenant->id,
            'pack_id' => $this->pack5050->id,
            'cliente_id' => $this->cliente->id,
            'total_amount' => 1000.00,
            'amount' => 500.00,
            'billing_mode' => 'split_50_50',
            'deposit_amount' => 500.00,
            'balance_amount' => 500.00,
            'balance_status' => 'pending',
            'status' => 'paid',
            'video_quantity' => 2,
            'remaining_videos' => 2,
            'invoice_number' => 'INV-ESCROW-PENDING',
            'paid_at' => now(),
        ]);

        // Compra B: 100% Upfront
        PackPurchase::create([
            'tenant_id' => $this->tenant->id,
            'pack_id' => $this->packUpfront->id,
            'cliente_id' => $this->cliente->id,
            'total_amount' => 500.00,
            'amount' => 500.00,
            'billing_mode' => 'full_upfront',
            'status' => 'paid',
            'video_quantity' => 1,
            'remaining_videos' => 1,
            'invoice_number' => 'INV-FULL-UPFRONT',
            'paid_at' => now(),
        ]);

        // Filtrar apenas operações com saldo de custódia pendente
        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.invoices.index', ['billing_mode' => 'pending_escrow']));

        $response->assertOk();
        $response->assertSee('INV-ESCROW-PENDING');
        $response->assertDontSee('INV-FULL-UPFRONT');

        // Filtrar apenas operações com 100% upfront
        $responseUpfront = $this->actingAs($this->adminUser)
            ->get(route('admin.invoices.index', ['billing_mode' => 'full_upfront']));

        $responseUpfront->assertOk();
        $responseUpfront->assertSee('INV-FULL-UPFRONT');
        $responseUpfront->assertDontSee('INV-ESCROW-PENDING');
    }

    public function test_admin_invoices_export_csv_includes_escrow_and_billing_mode_columns(): void
    {
        PackPurchase::create([
            'tenant_id' => $this->tenant->id,
            'pack_id' => $this->pack5050->id,
            'cliente_id' => $this->cliente->id,
            'subtotal' => 800.00,
            'total_amount' => 968.00,
            'amount' => 484.00,
            'billing_mode' => 'split_50_50',
            'deposit_amount' => 484.00,
            'balance_amount' => 484.00,
            'balance_status' => 'pending',
            'status' => 'paid',
            'video_quantity' => 2,
            'remaining_videos' => 2,
            'invoice_number' => 'NUM-2026-CSV-01',
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.invoices.export'));

        $response->assertOk();
        $this->assertEquals('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));

        // Obtém o conteúdo do stream
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        $this->assertStringContainsString('Modalidad_Cobro', $content);
        $this->assertStringContainsString('Deposito_Cobrado_EUR', $content);
        $this->assertStringContainsString('Saldo_Entrega_EUR', $content);
        $this->assertStringContainsString('Estado_Saldo', $content);
        $this->assertStringContainsString('50/50 Split (Custodia)', $content);
        $this->assertStringContainsString('PENDING', $content);
    }
}
