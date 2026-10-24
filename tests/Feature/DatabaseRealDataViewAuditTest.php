<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Production;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseRealDataViewAuditTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'database.default' => 'mysql',
            'database.connections.mysql.database' => 'nume',
        ]);
        DB::purge('mysql');

        try {
            DB::connection('mysql')->getPdo();
            $tenant = Tenant::where('slug', 'nume')->first();
            $admin = User::where('email', 'admin@numecreative.com')->first();
            $cliente = Cliente::withoutGlobalScope('tenant')->where('email', 'cliente@teste.com')->first();

            if (! $tenant || ! $admin || ! $cliente) {
                $this->markTestSkipped('Dados reais no banco MySQL incompletos para auditoria.');
            }
        } catch (\Throwable $e) {
            $this->markTestSkipped('Conexão com MySQL real não disponível para auditoria: '.$e->getMessage());
        }
    }

    public function test_staff_views_render_successfully_with_real_seeded_data(): void
    {
        $tenant = Tenant::where('slug', 'nume')->firstOrFail();
        $admin = User::where('email', 'admin@numecreative.com')->firstOrFail();
        $production = Production::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        // 1. Lista de Produções
        $res = $this->actingAs($admin)->get(route('productions.index'));
        $res->assertStatus(200);
        $res->assertSee('Produções');
        $res->assertSee($production->title);

        // 2. Detalhes da Produção (UUID)
        $res = $this->actingAs($admin)->get(route('productions.show', $production->uuid));
        $res->assertStatus(200);
        $res->assertSee($production->title);
        $res->assertSee('Roteiros');

        // 3. Painel de Faturas & Custódia Escrow
        $res = $this->actingAs($admin)->get(route('admin.invoices.index'));
        $res->assertStatus(200);
        $res->assertSee('FAC-2026-0001');
        $res->assertSee('Zaragoza Tech Solutions S.L.');

        // 4. Painel de Assinaturas & MRR
        $res = $this->actingAs($admin)->get(route('admin.subscriptions.index'));
        $res->assertStatus(200);
        $res->assertSee('Zaragoza Tech Solutions S.L.');

        // 5. Planos Recorrentes
        $res = $this->actingAs($admin)->get(route('admin.plans.index'));
        $res->assertStatus(200);

        // 6. Pacotes de Vídeos
        $res = $this->actingAs($admin)->get(route('packs.index'));
        $res->assertStatus(200);

        // 7. Distribuição Social
        $res = $this->actingAs($admin)->get(route('distributions.index'));
        $res->assertStatus(200);

        // 8. Vitrine Pública (Storefront / Marketplace)
        $res = $this->get(route('marketplace.index'));
        $res->assertStatus(200);
        $res->assertSee('NUME Creative');
    }

    public function test_client_portal_views_render_successfully_with_real_seeded_data(): void
    {
        $tenant = Tenant::where('slug', 'nume')->firstOrFail();
        $cliente = Cliente::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('email', 'cliente@teste.com')
            ->firstOrFail();

        $production = Production::withoutGlobalScope('tenant')
            ->where('cliente_id', $cliente->id)
            ->firstOrFail();

        // 1. Dashboard do Cliente (com Contrato Recorrente e Saldo 50/50)
        $res = $this->actingAs($cliente, 'cliente')->get(route('portal.dashboard'));
        $res->assertStatus(200);
        $res->assertSee('Alejandro');

        // 2. Detalhes da Produção do Cliente
        $res = $this->actingAs($cliente, 'cliente')->get(route('portal.productions.show', $production->uuid));
        $res->assertStatus(200);
        $res->assertSee($production->title);

        // 3. Catálogo de Planos Recorrentes no Portal
        $res = $this->actingAs($cliente, 'cliente')->get(route('portal.plans.index'));
        $res->assertStatus(200);

        // 4. Catálogo de Pacotes Avulsos no Portal
        $res = $this->actingAs($cliente, 'cliente')->get(route('portal.packs.index'));
        $res->assertStatus(200);

        // 5. Hub de Redes Sociais no Portal
        $res = $this->actingAs($cliente, 'cliente')->get(route('portal.social.index'));
        $res->assertStatus(200);

        // 6. Perfil / DNA de Marca do Cliente
        $res = $this->actingAs($cliente, 'cliente')->get(route('portal.profile.edit'));
        $res->assertStatus(200);
        $res->assertSee('Zaragoza Tech Solutions S.L.');
    }
}
