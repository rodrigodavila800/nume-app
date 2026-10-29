<?php

namespace Tests\Feature;

use App\Models\Production;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductionStatusUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_status_can_be_updated_to_ready(): void
    {
        $tenant = Tenant::create([
            'name' => 'Produtora Teste',
            'slug' => 'produtora-teste-' . Str::random(5),
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Editor Teste',
            'email' => 'editor' . Str::random(5) . '@teste.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $production = Production::create([
            'tenant_id' => $tenant->id,
            'created_by' => $user->id,
            'title' => 'Vídeo de Teste',
            'status' => 'in_production',
        ]);

        $response = $this->actingAs($user)
            ->patch(route('productions.update-status', $production), [
                'status' => 'ready',
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('productions', [
            'id' => $production->id,
            'status' => 'ready',
        ]);
    }

    public function test_production_status_get_route_redirects_to_show(): void
    {
        $tenant = Tenant::create([
            'name' => 'Produtora Teste 2',
            'slug' => 'produtora-teste-' . Str::random(5),
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Editor Teste 2',
            'email' => 'editor' . Str::random(5) . '@teste.com',
            'password' => Hash::make('password'),
            'role' => 'editor',
        ]);

        $production = Production::create([
            'tenant_id' => $tenant->id,
            'created_by' => $user->id,
            'title' => 'Vídeo de Teste 2',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($user)
            ->get(route('productions.show', $production) . '/status');

        $response->assertRedirect('/productions/' . $production->uuid);
    }
}
