<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Production;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalBrandProfileProtectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_cannot_access_production_without_completed_brand_profile(): void
    {
        $tenant = Tenant::create([
            'name' => 'Produtora Teste',
            'slug' => 'produtora-teste',
            'status' => 'active',
            'subscription_expires_at' => now()->addDays(30),
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Editor',
            'email' => 'editor@teste.com',
            'password' => bcrypt('password'),
            'role' => 'editor',
        ]);

        $cliente = Cliente::create([
            'tenant_id' => $tenant->id,
            'name' => 'Cliente Teste',
            'email' => 'cliente@teste.com',
            'password' => bcrypt('password'),
            'brand_niche' => null,
            'website' => null,
            'instagram_handle' => null,
            'target_audience' => null,
        ]);

        $production = Production::create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'created_by' => $user->id,
            'title' => 'Vídeo Institucional',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($cliente, 'cliente')
            ->get(route('portal.productions.show', $production));

        $response->assertRedirect(route('portal.profile.edit'));
        $response->assertSessionHas('warning');
    }

    public function test_client_can_access_production_with_completed_brand_profile(): void
    {
        $tenant = Tenant::create([
            'name' => 'Produtora Teste',
            'slug' => 'produtora-teste',
            'status' => 'active',
            'subscription_expires_at' => now()->addDays(30),
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Editor',
            'email' => 'editor@teste.com',
            'password' => bcrypt('password'),
            'role' => 'editor',
        ]);

        $cliente = Cliente::create([
            'tenant_id' => $tenant->id,
            'name' => 'Cliente Teste',
            'email' => 'cliente@teste.com',
            'password' => bcrypt('password'),
            'brand_niche' => 'Saúde e Estética',
            'instagram_handle' => '@clinicasaude',
            'target_audience' => 'Homens e mulheres de 25-50 anos',
        ]);

        $production = Production::create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'created_by' => $user->id,
            'title' => 'Vídeo Institucional',
            'status' => 'draft',
        ]);

        $response = $this->actingAs($cliente, 'cliente')
            ->get(route('portal.productions.show', $production));

        $response->assertStatus(200);
    }
}
