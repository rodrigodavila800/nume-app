<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\Pack;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontSocialAddonTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Pack $pack;
    private Addon $addon;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'NUME Zaragoza',
            'slug' => 'nume-zaragoza',
            'status' => 'active',
            'subscription_expires_at' => now()->addYears(5),
        ]);

        $this->pack = Pack::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pack Audiovisual Pro',
            'slug' => 'pack-audiovisual-pro',
            'price' => 750.00,
            'video_quantity' => 10,
            'status' => 'active',
        ]);

        $this->addon = Addon::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Distribuição Automática Multi-Rede (Reels + TikTok + Shorts)',
            'slug' => 'distribuicao-multi-rede',
            'default_price' => 49.00,
            'currency' => 'EUR',
            'is_active' => true,
        ]);

        $this->pack->addons()->attach($this->addon->id, [
            'custom_price' => 49.00,
            'is_default_selected' => false,
            'sort_order' => 1,
        ]);
    }

    public function test_social_distribution_addon_is_visible_on_storefront_pack(): void
    {
        $response = $this->get(route('storefront.show', $this->tenant->slug));

        $response->assertOk();
        $response->assertSee('Pack Audiovisual Pro');
        $response->assertSee('Distribuição Automática Multi-Rede');
        $response->assertSee('49');
    }

    public function test_pack_effective_addons_includes_social_distribution(): void
    {
        $effective = $this->pack->effective_addons;

        $this->assertNotEmpty($effective);
        $socialAddon = collect($effective)->firstWhere('slug', 'distribuicao-multi-rede');

        $this->assertNotNull($socialAddon);
        $this->assertEquals(49.00, $socialAddon['price']);
    }
}
