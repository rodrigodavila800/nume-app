<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Pack;
use App\Models\PackPurchase;
use App\Models\Tenant;
use App\Services\Payment\RedsysService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RedsysCheckoutAndFiscalEngineTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Pack $pack;
    private Cliente $cliente;
    private RedsysService $redsysService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Nume Creative',
            'slug' => 'nume',
            'status' => 'active',
            'subscription_expires_at' => now()->addMonth(),
        ]);

        \App\Models\User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Staff Nume',
            'email' => 'staff@nume.app',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        $this->cliente = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Carlos Zaragoza',
            'email' => 'carlos@empresa.es',
            'password' => Hash::make('secret123'),
        ]);

        $this->pack = Pack::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pack Sofi Retention 4 Vídeos',
            'slug' => 'pack-sofi-retention-4-videos',
            'description' => '4 vídeos de alta retenção',
            'price' => 1000.00,
            'currency' => 'eur',
            'video_quantity' => 4,
            'status' => 'active',
        ]);

        $this->redsysService = new RedsysService(
            merchantCode: '999008881',
            terminal: '001',
            secretKey: 'sq7HjrUOBfKmC5Jw6ZGg14hqEcTZip5t',
            environment: 'test'
        );

        \App\Models\Setting::set('fiscal_regime', 'empresa_sl', 'fiscal');
    }

    public function test_checkout_with_bizum_calculates_iva_and_returns_redsys_redirect_view(): void
    {
        $response = $this->actingAs($this->cliente, 'cliente')
            ->post(route('storefront.checkout', [
                'tenant' => $this->tenant->slug,
                'pack' => $this->pack->slug,
            ]), [
                'payment_method' => 'bizum',
                'billing_type' => 'empresa_autonomo',
                'billing_tax_id' => 'B50123456',
                'billing_legal_name' => 'Zaragoza Media S.L.',
                'billing_postal_code' => '50001',
                'billing_city' => 'Zaragoza',
                'billing_country' => 'ES',
            ]);

        $response->assertStatus(200);
        $response->assertViewIs('storefront.redsys_redirect');
        $response->assertViewHasAll(['payload', 'purchase', 'pack', 'tenant']);

        $purchase = PackPurchase::where('cliente_id', $this->cliente->id)->first();
        $this->assertNotNull($purchase);
        $this->assertEquals('bizum', $purchase->payment_gateway);
        $this->assertEquals(1000.00, $purchase->subtotal);
        $this->assertEquals(21.00, $purchase->tax_rate);
        $this->assertEquals(210.00, $purchase->tax_amount);
        $this->assertEquals(1210.00, $purchase->total_amount);
        $this->assertEquals('pending', $purchase->status);
        $this->assertEquals('B50123456', $purchase->billing_tax_id);
        $this->assertNotEmpty($purchase->gateway_order_reference);
    }

    public function test_redsys_webhook_approves_payment_and_provisions_credits(): void
    {
        $orderNumber = $this->redsysService->generateOrderNumber();

        $purchase = PackPurchase::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'pack_id' => $this->pack->id,
            'payment_gateway' => 'redsys_bizum',
            'gateway_order_reference' => $orderNumber,
            'subtotal' => 1000.00,
            'tax_rate' => 21.00,
            'tax_amount' => 210.00,
            'total_amount' => 1210.00,
            'amount' => 1210.00,
            'status' => 'pending',
            'video_quantity' => 4,
        ]);

        $params = [
            'Ds_Amount' => '121000',
            'Ds_Order' => $orderNumber,
            'Ds_MerchantCode' => '999008881',
            'Ds_Currency' => '978',
            'Ds_Response' => '0000', // Aprovado
            'Ds_AuthorisationCode' => '778899',
            'Ds_PayMethod' => 'z', // Bizum
        ];

        $merchantParametersBase64 = base64_encode(json_encode($params));
        $signature = $this->redsysService->calculateSignature($merchantParametersBase64, $orderNumber);

        $response = $this->post(route('redsys.webhook'), [
            'Ds_SignatureVersion' => 'HMAC_SHA256_V1',
            'Ds_MerchantParameters' => $merchantParametersBase64,
            'Ds_Signature' => $signature,
        ]);

        $response->assertStatus(200);
        $response->assertSeeText('OK');

        $purchase->refresh();
        $this->assertEquals('paid', $purchase->status);
        $this->assertNotNull($purchase->paid_at);
        $this->assertEquals('redsys_bizum', $purchase->payment_gateway);

        // Verifica criação automática da produção inicial
        $this->assertEquals(1, $purchase->productions()->count());
        $this->assertEquals('draft', $purchase->productions()->first()->status);
    }

    public function test_redsys_webhook_rejects_tampered_signature(): void
    {
        $orderNumber = $this->redsysService->generateOrderNumber();

        $params = [
            'Ds_Amount' => '121000',
            'Ds_Order' => $orderNumber,
            'Ds_Response' => '0000',
        ];

        $merchantParametersBase64 = base64_encode(json_encode($params));
        $fakeSignature = base64_encode('fake_fraudulent_signature');

        $response = $this->post(route('redsys.webhook'), [
            'Ds_SignatureVersion' => 'HMAC_SHA256_V1',
            'Ds_MerchantParameters' => $merchantParametersBase64,
            'Ds_Signature' => $fakeSignature,
        ]);

        $response->assertStatus(400);
        $response->assertSeeText('Assinatura inválida');
    }
}
