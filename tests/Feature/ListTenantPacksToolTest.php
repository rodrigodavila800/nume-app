<?php

namespace Tests\Feature;

use App\Ai\Tools\ListTenantPacksTool;
use App\Models\Addon;
use App\Models\Pack;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class ListTenantPacksToolTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = $this->tenant('a');
        $this->tenantB = $this->tenant('b');

        $packA = $this->pack($this->tenantA, 'Reels Starter A', 490, 4);
        $this->pack($this->tenantA, 'Pack Inativo A', 100, 1, 'inactive');
        $this->pack($this->tenantB, 'Autoridade B', 990, 8);

        $addonA = Addon::create(['tenant_id' => $this->tenantA->id, 'name' => 'Legendas A', 'slug' => 'legendas-a', 'default_price' => 50, 'is_active' => true]);
        $addonB = Addon::create(['tenant_id' => $this->tenantB->id, 'name' => 'Drone B', 'slug' => 'drone-b', 'default_price' => 200, 'is_active' => true]);

        // Vínculo cruzado indevido no pivot: o adicional de B nunca pode aparecer no pacote de A.
        $packA->addons()->attach($addonA->id, ['custom_price' => 45]);
        $packA->addons()->attach($addonB->id);
    }

    public function test_returns_only_active_packs_of_its_own_tenant_with_public_fields(): void
    {
        $packs = (new ListTenantPacksTool($this->tenantA->id))->packs();

        $this->assertCount(1, $packs);
        $this->assertSame('Reels Starter A', $packs[0]['name']);
        $this->assertSame(490.0, $packs[0]['price']);
        $this->assertSame(4, $packs[0]['video_quantity']);
        $this->assertSame([['name' => 'Legendas A', 'price' => 45.0]], $packs[0]['addons']);
        $this->assertSame(['id', 'name', 'slug', 'video_quantity', 'price', 'currency', 'description', 'addons'], array_keys($packs[0]));
    }

    public function test_tool_of_one_tenant_never_returns_packs_of_the_other(): void
    {
        $outputA = (new ListTenantPacksTool($this->tenantA->id))->handle(new Request([]));
        $outputB = (new ListTenantPacksTool($this->tenantB->id))->handle(new Request([]));

        $this->assertStringContainsString('Reels Starter A', $outputA);
        $this->assertStringNotContainsString('Autoridade B', $outputA);
        $this->assertStringNotContainsString('Drone B', $outputA);

        $this->assertStringContainsString('Autoridade B', $outputB);
        $this->assertStringNotContainsString('Reels Starter A', $outputB);
    }

    public function test_logged_in_user_of_another_tenant_does_not_change_the_result(): void
    {
        // Staff de B navegando na vitrine pública de A: o scope filtraria por B.
        $this->actingAs(User::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Staff B',
            'email' => 'staff-b@produtora.es',
            'password' => 'secret12345',
            'role' => 'admin',
        ]));

        $packs = (new ListTenantPacksTool($this->tenantA->id))->packs();

        $this->assertSame(['Reels Starter A'], array_column($packs, 'name'));
        $this->assertSame([['name' => 'Legendas A', 'price' => 45.0]], $packs[0]['addons']);
    }

    private function tenant(string $suffix): Tenant
    {
        return Tenant::create([
            'name' => "Produtora {$suffix}",
            'slug' => "produtora-{$suffix}",
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);
    }

    private function pack(Tenant $tenant, string $name, float $price, int $videos, string $status = 'active'): Pack
    {
        return Pack::create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'slug' => str($name)->slug(),
            'price' => $price,
            'video_quantity' => $videos,
            'status' => $status,
        ]);
    }
}
