<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\Category;
use App\Models\Cliente;
use App\Models\Pack;
use App\Models\PackPurchase;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddonRelationalArchitectureTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'NUME Zaragoza',
            'slug' => 'nume',
            'status' => 'active',
            'stripe_account_id' => 'acct_test_123',
            'charges_enabled' => true,
            'subscription_expires_at' => now()->addYear(),
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin Zaragoza',
            'email' => 'admin@nume.es',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);
    }

    public function test_staff_can_view_addons_index(): void
    {
        Addon::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pack 10 Miniaturas',
            'slug' => 'pack-10-miniaturas',
            'default_price' => 350.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)->get(route('addons.index'));

        $response->assertStatus(200);
        $response->assertSee('Pack 10 Miniaturas');
        $response->assertSee('350');
    }

    public function test_staff_can_create_and_toggle_addon(): void
    {
        $response = $this->actingAs($this->user)->post(route('addons.store'), [
            'name' => 'Subtítulos Multi-idioma',
            'category_scope' => 'social_media',
            'default_price' => 120.00,
            'description' => 'Traducción y sincronización de subtítulos en ES y EN.',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('addons.index'));
        $response->assertSessionHas('success');

        $addon = Addon::where('slug', 'subtitulos-multi-idioma')->first();
        $this->assertNotNull($addon);
        $this->assertEquals(120.00, (float) $addon->default_price);
        $this->assertTrue((bool) $addon->is_active);

        // Toggle status
        $toggleResponse = $this->actingAs($this->user)->post(route('addons.toggle', $addon));
        $toggleResponse->assertRedirect();
        $addon->refresh();
        $this->assertFalse((bool) $addon->is_active);
    }

    public function test_staff_can_link_addons_to_pack_with_custom_prices(): void
    {
        $category = Category::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Redes Sociais',
            'slug' => 'redes-sociais',
            'type' => 'pack',
            'is_active' => true,
        ]);

        $addon1 = Addon::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Miniatura Custom',
            'slug' => 'miniatura-custom',
            'default_price' => 50.00,
            'is_active' => true,
        ]);

        $addon2 = Addon::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Locução Pro',
            'slug' => 'locucao-pro',
            'default_price' => 100.00,
            'is_active' => true,
        ]);

        $pack = Pack::create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $category->id,
            'name' => 'Starter Pack',
            'slug' => 'starter-pack',
            'price' => 500.00,
            'video_quantity' => 4,
            'status' => 'active',
        ]);

        // Simula atualização do Pack com addons selecionados e preço customizado
        $response = $this->actingAs($this->user)->put(route('packs.update', $pack), [
            'category_id' => $category->id,
            'name' => 'Starter Pack Pro',
            'price' => 550.00,
            'video_quantity' => 4,
            'status' => 'active',
            'addon_ids' => [$addon1->id, $addon2->id],
            'addon_prices' => [
                $addon1->id => 40.00, // Preço customizado menor para este pack
                $addon2->id => 100.00,
            ],
        ]);

        $response->assertRedirect(route('packs.index'));

        $pack->refresh();
        $this->assertCount(2, $pack->addons);

        $effective = $pack->effective_addons;
        $this->assertCount(2, $effective);

        $effectiveMiniatura = collect($effective)->firstWhere('id', $addon1->id);
        $this->assertEquals(40.00, $effectiveMiniatura['price']);

        // Verifica que a tela de edição do pacote abre com sucesso sem erro em keyBy()
        $editResponse = $this->actingAs($this->user)->get(route('packs.edit', $pack));
        $editResponse->assertStatus(200);
        $editResponse->assertSee('Starter Pack Pro');
        $editResponse->assertSee('Miniatura Custom');
    }

    public function test_storefront_calculates_total_with_selected_addons_on_checkout(): void
    {
        config(['services.stripe.secret' => 'sk_test_fake_key_for_testing']);
        config(['services.stripe.platform_fee_percent' => 10]);

        $category = Category::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Vídeos',
            'slug' => 'videos',
            'type' => 'pack',
            'is_active' => true,
        ]);

        $addon = Addon::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pack 10 Miniaturas',
            'slug' => 'pack-10-miniaturas',
            'default_price' => 350.00,
            'is_active' => true,
        ]);

        $pack = Pack::create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $category->id,
            'name' => 'Pack Reels Starter',
            'slug' => 'pack-reels-starter',
            'price' => 950.00,
            'video_quantity' => 6,
            'currency' => 'eur',
            'status' => 'active',
        ]);

        $pack->addons()->attach($addon->id, ['custom_price' => 350.00]);

        $cliente = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Maria López',
            'email' => 'maria@empresa.es',
            'password' => bcrypt('password123'),
            'status' => 'active',
        ]);

        $this->actingAs($cliente, 'cliente');

        // Como o Stripe secret é fake, a chamada para $stripe->checkout->sessions->create disparará exceção,
        // mas a criação de PackPurchase com o valor total recalculado ocorre ANTES da chamada do Stripe!
        $response = $this->post(route('storefront.checkout', [
            'tenant' => $this->tenant->slug,
            'pack' => $pack->slug,
        ]), [
            'email' => 'maria@empresa.es',
            'password' => 'password123',
            'selected_addons' => ['Pack 10 Miniaturas'],
        ]);

        // Verifica que o PackPurchase foi criado com o subtotal base (950 + 350 = 1300.00) e total com IVA 21% (1573.00)
        $purchase = PackPurchase::where('cliente_id', $cliente->id)
            ->where('pack_id', $pack->id)
            ->first();

        $this->assertNotNull($purchase);
        $this->assertEquals(1300.00, (float) $purchase->subtotal);
        $this->assertEquals(273.00, (float) $purchase->tax_amount);
        $this->assertEquals(1573.00, (float) $purchase->amount);
        $this->assertEquals(130.00, (float) $purchase->application_fee_amount);
        $this->assertContains('Pack 10 Miniaturas', $purchase->addons_purchased);
    }
}
