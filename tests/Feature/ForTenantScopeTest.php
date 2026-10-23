<?php

namespace Tests\Feature;

use App\Models\Pack;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForTenantScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_tenant_ignores_the_logged_user_scope(): void
    {
        $a = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);
        $b = Tenant::create(['name' => 'Produtora B', 'slug' => 'produtora-b', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);

        Pack::create(['tenant_id' => $a->id, 'name' => 'Pacote A', 'slug' => 'pacote-a', 'price' => 100, 'currency' => 'eur', 'video_quantity' => 1, 'status' => 'active']);
        Pack::create(['tenant_id' => $b->id, 'name' => 'Pacote B', 'slug' => 'pacote-b', 'price' => 200, 'currency' => 'eur', 'video_quantity' => 1, 'status' => 'active']);

        $this->actingAs(User::create(['tenant_id' => $b->id, 'name' => 'Staff B', 'email' => 'staff@b.test', 'password' => 'secret12345', 'role' => 'admin']));

        // O scope global filtra pelo tenant do usuário logado (B)...
        $this->assertSame(['Pacote B'], Pack::pluck('name')->all());
        // ...e forTenant() consulta o tenant pedido, sem depender dele.
        $this->assertSame(['Pacote A'], Pack::forTenant($a->id)->pluck('name')->all());
        $this->assertSame(['Pacote B'], Pack::forTenant($b->id)->pluck('name')->all());
    }
}
