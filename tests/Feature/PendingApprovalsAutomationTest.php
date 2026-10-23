<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Production;
use App\Models\Roteiro;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PendingApprovalsAutomationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Cliente $cliente;
    private Production $production;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Produtora Alpha',
            'slug' => 'produtora-alpha',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Staff Alpha',
            'email' => 'alpha@nume.test',
            'password' => bcrypt('secret123'),
        ]);

        $this->cliente = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente Alpha',
            'email' => 'cliente@alpha.test',
            'company_name' => 'Alpha Corp',
            'password' => bcrypt('secret123'),
        ]);

        $this->production = Production::create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'created_by' => $this->user->id,
            'title' => 'Vídeo Institucional',
            'status' => 'in_production',
        ]);
    }

    public function test_artisan_command_notifies_stale_submitted_roteiros(): void
    {
        // Roteiro 1: Submetido há 50 horas (deve ser notificado)
        $staleRoteiro = Roteiro::create([
            'tenant_id' => $this->tenant->id,
            'production_id' => $this->production->id,
            'created_by' => $this->user->id,
            'content' => 'Roteiro atrasado aguardando cliente.',
            'status' => 'submitted',
            'submitted_at' => now()->subHours(50),
        ]);

        // Roteiro 2: Submetido há apenas 2 horas (não deve ser notificado)
        $recentRoteiro = Roteiro::create([
            'tenant_id' => $this->tenant->id,
            'production_id' => $this->production->id,
            'created_by' => $this->user->id,
            'content' => 'Roteiro recém-enviado.',
            'status' => 'submitted',
            'submitted_at' => now()->subHours(2),
        ]);

        // Roteiro 3: Aprovado (não deve ser notificado)
        $approvedRoteiro = Roteiro::create([
            'tenant_id' => $this->tenant->id,
            'production_id' => $this->production->id,
            'created_by' => $this->user->id,
            'content' => 'Roteiro já aprovado.',
            'status' => 'approved',
            'submitted_at' => now()->subHours(60),
            'approved_at' => now()->subHours(10),
        ]);

        $this->artisan('approvals:check-pending', ['--hours' => 48])
            ->expectsOutputToContain('1 follow-ups de aprovação registrados com sucesso')
            ->assertSuccessful();

        $staleRoteiro->refresh();
        $this->assertEquals(1, $staleRoteiro->reminder_count);
        $this->assertNotNull($staleRoteiro->last_reminded_at);

        $recentRoteiro->refresh();
        $this->assertEquals(0, $recentRoteiro->reminder_count);
        $this->assertNull($recentRoteiro->last_reminded_at);

        $approvedRoteiro->refresh();
        $this->assertEquals(0, $approvedRoteiro->reminder_count);
    }

    public function test_artisan_command_respects_24h_anti_spam_cooldown(): void
    {
        // Roteiro submetido há 72 horas, mas já notificado há 3 horas
        $cooldownRoteiro = Roteiro::create([
            'tenant_id' => $this->tenant->id,
            'production_id' => $this->production->id,
            'created_by' => $this->user->id,
            'content' => 'Roteiro com lembrete recente.',
            'status' => 'submitted',
            'submitted_at' => now()->subHours(72),
            'last_reminded_at' => now()->subHours(3),
            'reminder_count' => 1,
        ]);

        $this->artisan('approvals:check-pending', ['--hours' => 48])
            ->expectsOutputToContain('Nenhum roteiro com aprovação pendente em atraso')
            ->assertSuccessful();

        $cooldownRoteiro->refresh();
        $this->assertEquals(1, $cooldownRoteiro->reminder_count);
    }
}
