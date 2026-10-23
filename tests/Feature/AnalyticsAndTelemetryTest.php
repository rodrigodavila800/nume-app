<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Pack;
use App\Models\PackPurchase;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsAndTelemetryTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $staffUser;
    protected Cliente $cliente;
    protected Pack $pack;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'NUME Zaragoza Studio',
            'slug' => 'nume',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $this->staffUser = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin Analytics',
            'email' => 'admin@analytics.es',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $this->cliente = Cliente::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente Aragão SL',
            'email' => 'aragao@cliente.es',
            'password' => bcrypt('password123'),
            'status' => 'active',
        ]);

        $this->pack = Pack::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pack TikTok Retención',
            'slug' => 'pack-tiktok-retencion',
            'price' => 1000.00,
            'video_quantity' => 10,
            'status' => 'active',
        ]);
    }

    public function test_staff_can_view_analytics_dashboard_with_gateways_breakdown(): void
    {
        // 1 Compra via Bizum
        PackPurchase::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'pack_id' => $this->pack->id,
            'video_quantity' => 10,
            'amount' => 1210.00,
            'subtotal' => 1000.00,
            'tax_rate' => 21.0,
            'tax_amount' => 210.00,
            'total_amount' => 1210.00,
            'payment_gateway' => 'bizum',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        // 1 Compra via Redsys Tarjeta
        PackPurchase::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'pack_id' => $this->pack->id,
            'video_quantity' => 10,
            'amount' => 605.00,
            'subtotal' => 500.00,
            'tax_rate' => 21.0,
            'tax_amount' => 105.00,
            'total_amount' => 605.00,
            'payment_gateway' => 'redsys_card',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($this->staffUser, 'web')
            ->get(route('analytics.index'));

        $response->assertOk();
        $response->assertSee('Dashboard de Vendas & Telemetria');
        $response->assertSee('1.815,00'); // Total faturado
        $response->assertSee('Bizum (BBVA)');
        $response->assertSee('Tarjeta (BBVA)');
        $response->assertSee('Sofi AI Copilot');
    }

    public function test_sofi_data_feed_endpoint_returns_json_with_kpis_and_breakdown(): void
    {
        PackPurchase::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'pack_id' => $this->pack->id,
            'video_quantity' => 10,
            'amount' => 1210.00,
            'subtotal' => 1000.00,
            'tax_rate' => 21.0,
            'tax_amount' => 210.00,
            'total_amount' => 1210.00,
            'payment_gateway' => 'bizum',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($this->staffUser, 'web')
            ->get(route('analytics.sofi-feed'));

        $response->assertOk();
        $response->assertJsonStructure([
            'status',
            'agent',
            'generated_at',
            'tenant' => ['id', 'name'],
            'kpis' => ['grossRevenue', 'baseRevenue', 'totalIva', 'totalSalesCount', 'averageTicket'],
            'gateways_breakdown' => ['bizum', 'redsys_card', 'stripe'],
            'pipeline',
            'sofi_actionable_insights',
            'pack_performance',
        ]);

        $this->assertEquals(1210.00, $response->json('kpis.grossRevenue'));
        $this->assertEquals(1, $response->json('gateways_breakdown.bizum.count'));
    }

    public function test_telemetry_respects_tenant_isolation(): void
    {
        $otherTenant = Tenant::create([
            'name' => 'Outra Produtora',
            'slug' => 'outra',
            'status' => 'active',
        ]);

        // Compra no outro tenant
        PackPurchase::withoutGlobalScope('tenant')->create([
            'tenant_id' => $otherTenant->id,
            'cliente_id' => $this->cliente->id,
            'pack_id' => $this->pack->id,
            'video_quantity' => 10,
            'amount' => 5000.00,
            'subtotal' => 5000.00,
            'total_amount' => 5000.00,
            'payment_gateway' => 'stripe',
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($this->staffUser, 'web')
            ->get(route('analytics.sofi-feed'));

        $response->assertOk();
        // Não deve somar os 5000 do outro tenant
        $this->assertEquals(0, $response->json('kpis.grossRevenue'));
    }
}
