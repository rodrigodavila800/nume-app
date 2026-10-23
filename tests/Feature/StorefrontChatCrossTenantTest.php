<?php

namespace Tests\Feature;

use App\Ai\Agents\SofiSalesConsultantAgent;
use App\Models\Addon;
use App\Models\Pack;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Usuário logado de uma produtora usando o chat da vitrine de OUTRA produtora:
 * o scope BelongsToTenant filtraria pelo tenant do usuário, e não pelo da vitrine.
 */
class StorefrontChatCrossTenantTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Pack $packA;

    private User $staffB;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();

        $this->tenantA = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        $tenantB = Tenant::create(['name' => 'Produtora B', 'slug' => 'produtora-b', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);

        $this->packA = Pack::create(['tenant_id' => $this->tenantA->id, 'name' => 'Reels Starter A', 'slug' => 'reels-starter-a', 'price' => 490, 'video_quantity' => 4, 'status' => 'active']);
        $addonA = Addon::create(['tenant_id' => $this->tenantA->id, 'name' => 'Legendas A', 'slug' => 'legendas-a', 'default_price' => 50, 'is_active' => true]);
        $this->packA->addons()->attach($addonA->id, ['custom_price' => 45]);

        Pack::create(['tenant_id' => $tenantB->id, 'name' => 'Pack B', 'slug' => 'pack-b', 'price' => 990, 'video_quantity' => 8, 'status' => 'active']);

        $this->staffB = User::create(['tenant_id' => $tenantB->id, 'name' => 'Staff B', 'email' => 'staff-b@produtora.es', 'password' => 'secret12345', 'role' => 'admin']);
    }

    public function test_logged_user_of_tenant_b_sees_tenant_a_packs_on_the_old_path(): void
    {
        config(['sofi.use_ai_sdk' => false]);

        $this->actingAs($this->staffB)
            ->postJson(route('storefront.sofi-chat', $this->tenantA), ['message' => 'Quero começar com Reels'])
            ->assertOk()
            ->assertJsonPath('recommended_pack.name', 'Reels Starter A')
            ->assertJsonPath('recommended_pack.addons.0.name', 'Legendas A')
            ->assertJsonPath('recommended_pack.addons.0.price', 45);
    }

    public function test_logged_user_of_tenant_b_sees_tenant_a_packs_on_the_sdk_path(): void
    {
        config(['sofi.use_ai_sdk' => true, 'sofi.provider' => 'gemini', 'sofi.model' => null, 'sofi.keys.gemini' => 'chave-de-teste']);

        SofiSalesConsultantAgent::fake([[
            'reply' => 'O Reels Starter A é o ideal.',
            'recommended_pack_id' => $this->packA->id,
            'call_to_action' => 'open_pack_modal',
            'suggested_quick_replies' => [],
        ]])->preventStrayPrompts();

        $this->actingAs($this->staffB)
            ->postJson(route('storefront.sofi-chat', $this->tenantA), ['message' => 'Qual pacote?'])
            ->assertOk()
            ->assertJsonPath('recommended_pack.id', $this->packA->id)
            ->assertJsonPath('recommended_pack.addons.0.name', 'Legendas A');
    }
}
