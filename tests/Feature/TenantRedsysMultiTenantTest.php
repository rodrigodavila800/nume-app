<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Pack;
use App\Models\PackPurchase;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Services\Payment\PaymentMethodAvailability;
use App\Services\Payment\RedsysService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantRedsysMultiTenantTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenantA;

    protected Tenant $tenantB;

    protected User $adminA;

    protected User $adminB;

    protected User $userA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name' => 'Produtora Alfa',
            'slug' => 'produtora-alfa',
            'status' => 'active',
            'subscription_expires_at' => now()->addMonth(),
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Produtora Beta',
            'slug' => 'produtora-beta',
            'status' => 'active',
            'subscription_expires_at' => now()->addMonth(),
        ]);

        $this->adminA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'role' => 'admin',
        ]);

        $this->adminB = User::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'role' => 'admin',
        ]);

        $this->userA = User::factory()->create([
            'tenant_id' => $this->tenantA->id,
            'role' => 'viewer',
        ]);
    }

    public function test_tenant_settings_redsys_screen_restricted_to_tenant_admin(): void
    {
        // Creator do mesmo tenant é barrado (403)
        $this->actingAs($this->userA)
            ->get(route('admin.settings.redsys'))
            ->assertStatus(403);

        // Admin acessa normalmente
        $this->actingAs($this->adminA)
            ->get(route('admin.settings.redsys'))
            ->assertStatus(200)
            ->assertSee('TPV Redsys & Bizum da Produtora')
            ->assertSee('Produtora Alfa');
    }

    public function test_admin_can_save_and_update_tenant_redsys_credentials(): void
    {
        $this->actingAs($this->adminA)
            ->post(route('admin.settings.redsys.update'), [
                'redsys_enabled' => '1',
                'redsys_bizum_enabled' => '1',
                'redsys_card_enabled' => '1',
                'redsys_merchant_code' => '111222333',
                'redsys_terminal' => '002',
                'redsys_environment' => 'live',
                'redsys_secret_key' => 'ChaveSecretaSuperSeguraAlfa123456789==',
            ])
            ->assertRedirect(route('admin.settings.redsys'))
            ->assertSessionHas('status');

        $this->assertEquals('1', TenantSetting::get($this->tenantA, 'redsys_enabled'));
        $this->assertEquals('111222333', TenantSetting::get($this->tenantA, 'redsys_merchant_code'));
        $this->assertEquals('002', TenantSetting::get($this->tenantA, 'redsys_terminal'));
        $this->assertEquals('live', TenantSetting::get($this->tenantA, 'redsys_environment'));
        $this->assertEquals('ChaveSecretaSuperSeguraAlfa123456789==', TenantSetting::get($this->tenantA, 'redsys_secret_key'));

        // O tenant B não deve ter sido afetado
        $this->assertNull(TenantSetting::get($this->tenantB, 'redsys_merchant_code'));
    }

    public function test_payment_method_availability_respects_tenant_redsys_configuration(): void
    {
        $pack = Pack::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Pack Teste',
            'slug' => 'pack-teste',
            'price' => 100,
            'currency' => 'eur',
            'video_quantity' => 1,
            'status' => 'active',
        ]);

        // Sem credenciais explícitas nem fallback ativado
        TenantSetting::set($this->tenantA, 'redsys_enabled', '0');
        $availability = PaymentMethodAvailability::forTenant($this->tenantA);

        $this->assertFalse($availability->bizumEnabled($pack));
        $this->assertFalse($availability->redsysCardEnabled($pack));

        // Configura credenciais para o tenant A
        TenantSetting::set($this->tenantA, 'redsys_enabled', '1');
        TenantSetting::set($this->tenantA, 'redsys_bizum_enabled', '1');
        TenantSetting::set($this->tenantA, 'redsys_card_enabled', '1');
        TenantSetting::set($this->tenantA, 'redsys_merchant_code', '111222333');
        TenantSetting::set($this->tenantA, 'redsys_secret_key', base64_encode('test-secret-key-tenant-alfa-1234'));

        $this->assertTrue($availability->bizumEnabled($pack));
        $this->assertTrue($availability->redsysCardEnabled($pack));
    }

    public function test_webhook_authenticates_using_specific_tenant_credentials(): void
    {
        $secretA = base64_encode('secret-key-for-tenant-alfa-32bytes!');
        $secretB = base64_encode('secret-key-for-tenant-beta-32bytes!!');

        TenantSetting::set($this->tenantA, 'redsys_merchant_code', '111111111');
        TenantSetting::set($this->tenantA, 'redsys_terminal', '001');
        TenantSetting::set($this->tenantA, 'redsys_secret_key', $secretA);
        TenantSetting::set($this->tenantA, 'redsys_environment', 'test');

        TenantSetting::set($this->tenantB, 'redsys_merchant_code', '222222222');
        TenantSetting::set($this->tenantB, 'redsys_terminal', '001');
        TenantSetting::set($this->tenantB, 'redsys_secret_key', $secretB);
        TenantSetting::set($this->tenantB, 'redsys_environment', 'test');

        $packA = Pack::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Pack Alfa',
            'slug' => 'pack-alfa',
            'price' => 100,
            'currency' => 'eur',
            'video_quantity' => 1,
            'status' => 'active',
        ]);

        $orderNumber = '012345678901';

        $cliente = Cliente::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Cliente Alfa',
            'email' => 'cliente@alfa.test',
            'password' => bcrypt('password'),
        ]);

        $purchase = PackPurchase::create([
            'tenant_id' => $this->tenantA->id,
            'cliente_id' => $cliente->id,
            'pack_id' => $packA->id,
            'amount' => 121.00,
            'video_quantity' => 1,
            'status' => 'pending',
            'payment_method' => 'bizum',
            'gateway_order_reference' => $orderNumber,
        ]);

        $redsysServiceA = RedsysService::forTenant($this->tenantA);
        $redsysServiceB = RedsysService::forTenant($this->tenantB);

        // Prepara parâmetros com o FUC do tenant A
        $params = [
            'Ds_Date' => date('d/m/Y'),
            'Ds_Hour' => date('H:i'),
            'Ds_Amount' => '12100',
            'Ds_Currency' => '978',
            'Ds_Order' => $orderNumber,
            'Ds_MerchantCode' => '111111111',
            'Ds_Terminal' => '001',
            'Ds_Response' => '0000',
            'Ds_AuthorisationCode' => 'AUTH12',
            'Ds_TransactionType' => '0',
            'Ds_SecurePayment' => '1',
        ];
        $merchantParamsBase64 = base64_encode(json_encode($params));

        // 1. Assinatura forjada com a chave do tenant B (deve ser rejeitada com 400)
        $signatureFromTenantB = $redsysServiceB->calculateSignature($merchantParamsBase64, $orderNumber);
        $responseBad = $this->post('/webhooks/redsys', [
            'Ds_MerchantParameters' => $merchantParamsBase64,
            'Ds_Signature' => $signatureFromTenantB,
        ]);
        $responseBad->assertStatus(400);
        $this->assertEquals('pending', $purchase->fresh()->status);

        // 2. Assinatura legítima com a chave do tenant A (deve ser aceita com 200 e confirmar a compra)
        $signatureFromTenantA = $redsysServiceA->calculateSignature($merchantParamsBase64, $orderNumber);
        $responseOk = $this->post('/webhooks/redsys', [
            'Ds_MerchantParameters' => $merchantParamsBase64,
            'Ds_Signature' => $signatureFromTenantA,
        ]);
        $responseOk->assertStatus(200);
        $this->assertEquals('paid', $purchase->fresh()->status);
    }

    public function test_webhook_rejects_divergent_merchant_code(): void
    {
        $secretA = base64_encode('secret-key-for-tenant-alfa-32bytes!');

        TenantSetting::set($this->tenantA, 'redsys_merchant_code', '111111111');
        TenantSetting::set($this->tenantA, 'redsys_terminal', '001');
        TenantSetting::set($this->tenantA, 'redsys_secret_key', $secretA);
        TenantSetting::set($this->tenantA, 'redsys_environment', 'test');

        $packA = Pack::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Pack Alfa',
            'slug' => 'pack-alfa',
            'price' => 100,
            'currency' => 'eur',
            'video_quantity' => 1,
            'status' => 'active',
        ]);

        $orderNumber = '012345678902';

        $cliente = Cliente::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Cliente Alfa',
            'email' => 'cliente2@alfa.test',
            'password' => bcrypt('password'),
        ]);

        $purchase = PackPurchase::create([
            'tenant_id' => $this->tenantA->id,
            'cliente_id' => $cliente->id,
            'pack_id' => $packA->id,
            'amount' => 121.00,
            'video_quantity' => 1,
            'status' => 'pending',
            'payment_method' => 'bizum',
            'gateway_order_reference' => $orderNumber,
        ]);

        $redsysServiceA = RedsysService::forTenant($this->tenantA);

        // Parâmetros com Ds_MerchantCode falso (diferente de 111111111)
        $params = [
            'Ds_Date' => date('d/m/Y'),
            'Ds_Hour' => date('H:i'),
            'Ds_Amount' => '12100',
            'Ds_Currency' => '978',
            'Ds_Order' => $orderNumber,
            'Ds_MerchantCode' => '999999999',
            'Ds_Terminal' => '001',
            'Ds_Response' => '0000',
            'Ds_AuthorisationCode' => 'AUTH12',
            'Ds_TransactionType' => '0',
            'Ds_SecurePayment' => '1',
        ];
        $merchantParamsBase64 = base64_encode(json_encode($params));
        $signature = $redsysServiceA->calculateSignature($merchantParamsBase64, $orderNumber);

        $response = $this->post('/webhooks/redsys', [
            'Ds_MerchantParameters' => $merchantParamsBase64,
            'Ds_Signature' => $signature,
        ]);

        $response->assertOk();
        $this->assertEquals('pending', $purchase->fresh()->status);
    }
}
