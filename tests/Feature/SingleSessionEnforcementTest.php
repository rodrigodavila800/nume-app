<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SingleSessionEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $adminUser;
    protected Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Produtora Teste',
            'slug' => 'produtora-teste',
            'status' => 'active',
        ]);

        $this->adminUser = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin Usuário',
            'email' => 'admin@produtora.es',
            'password' => bcrypt('segredo123'),
            'role' => 'admin',
        ]);

        $this->cliente = Cliente::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente Teste',
            'email' => 'cliente@teste.es',
            'password' => bcrypt('segredo123'),
            'status' => 'active',
        ]);
    }

    public function test_admin_login_invalidates_previous_sessions_in_database(): void
    {
        // Simula uma sessão anterior ativa no navegador Chrome
        $previousSessionId = 'chrome_session_previous_id_123';
        DB::table('sessions')->insert([
            'id' => $previousSessionId,
            'user_id' => $this->adminUser->id,
            'ip_address' => '192.168.1.10',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Chrome/120)',
            'payload' => serialize(['some' => 'data']),
            'last_activity' => now()->timestamp,
        ]);

        $this->assertDatabaseHas('sessions', [
            'id' => $previousSessionId,
            'user_id' => $this->adminUser->id,
        ]);

        // Faz novo login (ex: no Firefox)
        $response = $this->post(route('login'), [
            'tenant_slug' => $this->tenant->slug,
            'email' => $this->adminUser->email,
            'password' => 'segredo123',
        ]);

        $response->assertRedirect(route('productions.index'));
        $this->assertAuthenticatedAs($this->adminUser, 'web');

        // A sessão antiga no Chrome DEVE ter sido removida da tabela sessions
        $this->assertDatabaseMissing('sessions', [
            'id' => $previousSessionId,
        ]);
    }

    public function test_cliente_portal_login_invalidates_previous_sessions_in_database(): void
    {
        $previousSessionId = 'portal_cliente_session_previous_id_456';
        DB::table('sessions')->insert([
            'id' => $previousSessionId,
            'user_id' => $this->cliente->id,
            'ip_address' => '192.168.1.20',
            'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0)',
            'payload' => serialize(['some' => 'data']),
            'last_activity' => now()->timestamp,
        ]);

        $this->assertDatabaseHas('sessions', [
            'id' => $previousSessionId,
            'user_id' => $this->cliente->id,
        ]);

        $response = $this->post('/portal/login', [
            'tenant_slug' => $this->tenant->slug,
            'email' => $this->cliente->email,
            'password' => 'segredo123',
        ]);

        $response->assertRedirect(route('portal.dashboard'));
        $this->assertAuthenticatedAs($this->cliente, 'cliente');

        $this->assertDatabaseMissing('sessions', [
            'id' => $previousSessionId,
        ]);
    }
}
