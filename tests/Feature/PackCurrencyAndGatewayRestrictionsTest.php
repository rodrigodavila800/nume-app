<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Pack;
use App\Models\Setting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Valida o padrão de moeda EUR, a validação de currency (eur/brl)
 * e o bloqueio de Redsys/Bizum para pacotes fora de EUR.
 */
class PackCurrencyAndGatewayRestrictionsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $this->tenant = Tenant::create([
            'name' => 'Produtora Estúdio',
            'slug' => 'produtora-estudio',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $this->admin = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin Estúdio',
            'email' => 'admin@estudio.test',
            'password' => 'secret12345',
            'role' => 'admin',
        ]);
    }

    public function test_pack_controller_only_accepts_eur_or_brl_and_normalizes_to_lowercase(): void
    {
        $this->actingAs($this->admin);

        // Rejeita moeda não suportada (ex: usd)
        $response = $this->post(route('packs.store'), [
            'name' => 'Pack Dólar',
            'video_quantity' => 4,
            'price' => 100,
            'currency' => 'usd',
            'billing_type' => 'one_time',
        ]);
        $response->assertSessionHasErrors('currency');

        // Aceita BRL em maiúsculas e grava minúsculo
        $response = $this->post(route('packs.store'), [
            'name' => 'Pack Real',
            'video_quantity' => 4,
            'price' => 500,
            'currency' => 'BRL',
            'billing_type' => 'one_time',
        ]);
        $response->assertSessionDoesntHaveErrors();

        $pack = Pack::where('name', 'Pack Real')->first();
        $this->assertNotNull($pack);
        $this->assertSame('brl', $pack->currency);

        // Padrão default quando não informado é eur
        $this->post(route('packs.store'), [
            'name' => 'Pack Default',
            'video_quantity' => 2,
            'price' => 200,
            'billing_type' => 'one_time',
        ]);
        $packDefault = Pack::where('name', 'Pack Default')->first();
        $this->assertSame('eur', $packDefault->currency);
    }

    public function test_setting_payment_methods_hides_redsys_and_bizum_for_non_eur_packs(): void
    {
        $packEur = Pack::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pack Euro',
            'slug' => 'pack-euro',
            'price' => 300,
            'currency' => 'eur',
            'video_quantity' => 3,
            'status' => 'active',
        ]);

        $packBrl = Pack::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pack Brasil',
            'slug' => 'pack-brasil',
            'price' => 1500,
            'currency' => 'brl',
            'video_quantity' => 3,
            'status' => 'active',
        ]);

        config(['services.stripe.secret' => 'sk_test_fake_123']);
        $this->tenant->update(['stripe_account_id' => 'acct_fake_123', 'charges_enabled' => true]);

        $methodsEur = Setting::getActivePaymentMethods($this->tenant, $packEur);
        $this->assertTrue($methodsEur['bizum']);
        $this->assertTrue($methodsEur['redsys_card']);

        $methodsBrl = Setting::getActivePaymentMethods($this->tenant, $packBrl);
        $this->assertFalse($methodsBrl['bizum']);
        $this->assertFalse($methodsBrl['redsys_card']);
        $this->assertTrue($methodsBrl['stripe']);
    }

    public function test_storefront_checkout_rejects_bizum_for_brl_pack(): void
    {
        $packBrl = Pack::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pack Brasil',
            'slug' => 'pack-brasil',
            'price' => 1500,
            'currency' => 'brl',
            'video_quantity' => 3,
            'status' => 'active',
        ]);

        $response = $this->post(route('storefront.checkout', [$this->tenant->slug, $packBrl->slug]), [
            'payment_method' => 'bizum',
            'name' => 'Cliente BR',
            'email' => 'cliente@brasil.test',
            'password' => 'secret12345',
            'billing_type' => 'particular',
            'billing_country' => 'BR',
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('não está disponível para este pacote', session('error'));
    }

    public function test_portal_checkout_rejects_redsys_card_for_brl_pack(): void
    {
        $cliente = Cliente::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente Portal',
            'email' => 'portal@cliente.test',
            'password' => 'secret12345',
        ]);

        $packBrl = Pack::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pack Brasil',
            'slug' => 'pack-brasil',
            'price' => 1500,
            'currency' => 'brl',
            'video_quantity' => 3,
            'status' => 'active',
        ]);

        $response = $this->actingAs($cliente, 'cliente')
            ->post(route('portal.packs.checkout', $packBrl), [
                'payment_method' => 'redsys_card',
                'billing_type' => 'particular',
                'billing_country' => 'BR',
            ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('não está disponível para este pacote', session('error'));
    }
}
