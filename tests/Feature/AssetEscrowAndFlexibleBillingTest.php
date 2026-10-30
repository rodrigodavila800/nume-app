<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Pack;
use App\Models\PackPurchase;
use App\Models\Production;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetEscrowAndFlexibleBillingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Cliente $cliente;
    private User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'NUME Creative Escrow Lab',
            'slug' => 'nume-escrow',
            'status' => 'active',
            'social_whatsapp' => '+34 976 111 222',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $this->cliente = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Marcos Silva',
            'email' => 'marcos@empresa.com',
            'password' => bcrypt('password123'),
            'brand_niche' => 'Odontologia Avançada',
            'website' => 'https://odontomarcos.com',
        ]);

        $this->adminUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
        ]);
    }

    public function test_pack_can_be_configured_with_split_50_50_and_pilot_discount(): void
    {
        $pack = Pack::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pack Estratégico 50/50',
            'slug' => 'pack-estrategico-50-50',
            'price' => 1000.00,
            'currency' => 'eur',
            'billing_type' => 'one_time',
            'billing_mode' => 'split_50_50',
            'deposit_percentage' => 50,
            'pilot_discount_on_subscription' => 150.00,
            'video_quantity' => 4,
            'status' => 'active',
        ]);

        $this->assertTrue($pack->isSplit5050());
        $this->assertTrue($pack->isPilotEntry());
        $this->assertEquals(500.00, $pack->calculateDepositAmount());
        $this->assertEquals(500.00, $pack->calculateBalanceAmount());
    }

    public function test_storefront_checkout_50_50_calculates_deposit_and_creates_purchase_with_escrow(): void
    {
        \App\Models\Setting::set('fiscal_regime', 'empresa_sl', 'fiscal');

        $pack = Pack::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pacote Produção 50/50 Vitrine',
            'slug' => 'pacote-producao-50-50-vitrine',
            'price' => 1000.00,
            'currency' => 'eur',
            'billing_type' => 'one_time',
            'billing_mode' => 'split_50_50',
            'deposit_percentage' => 50,
            'video_quantity' => 2,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->cliente, 'cliente')
            ->post(route('storefront.checkout', [
                'tenant' => $this->tenant->slug,
                'pack' => $pack->slug,
            ]), [
                'payment_method' => 'bizum',
                'billing_type' => 'empresa_autonomo',
                'billing_tax_id' => 'B50123456',
                'billing_legal_name' => 'Marcos Odonto S.L.',
                'billing_postal_code' => '50001',
                'billing_city' => 'Zaragoza',
                'billing_country' => 'ES',
            ]);

        $response->assertStatus(200);

        $purchase = PackPurchase::where('pack_id', $pack->id)
            ->where('cliente_id', $this->cliente->id)
            ->first();

        $this->assertNotNull($purchase);
        $this->assertEquals('split_50_50', $purchase->billing_mode);
        $this->assertEquals('pending', $purchase->balance_status);

        // Preço base 1000 + 21% IVA = 1210 total
        // Depósito 50% = 605, Saldo 50% = 605
        $this->assertEquals(1210.00, $purchase->total_amount);
        $this->assertEquals(605.00, $purchase->deposit_amount);
        $this->assertEquals(605.00, $purchase->balance_amount);
        $this->assertEquals(605.00, $purchase->amount); // Valor cobrado no ato do checkout
    }

    public function test_video_asset_is_locked_when_purchase_has_pending_balance(): void
    {
        $pack = Pack::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Vídeo Piloto',
            'slug' => 'video-piloto',
            'price' => 600.00,
            'currency' => 'eur',
            'billing_type' => 'one_time',
            'billing_mode' => 'split_50_50',
            'deposit_percentage' => 50,
            'video_quantity' => 1,
            'status' => 'active',
        ]);

        $purchase = PackPurchase::create([
            'tenant_id' => $this->tenant->id,
            'pack_id' => $pack->id,
            'cliente_id' => $this->cliente->id,
            'amount' => 600.00,
            'billing_mode' => 'split_50_50',
            'deposit_amount' => 300.00,
            'deposit_paid_at' => now(),
            'balance_amount' => 300.00,
            'balance_status' => 'pending',
            'payment_gateway' => 'stripe',
            'status' => 'paid',
            'video_quantity' => 1,
            'remaining_videos' => 1,
        ]);

        $production = Production::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'created_by' => $this->adminUser->id,
            'pack_purchase_id' => $purchase->id,
            'title' => 'Vídeo Institucional 4K Piloto',
            'status' => 'in_production',
        ]);

        $video = Video::create([
            'tenant_id' => $this->tenant->id,
            'production_id' => $production->id,
            'title' => 'Corte Final - Campanha Zaragoza',
            'video_url' => 'https://vimeo.com/999888777',
            'status' => 'processing',
        ]);

        $this->assertTrue($purchase->isAssetLocked());
        $this->assertTrue($video->isAssetLocked());

        // Acessa portal como o cliente autenticado
        $response = $this->actingAs($this->cliente, 'cliente')
            ->get(route('portal.productions.show', $production->uuid));

        $response->assertOk();
        $response->assertSee('Custódia Ativa');
        // Entrega em duas opções: com saldo pendente, só o convite para aprovar, nunca o download.
        $response->assertSee('Aprovar Corte para Liberar Download');
        $response->assertDontSee('Baixar Master (.mp4)');
        $response->assertSee('Aprovar Corte &amp; Liberar', false);
        $response->assertSee('300,00');
    }

    public function test_client_approves_video_and_releases_escrow_by_paying_balance(): void
    {
        $pack = Pack::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Vídeo Piloto Escrow',
            'slug' => 'video-piloto-escrow',
            'price' => 800.00,
            'currency' => 'eur',
            'billing_type' => 'one_time',
            'billing_mode' => 'split_50_50',
            'deposit_percentage' => 50,
            'video_quantity' => 1,
            'status' => 'active',
        ]);

        $purchase = PackPurchase::create([
            'tenant_id' => $this->tenant->id,
            'pack_id' => $pack->id,
            'cliente_id' => $this->cliente->id,
            'amount' => 800.00,
            'billing_mode' => 'split_50_50',
            'deposit_amount' => 400.00,
            'deposit_paid_at' => now(),
            'balance_amount' => 400.00,
            'balance_status' => 'pending',
            'payment_gateway' => 'stripe',
            'status' => 'paid',
            'video_quantity' => 1,
            'remaining_videos' => 1,
        ]);

        $production = Production::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'created_by' => $this->adminUser->id,
            'pack_purchase_id' => $purchase->id,
            'title' => 'Vídeo Entrega Final',
            'status' => 'in_production',
        ]);

        $video = Video::create([
            'tenant_id' => $this->tenant->id,
            'production_id' => $production->id,
            'title' => 'Corte 1 - Alta Qualidade',
            'video_url' => 'https://vimeo.com/123456789',
            'status' => 'processing',
        ]);

        $this->assertTrue($video->isAssetLocked());

        // Cliente clica em aprovar e liquidar
        $response = $this->actingAs($this->cliente, 'cliente')
            ->post(route('portal.videos.approve-and-release', $video));

        $response->assertSessionHas('success');

        $purchase->refresh();
        $video->refresh();

        $this->assertEquals('paid', $purchase->balance_status);
        $this->assertNotNull($purchase->balance_paid_at);
        $this->assertFalse($video->isAssetLocked());
        $this->assertEquals('ready', $video->status);
    }

    public function test_idor_protection_prevents_unauthorized_client_from_approving_video(): void
    {
        $outroCliente = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente Invasor',
            'email' => 'invasor@hack.es',
            'password' => bcrypt('password123'),
        ]);

        $pack = Pack::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pack Protegido',
            'slug' => 'pack-protegido',
            'price' => 500.00,
            'currency' => 'eur',
            'billing_type' => 'one_time',
            'billing_mode' => 'split_50_50',
            'video_quantity' => 1,
            'status' => 'active',
        ]);

        $purchase = PackPurchase::create([
            'tenant_id' => $this->tenant->id,
            'pack_id' => $pack->id,
            'cliente_id' => $this->cliente->id,
            'amount' => 500.00,
            'billing_mode' => 'split_50_50',
            'deposit_amount' => 250.00,
            'deposit_paid_at' => now(),
            'balance_amount' => 250.00,
            'balance_status' => 'pending',
            'payment_gateway' => 'stripe',
            'status' => 'paid',
            'video_quantity' => 1,
            'remaining_videos' => 1,
        ]);

        $production = Production::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'created_by' => $this->adminUser->id,
            'pack_purchase_id' => $purchase->id,
            'title' => 'Produção Privada Marcos',
            'status' => 'in_production',
        ]);

        $video = Video::create([
            'tenant_id' => $this->tenant->id,
            'production_id' => $production->id,
            'title' => 'Vídeo Privado',
            'video_url' => 'https://vimeo.com/11223344',
            'status' => 'processing',
        ]);

        // Tentativa de aprovação por outro cliente autenticado deve ser barrada com 403
        $response = $this->actingAs($outroCliente, 'cliente')
            ->post(route('portal.videos.approve-and-release', $video));

        $response->assertForbidden();

        $this->assertEquals('pending', $purchase->fresh()->balance_status);
        $this->assertTrue($video->fresh()->isAssetLocked());
    }

    public function test_pack_admin_crud_supports_split_50_50_and_pilot_fields(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->post(route('packs.store'), [
                'name' => 'Pacote Piloto de Alta Conversão',
                'description' => 'Pacote de entrada com 50% de sinal e desconto na assinatura.',
                'price' => 750.00,
                'currency' => 'eur',
                'billing_type' => 'one_time',
                'billing_mode' => 'split_50_50',
                'deposit_percentage' => 50,
                'pilot_discount_on_subscription' => 100.00,
                'video_quantity' => 2,
                'status' => 'active',
            ]);

        $response->assertRedirect(route('packs.index'));

        $this->assertDatabaseHas('packs', [
            'name' => 'Pacote Piloto de Alta Conversão',
            'billing_mode' => 'split_50_50',
            'deposit_percentage' => 50,
            'pilot_discount_on_subscription' => 100.00,
        ]);
    }
}
