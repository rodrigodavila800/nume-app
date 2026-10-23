<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GatewaySettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_save_and_update_gateway_settings_in_database(): void
    {
        $tenant = Tenant::create([
            'name' => 'Produtora Teste',
            'slug' => 'produtora-teste',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.settings.gateways.update'), [
            'stripe_mode' => 'test',
            'stripe_platform_fee_percent' => 10.0,
            'stripe_key' => 'pk_test_sample_key_123',
            'stripe_secret' => 'sk_test_sample_secret_456',
            'stripe_webhook_secret' => 'whsec_sample_webhook_789',
            'stripe_price_id' => 'price_sample_id',
            'redsys_enabled' => '1',
            'redsys_environment' => 'test',
            'redsys_merchant_code' => '999008881',
            'redsys_terminal' => '001',
            'redsys_secret_key' => 'sq7HjrUOBfKmC5Jw6ZGg14hqEcTZip5t',
            'tax_default_iva_rate' => 21.00,
            'fiscal_regime' => 'autonomo',
            'fiscal_irpf_rate' => 15.00,
            'fiscal_company_name' => 'Rodrigo Souza Audiovisual',
            'fiscal_company_cif' => '12345678Z',
            'fiscal_company_address' => 'Calle Alfonso I, 12, Planta 3',
            'fiscal_company_postal_code' => '50003',
            'fiscal_company_city' => 'Zaragoza',
            'fiscal_company_email' => 'facturacion@numecreative.es',
            'fiscal_invoice_series' => 'NUM-2026-',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.settings.gateways'));
        $response->assertSessionHas('success');

        // Valida que foi salvo e criptografado no banco
        $this->assertEquals('autonomo', Setting::get('fiscal_regime'));
        $this->assertEquals(15.00, (float) Setting::get('fiscal_irpf_rate'));
        $this->assertEquals('12345678Z', Setting::get('fiscal_company_cif'));

        // Valida que foi salvo e criptografado no banco
        $dbSecret = Setting::get('stripe_secret');
        $this->assertEquals('sk_test_sample_secret_456', $dbSecret);

        $dbKey = Setting::get('stripe_key');
        $this->assertEquals('pk_test_sample_key_123', $dbKey);

        $this->assertEquals('sk_test_sample_secret_456', config('services.stripe.secret'));
        $this->assertEquals('pk_test_sample_key_123', config('services.stripe.key'));
    }
}
