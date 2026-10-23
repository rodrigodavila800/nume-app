<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Pack;
use App\Models\PackPurchase;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\InvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoiceGenerationAndDownloadTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Pack $pack;
    private Cliente $cliente;
    private InvoiceService $invoiceService;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->tenant = Tenant::create([
            'name' => 'Nume Creative S.L.',
            'slug' => 'nume',
            'status' => 'active',
            'subscription_expires_at' => now()->addMonth(),
        ]);

        User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin Nume',
            'email' => 'admin@nume.es',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $this->cliente = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Mateo Fernández',
            'email' => 'mateo@zaragozamedia.es',
            'password' => Hash::make('secret123'),
            'company_name' => 'Zaragoza Media S.L.',
        ]);

        $this->pack = Pack::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pack Audiovisual 4 Vídeos',
            'slug' => 'pack-audiovisual-4-videos',
            'description' => 'Produção de 4 vídeos estratégicos',
            'price' => 1000.00,
            'currency' => 'eur',
            'video_quantity' => 4,
            'status' => 'active',
        ]);

        Setting::set('fiscal_company_name', 'Nume Creative S.L.');
        Setting::set('fiscal_company_cif', 'B99887766');
        Setting::set('fiscal_invoice_series', 'NUM-2026-');

        $this->invoiceService = new InvoiceService();
    }

    public function test_generates_sequential_invoice_number_and_prepares_tax_data(): void
    {
        $purchase = PackPurchase::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'pack_id' => $this->pack->id,
            'payment_gateway' => 'redsys_bizum',
            'gateway_order_reference' => '260900000001',
            'subtotal' => 1000.00,
            'tax_rate' => 21.00,
            'tax_amount' => 210.00,
            'total_amount' => 1210.00,
            'amount' => 1210.00,
            'billing_type' => 'empresa_autonomo',
            'billing_tax_id' => 'B50999888',
            'billing_legal_name' => 'Zaragoza Media S.L.',
            'billing_postal_code' => '50001',
            'billing_city' => 'Zaragoza',
            'billing_country' => 'ES',
            'video_quantity' => 4,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $data = $this->invoiceService->prepareInvoiceData($purchase);

        $this->assertEquals('NUM-2026-00001', $data['invoiceNumber']);
        $this->assertEquals('Nume Creative S.L.', $data['companyName']);
        $this->assertEquals('B99887766', $data['companyCif']);
        $this->assertEquals('Zaragoza Media S.L.', $data['clientLegalName']);
        $this->assertEquals('B50999888', $data['clientTaxId']);
        $this->assertEquals(1000.00, $data['subtotal']);
        $this->assertEquals(210.00, $data['taxAmount']);
        $this->assertEquals(1210.00, $data['totalAmount']);
        $this->assertEquals('Bizum Móvil (BBVA)', $data['paymentMethodName']);
    }

    public function test_renders_valid_pdf_document(): void
    {
        $purchase = PackPurchase::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'pack_id' => $this->pack->id,
            'payment_gateway' => 'redsys_card',
            'gateway_order_reference' => '260900000002',
            'subtotal' => 500.00,
            'tax_rate' => 21.00,
            'tax_amount' => 105.00,
            'total_amount' => 605.00,
            'amount' => 605.00,
            'video_quantity' => 2,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $pdf = $this->invoiceService->renderPdf($purchase);
        $output = $pdf->output();

        $this->assertStringStartsWith('%PDF-', $output);
        $this->assertGreaterThan(1000, strlen($output));
    }

    public function test_saves_invoice_file_to_storage(): void
    {
        $purchase = PackPurchase::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'pack_id' => $this->pack->id,
            'payment_gateway' => 'stripe',
            'subtotal' => 800.00,
            'tax_rate' => 21.00,
            'tax_amount' => 168.00,
            'total_amount' => 968.00,
            'amount' => 968.00,
            'video_quantity' => 3,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $path = $this->invoiceService->saveInvoiceFile($purchase);

        Storage::disk('local')->assertExists($path);
        $purchase->refresh();
        $this->assertEquals($path, $purchase->invoice_path);
        $this->assertNotEmpty($purchase->invoice_number);
    }

    public function test_client_can_download_their_own_invoice(): void
    {
        $purchase = PackPurchase::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'pack_id' => $this->pack->id,
            'payment_gateway' => 'redsys_bizum',
            'subtotal' => 1000.00,
            'tax_rate' => 21.00,
            'tax_amount' => 210.00,
            'total_amount' => 1210.00,
            'amount' => 1210.00,
            'video_quantity' => 4,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($this->cliente, 'cliente')
            ->get(route('portal.purchases.invoice', $purchase));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_idor_protection_prevents_client_from_downloading_others_invoice(): void
    {
        $otherCliente = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Otro Cliente',
            'email' => 'otro@empresa.es',
            'password' => Hash::make('secret123'),
        ]);

        $purchaseOfOther = PackPurchase::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $otherCliente->id,
            'pack_id' => $this->pack->id,
            'payment_gateway' => 'redsys_bizum',
            'subtotal' => 1000.00,
            'tax_rate' => 21.00,
            'tax_amount' => 210.00,
            'total_amount' => 1210.00,
            'amount' => 1210.00,
            'video_quantity' => 4,
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($this->cliente, 'cliente')
            ->get(route('portal.purchases.invoice', $purchaseOfOther));

        $response->assertStatus(403);
    }
}
