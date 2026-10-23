<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Pack;
use App\Models\PackPurchase;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\CategoryAndPacksSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackManualAddonsAndSofiTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_generate_pack_content_with_sofi_ia(): void
    {
        $tenant = Tenant::create([
            'name' => 'NUME Zaragoza',
            'slug' => 'nume',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);

        $category = Category::create([
            'tenant_id' => $tenant->id,
            'name' => 'Campaña de Anuncios (Ads)',
            'slug' => 'ads',
            'type' => 'pack',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->postJson(route('packs.sofi_generate'), [
            'category_id' => $category->id,
            'name' => 'Pack Ads Escala',
            'price' => 700.00,
            'video_quantity' => 3,
            'language' => 'es',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'description',
                'features',
                'addons',
                'vat_rate',
                'currency',
            ],
        ]);

        $data = $response->json('data');
        $this->assertNotEmpty($data['description']);
        $this->assertIsArray($data['features']);
        $this->assertIsArray($data['addons']);
        $this->assertEquals(21.00, $data['vat_rate']);
    }

    public function test_staff_can_create_pack_with_manual_addons_and_features(): void
    {
        $tenant = Tenant::create([
            'name' => 'NUME Zaragoza',
            'slug' => 'nume',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'admin',
        ]);

        $category = Category::create([
            'tenant_id' => $tenant->id,
            'name' => 'Posicionamiento de Marca',
            'slug' => 'posicionamiento',
            'type' => 'pack',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->post(route('packs.store'), [
            'category_id' => $category->id,
            'name' => 'Pack Personalizado VIP',
            'description' => 'Producción de alta gama com roteiros calibrados.',
            'video_quantity' => 6,
            'price' => 950.00,
            'currency' => 'eur',
            'vat_rate' => 21.00,
            'status' => 'active',
            'features' => [
                '6 vídeos em 4K',
                'Roteirização com gancho 0-3s',
                'Suporte no Portal',
                '', // Deve ser filtrado
            ],
            'addons' => [
                ['name' => 'Composição com IA', 'price' => 100],
                ['name' => 'Tráfego Pago Meta Ads', 'price' => 200],
                ['name' => '', 'price' => 50], // Deve ser filtrado
            ],
        ]);

        $response->assertRedirect(route('packs.index'));

        $this->assertDatabaseHas('packs', [
            'tenant_id' => $tenant->id,
            'name' => 'Pack Personalizado VIP',
            'price' => 950.00,
            'vat_rate' => 21.00,
            'currency' => 'eur',
        ]);

        $pack = Pack::where('name', 'Pack Personalizado VIP')->first();
        $this->assertCount(3, $pack->features);
        $this->assertCount(2, $pack->addons);
        $this->assertEquals('Composição com IA', $pack->addons[0]['name']);
        $this->assertEquals(100, $pack->addons[0]['price']);
    }

    public function test_database_contains_only_official_packs_from_pacotes_txt_after_seeding(): void
    {
        $tenant = Tenant::create([
            'name' => 'NUME Zaragoza',
            'slug' => 'nume',
            'status' => 'active',
        ]);

        // Cria pacote dummy antigo
        Pack::create([
            'tenant_id' => $tenant->id,
            'name' => 'Dummy Old Pack',
            'slug' => 'dummy-old-pack',
            'price' => 100.00,
            'video_quantity' => 1,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('packs', ['slug' => 'dummy-old-pack']);

        $this->seed(CategoryAndPacksSeeder::class);

        // O dummy pack deve ter sido deletado
        $this->assertDatabaseMissing('packs', ['slug' => 'dummy-old-pack']);

        // Deve conter exatamente os 8 pacotes oficiais
        $officialSlugs = [
            'pack-posicionamiento-basico',
            'pack-posicionamiento-pro',
            'pack-posicionamiento-full',
            'pack-ads-campana',
            'video-institucional',
            'pack-podcast',
            'evento-corporativo-pack-1',
            'evento-corporativo-pack-2',
        ];

        $tenantPacks = Pack::where('tenant_id', $tenant->id)->get();
        $this->assertCount(8, $tenantPacks);
        $this->assertEqualsCanonicalizing($officialSlugs, $tenantPacks->pluck('slug')->toArray());
    }

    public function test_pack_can_link_showcase_video_and_subscription_contract(): void
    {
        $tenant = Tenant::create([
            'name' => 'NUME Zaragoza',
            'slug' => 'nume',
            'status' => 'active',
        ]);

        $video = \App\Models\ShowcaseVideo::create([
            'tenant_id' => $tenant->id,
            'title' => 'Vídeo Institucional Cirurgia 4K',
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'is_vertical' => false,
            'is_published' => true,
        ]);

        $pack = Pack::create([
            'tenant_id' => $tenant->id,
            'showcase_video_id' => $video->id,
            'name' => 'Retainer Trimestral 8 Vídeos',
            'price' => 1200.00,
            'video_quantity' => 8,
            'billing_type' => 'subscription',
            'billing_interval' => 'quarter',
            'commitment_months' => 3,
            'status' => 'active',
        ]);

        $this->assertNotNull($pack->showcaseVideo);
        $this->assertEquals($video->id, $pack->showcaseVideo->id);
        $this->assertEquals('subscription', $pack->billing_type);
        $this->assertEquals('quarter', $pack->billing_interval);
        $this->assertEquals(3, $pack->commitment_months);
    }

    public function test_portal_packs_renders_selectable_addons_and_showcase_video_button(): void
    {
        $tenant = Tenant::create([
            'name' => 'NUME Zaragoza',
            'slug' => 'nume',
            'status' => 'active',
        ]);

        $video = \App\Models\ShowcaseVideo::create([
            'tenant_id' => $tenant->id,
            'title' => 'Demonstração de Retenção 9:16',
            'video_url' => 'https://www.youtube.com/shorts/demo123',
            'is_vertical' => true,
            'is_published' => true,
        ]);

        $pack = Pack::create([
            'tenant_id' => $tenant->id,
            'showcase_video_id' => $video->id,
            'name' => 'Pack Pro Retenção',
            'price' => 900.00,
            'video_quantity' => 4,
            'addons' => [
                ['name' => 'Locução Comercial Pro', 'price' => 80],
                ['name' => 'Roteiro Adicional Sofi', 'price' => 120],
            ],
            'status' => 'active',
        ]);

        $cliente = \App\Models\Cliente::create([
            'tenant_id' => $tenant->id,
            'name' => 'Alejandro Martínez',
            'email' => 'alejandro@empresa.es',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->actingAs($cliente, 'cliente')
            ->get(route('portal.packs.index'));

        $response->assertStatus(200);
        $response->assertSee('Pack Pro Retenção');
        $response->assertSee('Ver Padrão de Entrega (4K)');
        $response->assertSee('Locução Comercial Pro');
        $response->assertSee('Roteiro Adicional Sofi');
        $response->assertSee('portal-addon-checkbox');
    }

    public function test_storefront_guest_checkout_creates_client_inline_with_addons(): void
    {
        \App\Models\Setting::set('redsys_enabled', '1');

        $tenant = Tenant::create([
            'name' => 'NUME Zaragoza',
            'slug' => 'nume',
            'status' => 'active',
        ]);

        $pack = Pack::create([
            'tenant_id' => $tenant->id,
            'name' => 'Pack 4 Vídeos',
            'price' => 800.00,
            'video_quantity' => 4,
            'addons' => [
                ['name' => 'Edição Express 24h', 'price' => 150],
            ],
            'status' => 'active',
        ]);

        $response = $this->post(route('storefront.checkout', [
            'tenant' => $tenant->slug,
            'pack' => $pack->slug,
        ]), [
            'name' => 'Novo Cliente Zaragoza',
            'email' => 'novo@cliente.com',
            'password' => 'secret123',
            'payment_method' => 'bizum',
            'selected_addons' => ['Edição Express 24h'],
            'billing_type' => 'particular',
        ]);

        // Valida que o cliente foi criado e autenticado
        $createdClient = \App\Models\Cliente::where('email', 'novo@cliente.com')->first();
        $this->assertNotNull($createdClient);
        $this->assertEquals('Novo Cliente Zaragoza', $createdClient->name);
        $this->assertAuthenticatedAs($createdClient, 'cliente');

        // Valida que a compra foi registrada com os add-ons somados
        $purchase = \App\Models\PackPurchase::where('cliente_id', $createdClient->id)->first();
        $this->assertNotNull($purchase);
        $this->assertEquals(950.00, (float) $purchase->subtotal); // 800 base + 150 addon
        $this->assertEquals(1149.50, (float) $purchase->total_amount); // 950 * 1.21 IVA
        $this->assertContains('Edição Express 24h', $purchase->addons_purchased);
    }

    public function test_portal_pack_checkout_records_purchase_with_addons_and_iva(): void
    {
        \App\Models\Setting::set('redsys_enabled', '1');

        $tenant = Tenant::create([
            'name' => 'NUME Zaragoza',
            'slug' => 'nume',
            'status' => 'active',
        ]);

        $pack = Pack::create([
            'tenant_id' => $tenant->id,
            'name' => 'Pack Estratégico 8 Vídeos',
            'price' => 1000.00,
            'video_quantity' => 8,
            'addons' => [
                ['name' => 'Roteiro Sofi Viral', 'price' => 100],
                ['name' => 'Thumbnail 3D', 'price' => 50],
            ],
            'status' => 'active',
        ]);

        $cliente = \App\Models\Cliente::create([
            'tenant_id' => $tenant->id,
            'name' => 'Carlos Aragón',
            'email' => 'carlos@aragon.es',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->actingAs($cliente, 'cliente')
            ->post(route('portal.packs.checkout', $pack), [
                'payment_method' => 'bizum',
                'selected_addons' => ['Roteiro Sofi Viral', 'Thumbnail 3D'],
                'billing_type' => 'particular',
            ]);

        $response->assertStatus(200); // Exibe a view de redirecionamento seguro Redsys
        $response->assertSee('redsys_form');

        $purchase = \App\Models\PackPurchase::where('cliente_id', $cliente->id)->first();
        $this->assertNotNull($purchase);
        $this->assertEquals(1150.00, (float) $purchase->subtotal); // 1000 base + 150 addons
        $this->assertEquals(1391.50, (float) $purchase->total_amount); // 1150 * 1.21 IVA
        $this->assertContains('Roteiro Sofi Viral', $purchase->addons_purchased);
        $this->assertContains('Thumbnail 3D', $purchase->addons_purchased);
        $this->assertNotNull($purchase->gateway_order_reference);
    }

    public function test_storefront_existing_client_checkout_validates_password(): void
    {
        \App\Models\Setting::set('redsys_enabled', '1');

        $tenant = Tenant::create([
            'name' => 'NUME Zaragoza',
            'slug' => 'nume',
            'status' => 'active',
        ]);

        $pack = Pack::create([
            'tenant_id' => $tenant->id,
            'name' => 'Pack Retenção',
            'price' => 500.00,
            'video_quantity' => 2,
            'status' => 'active',
        ]);

        $cliente = \App\Models\Cliente::create([
            'tenant_id' => $tenant->id,
            'name' => 'Cliente Existente',
            'email' => 'existente@empresa.es',
            'password' => bcrypt('correta123'),
        ]);

        // Senha incorreta deve falhar
        $failResponse = $this->post(route('storefront.checkout', [
            'tenant' => $tenant->slug,
            'pack' => $pack->slug,
        ]), [
            'email' => 'existente@empresa.es',
            'password' => 'errada123',
            'payment_method' => 'bizum',
        ]);

        $failResponse->assertSessionHas('error');
        $this->assertGuest('cliente');

        // Senha correta deve autenticar e prosseguir
        $successResponse = $this->post(route('storefront.checkout', [
            'tenant' => $tenant->slug,
            'pack' => $pack->slug,
        ]), [
            'email' => 'existente@empresa.es',
            'password' => 'correta123',
            'payment_method' => 'bizum',
        ]);

        $successResponse->assertStatus(200); // Redsys redirect
        $this->assertAuthenticatedAs($cliente, 'cliente');
    }

    public function test_storefront_and_portal_render_payment_method_ux_elements(): void
    {
        \App\Models\Setting::set('stripe_secret', 'sk_test_mock');
        \App\Models\Setting::set('stripe_enabled', '1');
        \App\Models\Setting::set('redsys_enabled', '1');
        \App\Models\Setting::set('redsys_bizum_enabled', '1');
        \App\Models\Setting::set('redsys_card_enabled', '1');

        $tenant = Tenant::create([
            'name' => 'NUME Zaragoza',
            'slug' => 'nume',
            'status' => 'active',
            'stripe_account_id' => 'acct_test_ux_123',
            'charges_enabled' => true,
        ]);

        $pack = Pack::create([
            'tenant_id' => $tenant->id,
            'name' => 'Pack Audiovisual 360',
            'slug' => 'pack-audiovisual-360',
            'price' => 1000.00,
            'video_quantity' => 4,
            'status' => 'active',
            'features' => ['4 Vídeos 4K', 'Roteiro Sofi IA'],
            'addons' => [
                ['name' => 'Composição com IA', 'price' => 150],
            ],
        ]);

        $cliente = \App\Models\Cliente::create([
            'tenant_id' => $tenant->id,
            'name' => 'Cliente Teste UX',
            'email' => 'teste.ux@empresa.com',
            'password' => bcrypt('password123'),
        ]);

        // 1. Vitrine pública oficial (/) deve conter o modal transparente com hints e tabs
        $storefrontResponse = $this->get(route('marketplace.index'));
        $storefrontResponse->assertStatus(200);
        $storefrontResponse->assertSee('storefront-checkout-auth-tabs', false);
        $storefrontResponse->assertSee('storefront-checkout-payment-hint', false);
        $storefrontResponse->assertSee('badge-bizum', false);
        $storefrontResponse->assertSee('badge-card', false);
        $storefrontResponse->assertSee('badge-stripe', false);
        $storefrontResponse->assertSee('storefront-checkout-submit-btn', false);

        // 2. Portal do cliente deve conter o seletor com hint contextual e botão temático
        $portalResponse = $this->actingAs($cliente, 'cliente')->get(route('portal.packs.index'));
        $portalResponse->assertStatus(200);
        $portalResponse->assertSee('portal-payment-hint', false);
        $portalResponse->assertSee('portal-checkout-submit-btn', false);
        $portalResponse->assertSee('payment-method-selector', false);
        $portalResponse->assertSee('payment-method-radio', false);
        $portalResponse->assertSee('value="bizum"', false);
        $portalResponse->assertSee('value="redsys_card"', false);
        $portalResponse->assertSee('value="stripe"', false);
    }

    public function test_inactive_payment_methods_are_hidden_and_rejected(): void
    {
        // Desativa Bizum e Stripe no banco de dados
        \App\Models\Setting::set('redsys_enabled', '1');
        \App\Models\Setting::set('redsys_bizum_enabled', '0');
        \App\Models\Setting::set('redsys_card_enabled', '1');
        \App\Models\Setting::set('stripe_enabled', '0');

        $tenant = Tenant::create([
            'name' => 'Produtora Teste',
            'slug' => 'produtora-teste',
            'status' => 'active',
        ]);

        $pack = Pack::create([
            'tenant_id' => $tenant->id,
            'name' => 'Pack Audiovisual Redsys Only',
            'slug' => 'pack-audiovisual-redsys-only',
            'price' => 500.00,
            'video_quantity' => 2,
            'status' => 'active',
        ]);

        $cliente = \App\Models\Cliente::create([
            'tenant_id' => $tenant->id,
            'name' => 'Cliente Inativo UX',
            'email' => 'cliente.inativo@empresa.com',
            'password' => bcrypt('password123'),
        ]);

        // 1. No portal do cliente, Bizum e Stripe NÃO devem aparecer
        $portalResponse = $this->actingAs($cliente, 'cliente')->get(route('portal.packs.index'));
        $portalResponse->assertStatus(200);
        $portalResponse->assertSee('value="redsys_card"', false);
        $portalResponse->assertDontSee('value="bizum"', false);
        $portalResponse->assertDontSee('value="stripe"', false);

        // 2. Se tentar submeter Bizum pelo portal, deve ser rejeitado
        $portalCheckoutResponse = $this->actingAs($cliente, 'cliente')->post(route('portal.packs.checkout', $pack), [
            'payment_method' => 'bizum',
        ]);
        $portalCheckoutResponse->assertSessionHas('error');

        // 3. Na vitrine pública, Bizum e Stripe também não devem aparecer no modal
        $storefrontResponse = $this->get(route('storefront.show', $tenant->slug));
        $storefrontResponse->assertStatus(200);
        $storefrontResponse->assertSee('data-method="redsys_card"', false);
        $storefrontResponse->assertDontSee('data-method="bizum"', false);
        $storefrontResponse->assertDontSee('data-method="stripe"', false);

        // 4. Se tentar submeter Stripe pela vitrine, deve ser rejeitado
        $storefrontCheckoutResponse = $this->post(route('storefront.checkout', ['tenant' => $tenant->slug, 'pack' => $pack->slug]), [
            'payment_method' => 'stripe',
            'email' => $cliente->email,
            'password' => 'password123',
        ]);
        $storefrontCheckoutResponse->assertSessionHas('error');
    }

    public function test_storefront_and_portal_checkout_recurring_subscription_pack(): void
    {
        \App\Models\Setting::set('redsys_enabled', '1');

        $tenant = Tenant::create([
            'name' => 'NUME Zaragoza',
            'slug' => 'nume',
            'status' => 'active',
        ]);

        $subPack = Pack::create([
            'tenant_id' => $tenant->id,
            'name' => 'Plano Trimestral Estratégico',
            'slug' => 'plano-trimestral-estrategico',
            'price' => 800.00,
            'video_quantity' => 4,
            'status' => 'active',
            'billing_type' => 'subscription',
            'billing_interval' => 'month',
            'commitment_months' => 3,
            'features' => ['4 Vídeos por Mês', 'Roteiro Sofi IA'],
        ]);

        // 1. Vitrine pública exibe badges e notas de contrato de 3 meses
        $storefront = $this->get(route('marketplace.index'));
        $storefront->assertStatus(200);
        $storefront->assertSee('Contrato · 3 Meses', false);
        $storefront->assertSee('pack-subscription-badge', false);
        $storefront->assertSee('/ mês', false);

        // 2. Checkout na vitrine grava assinatura com 3 ciclos e datas de renovação
        $response = $this->post(route('storefront.checkout', [
            'tenant' => $tenant->slug,
            'pack' => $subPack->slug,
        ]), [
            'name' => 'Cliente Contrato 3M',
            'email' => 'contrato3m@empresa.com',
            'password' => 'secret123',
            'payment_method' => 'bizum',
        ]);

        $response->assertStatus(200); // Redsys redirect view

        $this->assertDatabaseHas('pack_purchases', [
            'tenant_id' => $tenant->id,
            'pack_id' => $subPack->id,
            'subscription_status' => 'active',
            'subscription_interval' => 'month',
            'commitment_months' => 3,
            'subscription_cycle_current' => 1,
            'subscription_cycles_total' => 3,
            'video_quantity' => 4,
        ]);

        $purchase = PackPurchase::where('pack_id', $subPack->id)->first();
        $this->assertTrue($purchase->isSubscription());
        $this->assertNotNull($purchase->subscription_renews_at);
        $this->assertNotNull($purchase->subscription_ends_at);
    }

    public function test_subscription_cycle_renewal_and_completion(): void
    {
        $tenant = Tenant::create([
            'name' => 'NUME Zaragoza',
            'slug' => 'nume',
            'status' => 'active',
        ]);

        $subPack = Pack::create([
            'tenant_id' => $tenant->id,
            'name' => 'Plano Trimestral',
            'slug' => 'plano-trimestral',
            'price' => 750.00,
            'video_quantity' => 4,
            'status' => 'active',
            'billing_type' => 'subscription',
            'billing_interval' => 'month',
            'commitment_months' => 3,
        ]);

        $cliente = \App\Models\Cliente::create([
            'tenant_id' => $tenant->id,
            'name' => 'Cliente Ciclos',
            'email' => 'ciclos@empresa.com',
            'password' => bcrypt('password123'),
        ]);

        $purchase = PackPurchase::create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'pack_id' => $subPack->id,
            'payment_gateway' => 'stripe',
            'amount' => 907.50,
            'subtotal' => 750.00,
            'tax_rate' => 21.00,
            'tax_amount' => 157.50,
            'total_amount' => 907.50,
            'video_quantity' => 4,
            'status' => 'paid',
            'subscription_status' => 'active',
            'subscription_interval' => 'month',
            'commitment_months' => 3,
            'subscription_cycle_current' => 1,
            'subscription_cycles_total' => 3,
            'subscription_renews_at' => now()->addMonth(),
            'subscription_ends_at' => now()->addMonths(3),
        ]);

        // Ciclo 1 -> 2 (adiciona 4 vídeos cota nova)
        $purchase->renewSubscriptionCycle();
        $purchase->refresh();
        $this->assertEquals(2, $purchase->subscription_cycle_current);
        $this->assertEquals('active', $purchase->subscription_status);
        $this->assertEquals(8, $purchase->video_quantity);

        // Ciclo 2 -> 3 (adiciona mais 4 vídeos cota nova)
        $purchase->renewSubscriptionCycle();
        $purchase->refresh();
        $this->assertEquals(3, $purchase->subscription_cycle_current);
        $this->assertEquals('active', $purchase->subscription_status);
        $this->assertEquals(12, $purchase->video_quantity);

        // Ciclo 3 -> 4 (atingiu e ultrapassou os 3 meses de compromisso -> marca completed)
        $purchase->renewSubscriptionCycle();
        $purchase->refresh();
        $this->assertEquals(3, $purchase->subscription_cycle_current);
        $this->assertEquals('completed', $purchase->subscription_status);
        $this->assertNull($purchase->subscription_renews_at);
    }

    public function test_portal_dashboard_renders_active_subscription_contract_cards(): void
    {
        $tenant = Tenant::create([
            'name' => 'NUME Zaragoza',
            'slug' => 'nume',
            'status' => 'active',
        ]);

        $subPack = Pack::create([
            'tenant_id' => $tenant->id,
            'name' => 'Contrato Trimestral Premium',
            'slug' => 'contrato-trimestral-premium',
            'price' => 1200.00,
            'video_quantity' => 6,
            'status' => 'active',
            'billing_type' => 'subscription',
            'billing_interval' => 'month',
            'commitment_months' => 3,
        ]);

        $cliente = \App\Models\Cliente::create([
            'tenant_id' => $tenant->id,
            'name' => 'Cliente VIP Contrato',
            'email' => 'vip.contrato@empresa.es',
            'password' => bcrypt('password123'),
        ]);

        PackPurchase::create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'pack_id' => $subPack->id,
            'payment_gateway' => 'redsys_card',
            'amount' => 1452.00,
            'subtotal' => 1200.00,
            'tax_rate' => 21.00,
            'tax_amount' => 252.00,
            'total_amount' => 1452.00,
            'video_quantity' => 6,
            'videos_used' => 2,
            'status' => 'paid',
            'subscription_status' => 'active',
            'subscription_interval' => 'month',
            'commitment_months' => 3,
            'subscription_cycle_current' => 1,
            'subscription_cycles_total' => 3,
            'subscription_renews_at' => now()->addMonth(),
            'subscription_ends_at' => now()->addMonths(3),
        ]);

        $response = $this->actingAs($cliente, 'cliente')->get(route('portal.dashboard'));
        $response->assertStatus(200);

        // Verifica a presença do card executivo e badges do contrato
        $response->assertSee('Contratos Recorrentes');
        $response->assertSee('Contrato Ativo');
        $response->assertSee('Ciclo 1 de 3');
        $response->assertSee('4 disponíveis'); // 6 total - 2 usados = 4 disponíveis
        $response->assertSee('Próxima Renovação');
        $response->assertSee('Vigência do Contrato');
        $response->assertSee('Contrato Trimestral Premium');
    }

    public function test_checkout_applies_irpf_deduction_for_b2b_when_producer_is_autonomo(): void
    {
        // 1. Configura a produtora como Autônomo com 15% de IRPF
        \App\Models\Setting::set('fiscal_regime', 'autonomo');
        \App\Models\Setting::set('fiscal_irpf_rate', 15.00);
        \App\Models\Setting::set('tax_default_iva_rate', 21.00);
        \App\Models\Setting::set('redsys_enabled', '1');
        \App\Models\Setting::set('redsys_bizum_enabled', '1');

        $tenant = Tenant::create([
            'name' => 'Produtora Autonoma',
            'slug' => 'produtora-autonoma',
            'status' => 'active',
        ]);

        $pack = Pack::create([
            'tenant_id' => $tenant->id,
            'name' => 'Pack Fiscal B2B',
            'slug' => 'pack-fiscal-b2b',
            'price' => 1000.00,
            'video_quantity' => 4,
            'status' => 'active',
        ]);

        $clienteEmpresa = \App\Models\Cliente::create([
            'tenant_id' => $tenant->id,
            'name' => 'Cliente Empresa S.L.',
            'company_name' => 'Empresa Contratante S.L.',
            'email' => 'empresa@b2b.es',
            'password' => bcrypt('password123'),
        ]);

        // A) Compra como Empresa B2B (Espanha) -> Aplica retenção IRPF 15%
        // Base = 1000, IVA 21% = 210, IRPF 15% = 150 -> Total = 1060
        $responseB2B = $this->actingAs($clienteEmpresa, 'cliente')->post(route('portal.packs.checkout', $pack), [
            'payment_method' => 'bizum',
            'billing_type' => 'empresa_autonomo',
            'billing_tax_id' => 'B99887766',
            'billing_legal_name' => 'Empresa Contratante S.L.',
            'billing_country' => 'ES',
        ]);

        $purchaseB2B = PackPurchase::where('cliente_id', $clienteEmpresa->id)->latest()->first();
        $this->assertNotNull($purchaseB2B);
        $this->assertEquals(1000.00, (float) $purchaseB2B->subtotal);
        $this->assertEquals(210.00, (float) $purchaseB2B->tax_amount);
        $this->assertEquals(15.00, (float) $purchaseB2B->irpf_rate);
        $this->assertEquals(150.00, (float) $purchaseB2B->irpf_amount);
        $this->assertEquals(1060.00, (float) $purchaseB2B->total_amount);

        // B) Compra como Particular -> NÃO aplica IRPF
        // Base = 1000, IVA 21% = 210, IRPF 0% -> Total = 1210
        $clienteParticular = \App\Models\Cliente::create([
            'tenant_id' => $tenant->id,
            'name' => 'Cliente Particular',
            'email' => 'particular@gmail.com',
            'password' => bcrypt('password123'),
        ]);

        $responseB2C = $this->actingAs($clienteParticular, 'cliente')->post(route('portal.packs.checkout', $pack), [
            'payment_method' => 'bizum',
            'billing_type' => 'particular',
            'billing_country' => 'ES',
        ]);

        $purchaseB2C = PackPurchase::where('cliente_id', $clienteParticular->id)->latest()->first();
        $this->assertNotNull($purchaseB2C);
        $this->assertEquals(1000.00, (float) $purchaseB2C->subtotal);
        $this->assertEquals(210.00, (float) $purchaseB2C->tax_amount);
        $this->assertEquals(0.00, (float) $purchaseB2C->irpf_rate);
        $this->assertEquals(0.00, (float) $purchaseB2C->irpf_amount);
        $this->assertEquals(1210.00, (float) $purchaseB2C->total_amount);
    }

    public function test_portal_pack_checkout_with_stripe_returns_embedded_json(): void
    {
        \App\Models\Setting::set('stripe_secret', 'sk_test_mock_secret');
        \App\Models\Setting::set('stripe_key', 'pk_test_mock_key');
        \App\Models\Setting::set('stripe_enabled', '1');

        $tenant = Tenant::create([
            'name' => 'NUME Zaragoza',
            'slug' => 'nume-stripe',
            'status' => 'active',
            'stripe_account_id' => 'acct_test_embedded_123',
            'charges_enabled' => true,
        ]);

        $pack = Pack::create([
            'tenant_id' => $tenant->id,
            'name' => 'Pack Teste Stripe',
            'price' => 500.00,
            'video_quantity' => 2,
            'status' => 'active',
        ]);

        $cliente = \App\Models\Cliente::create([
            'tenant_id' => $tenant->id,
            'name' => 'Cliente Stripe Teste',
            'email' => 'stripe.teste@empresa.es',
            'password' => bcrypt('password123'),
        ]);

        // Quando a chave secreta estiver ausente ou inválida, retorna JSON 422 amigável
        \App\Models\Setting::set('stripe_secret', '');

        $response = $this->actingAs($cliente, 'cliente')
            ->postJson(route('portal.packs.checkout', $pack), [
                'payment_method' => 'stripe',
                'billing_type' => 'particular',
                'embedded' => '1',
            ]);

        $response->assertStatus(422);
        $response->assertJson([
            'status' => 'error',
        ]);
    }
}
