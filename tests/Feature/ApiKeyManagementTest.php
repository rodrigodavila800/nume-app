<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiKeyManagementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Produtora Criativa',
            'slug' => 'produtora-criativa',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'role' => 'admin',
        ]);
    }

    public function test_staff_can_view_api_keys_dashboard(): void
    {
        $response = $this->actingAs($this->user)->get(route('api_keys.index'));

        $response->assertOk();
        $response->assertSee('Chaves de API & Desenvolvedores');
    }

    public function test_staff_can_generate_new_api_key(): void
    {
        $response = $this->actingAs($this->user)->post(route('api_keys.store'), [
            'name' => 'Make.com Automation',
            'rate_limit_per_minute' => 120,
        ]);

        $response->assertRedirect(route('api_keys.index'));
        $response->assertSessionHas('plainTextToken');

        $this->assertDatabaseHas('api_keys', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Make.com Automation',
            'rate_limit_per_minute' => 120,
            'revoked_at' => null,
        ]);
    }

    public function test_staff_can_revoke_api_key(): void
    {
        $tokenData = ApiKey::createToken($this->tenant, 'ERP Integration');
        $apiKey = $tokenData['apiKey'];

        $response = $this->actingAs($this->user)->delete(route('api_keys.destroy', $apiKey));

        $response->assertRedirect(route('api_keys.index'));
        $this->assertFalse($apiKey->fresh()->isValid());
        $this->assertNotNull($apiKey->fresh()->revoked_at);
    }

    public function test_tenant_isolation_prevents_revoking_other_tenant_key(): void
    {
        $otherTenant = Tenant::create([
            'name' => 'Outra Produtora',
            'slug' => 'outra-produtora',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $tokenData = ApiKey::createToken($otherTenant, 'Chave de Outro');
        $otherKey = $tokenData['apiKey'];

        $response = $this->actingAs($this->user)->delete(route('api_keys.destroy', $otherKey));

        $this->assertTrue(in_array($response->status(), [403, 404]));
        $this->assertTrue($otherKey->fresh()->isValid());
    }
}
