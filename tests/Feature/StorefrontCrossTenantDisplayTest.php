<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\Category;
use App\Models\Pack;
use App\Models\ShowcaseVideo;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Vitrine pública aberta por um usuário logado de OUTRA produtora: o scope BelongsToTenant
 * filtraria pelo tenant do usuário, e não pelo da vitrine. Dados fictícios.
 */
class StorefrontCrossTenantDisplayTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private User $staffB;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $this->tenantA = Tenant::create(['name' => 'Produtora Aurora', 'slug' => 'produtora-aurora', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        $tenantB = Tenant::create(['name' => 'Produtora Boreal', 'slug' => 'produtora-boreal', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);

        $category = Category::create(['tenant_id' => $this->tenantA->id, 'name' => 'Categoria Aurora', 'slug' => 'categoria-aurora', 'type' => 'pack', 'sort_order' => 1, 'is_active' => true]);
        $video = ShowcaseVideo::create(['tenant_id' => $this->tenantA->id, 'title' => 'Video Portfolio Aurora', 'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'is_vertical' => false, 'sort_order' => 1, 'is_published' => true, 'category_id' => $category->id]);
        $pack = Pack::create([
            'tenant_id' => $this->tenantA->id, 'category_id' => $category->id, 'showcase_video_id' => $video->id,
            'name' => 'Pacote Aurora Reels', 'slug' => 'pacote-aurora-reels', 'price' => 490, 'currency' => 'eur', 'video_quantity' => 4, 'status' => 'active',
        ]);
        $addon = Addon::create(['tenant_id' => $this->tenantA->id, 'name' => 'Adicional Aurora Legendas', 'slug' => 'adicional-aurora', 'default_price' => 50, 'is_active' => true]);
        $pack->addons()->attach($addon->id, ['custom_price' => 45]);

        Pack::create(['tenant_id' => $tenantB->id, 'name' => 'Pacote Boreal', 'slug' => 'pacote-boreal', 'price' => 990, 'currency' => 'eur', 'video_quantity' => 8, 'status' => 'active']);

        $this->staffB = User::create(['tenant_id' => $tenantB->id, 'name' => 'Staff Boreal', 'email' => 'staff@boreal.test', 'password' => 'secret12345', 'role' => 'admin']);
    }

    public function test_logged_user_of_another_tenant_sees_the_full_storefront(): void
    {
        $this->actingAs($this->staffB)
            ->get('/vitrine/produtora-aurora')
            ->assertOk()
            ->assertSee('Categoria Aurora')
            ->assertSee('Video Portfolio Aurora')
            ->assertSee('Pacote Aurora Reels')
            ->assertSee('Adicional Aurora Legendas')
            ->assertDontSee('Pacote Boreal');
    }
}
