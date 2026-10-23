<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Production;
use App\Models\Roteiro;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BatchContentPlannerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;
    private Cliente $clienteA;
    private Cliente $clienteB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name' => 'Produtora Alpha',
            'slug' => 'produtora-alpha',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Produtora Beta',
            'slug' => 'produtora-beta',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $this->userA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Staff Alpha',
            'email' => 'alpha@nume.test',
            'password' => bcrypt('secret123'),
        ]);

        $this->userB = User::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Staff Beta',
            'email' => 'beta@nume.test',
            'password' => bcrypt('secret123'),
        ]);

        $this->clienteA = Cliente::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Cliente Alpha',
            'email' => 'cliente.alpha@teste.com',
            'company_name' => 'Clínica Dermatológica Alpha',
            'brand_niche' => 'Saúde e Estética',
            'password' => bcrypt('secret123'),
        ]);

        $this->clienteB = Cliente::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Cliente Beta',
            'email' => 'cliente.beta@teste.com',
            'company_name' => 'Advocacia Beta',
            'password' => bcrypt('secret123'),
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('batch_planner.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_access_batch_planner(): void
    {
        $response = $this->actingAs($this->userA)->get(route('batch_planner.index'));

        $response->assertStatus(200);
        $response->assertSee('Planejador Mensal de Conteúdo em Lote');
        $response->assertSee('Clínica Dermatológica Alpha');
        $response->assertDontSee('Advocacia Beta');
    }

    public function test_user_can_generate_batch_editorial_plan(): void
    {
        $response = $this->actingAs($this->userA)->post(route('batch_planner.generate'), [
            'cliente_id' => $this->clienteA->id,
            'theme' => 'Rejuvenescimento Facial com Tecnologia Laser',
            'quantity' => '4',
            'language' => 'pt_BR',
            'notes' => 'Focar em segurança médica e agendamento de avaliação.',
        ]);

        $response->assertRedirect(route('batch_planner.index', ['cliente_id' => $this->clienteA->id]));
        $response->assertSessionHas('generated_plan');
        $response->assertSessionHas('plan_meta');

        $plan = session('generated_plan');
        $this->assertCount(4, $plan);
        $this->assertNotEmpty($plan[0]['script_content']);
        $this->assertNotEmpty($plan[0]['hook']);
    }

    public function test_user_can_commit_batch_plan_creating_productions_and_roteiros(): void
    {
        $payload = [
            'cliente_id' => $this->clienteA->id,
            'items' => [
                [
                    'title' => 'Vídeo 1: O Maior Erro no Laser Facial',
                    'description' => 'Topo de funil - Interrupção de padrão',
                    'script_content' => "HOOK: Pare de fazer isso no seu rosto!\nCORPO: Explicamos o cuidado com laser...",
                    'suggested_date' => now()->addDays(7)->toDateString(),
                ],
                [
                    'title' => 'Vídeo 2: Bastidores da Aplicação',
                    'description' => 'Meio de funil - Autoridade médica',
                    'script_content' => "HOOK: Veja como funciona nossa sala de laser...\nCORPO: Apresentação técnica...",
                    'suggested_date' => now()->addDays(14)->toDateString(),
                ],
                [
                    'title' => 'Vídeo 3: História da Paciente Mariana',
                    'description' => 'Fundo de funil - Caso real',
                    'script_content' => "HOOK: Ela tinha vergonha de sair sem maquiagem...\nCORPO: Transformação real...",
                    'suggested_date' => now()->addDays(21)->toDateString(),
                ],
                [
                    'title' => 'Vídeo 4: Condição Especial do Mês',
                    'description' => 'Conversão direta',
                    'script_content' => "HOOK: Apenas 5 vagas abertas para o protocolo de inverno.\nCTA: Clique no link da bio.",
                    'suggested_date' => now()->addDays(28)->toDateString(),
                ],
            ],
        ];

        $response = $this->actingAs($this->userA)->post(route('batch_planner.commit'), $payload);

        $response->assertRedirect(route('calendar.index', ['cliente_id' => $this->clienteA->id]));
        $response->assertSessionHas('success');

        $this->assertDatabaseCount('productions', 4);
        $this->assertDatabaseCount('roteiros', 4);

        $this->assertDatabaseHas('productions', [
            'tenant_id' => $this->tenantA->id,
            'cliente_id' => $this->clienteA->id,
            'title' => 'Vídeo 1: O Maior Erro no Laser Facial',
            'status' => 'in_production',
        ]);

        $this->assertDatabaseHas('roteiros', [
            'tenant_id' => $this->tenantA->id,
            'status' => 'submitted',
        ]);
    }

    public function test_tenant_isolation_prevents_generating_for_other_tenant_client(): void
    {
        $response = $this->actingAs($this->userA)->post(route('batch_planner.generate'), [
            'cliente_id' => $this->clienteB->id,
            'theme' => 'Tema Invasivo',
            'quantity' => '4',
            'language' => 'pt_BR',
        ]);

        $response->assertStatus(404);
    }
}
