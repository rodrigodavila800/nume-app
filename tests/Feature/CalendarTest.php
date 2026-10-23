<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Production;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoPublication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarTest extends TestCase
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
            'name' => 'Cliente Alpha 1',
            'email' => 'cliente.alpha@teste.com',
            'company_name' => 'Restaurante Alpha',
            'password' => bcrypt('cliente123'),
        ]);

        $this->clienteB = Cliente::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Cliente Beta 1',
            'email' => 'cliente.beta@teste.com',
            'company_name' => 'Moda Beta',
            'password' => bcrypt('cliente123'),
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get(route('calendar.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_access_calendar(): void
    {
        $production = Production::create([
            'tenant_id' => $this->tenantA->id,
            'cliente_id' => $this->clienteA->id,
            'created_by' => $this->userA->id,
            'title' => 'Vídeo Institucional Alpha',
            'status' => 'in_production',
            'target_publication_date' => now()->startOfMonth()->addDays(5),
        ]);

        $video = Video::create([
            'tenant_id' => $this->tenantA->id,
            'production_id' => $production->id,
            'title' => 'Vídeo Master Alpha',
            'status' => 'ready',
            'video_url' => 'https://vimeo.com/12345678',
        ]);

        VideoPublication::create([
            'tenant_id' => $this->tenantA->id,
            'cliente_id' => $this->clienteA->id,
            'video_id' => $video->id,
            'titulo' => 'Reels de Lançamento',
            'legenda' => 'Legenda teste de impacto',
            'plataformas' => ['instagram', 'tiktok'],
            'scheduled_at' => now()->startOfMonth()->addDays(10)->setTime(18, 0),
            'status' => 'agendado',
        ]);

        $response = $this->actingAs($this->userA)->get(route('calendar.index'));

        $response->assertStatus(200);
        $response->assertSee('Planejamento Audiovisual &amp; Social', false);
        $response->assertSee('Reels de Lançamento');
        $response->assertSee('18:00');
        $response->assertSee('Restaurante Alpha');
    }

    public function test_calendar_respects_multi_tenancy(): void
    {
        $productionB = Production::create([
            'tenant_id' => $this->tenantB->id,
            'cliente_id' => $this->clienteB->id,
            'created_by' => $this->userB->id,
            'title' => 'Produção Secreta Tenant B',
            'status' => 'in_production',
            'target_publication_date' => now()->startOfMonth()->addDays(3),
        ]);

        $videoB = Video::create([
            'tenant_id' => $this->tenantB->id,
            'production_id' => $productionB->id,
            'title' => 'Vídeo Master B',
            'status' => 'ready',
            'video_url' => 'https://vimeo.com/99999999',
        ]);

        VideoPublication::create([
            'tenant_id' => $this->tenantB->id,
            'cliente_id' => $this->clienteB->id,
            'video_id' => $videoB->id,
            'titulo' => 'Post Confidencial Tenant B',
            'legenda' => 'Legenda B',
            'plataformas' => ['youtube'],
            'scheduled_at' => now()->startOfMonth()->addDays(3),
            'status' => 'agendado',
        ]);

        $response = $this->actingAs($this->userA)->get(route('calendar.index'));

        $response->assertStatus(200);
        $response->assertDontSee('Produção Secreta Tenant B');
        $response->assertDontSee('Post Confidencial Tenant B');
    }

    public function test_calendar_can_return_json_api(): void
    {
        $response = $this->actingAs($this->userA)->getJson(route('calendar.index'));

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'selected_date',
            'stats' => ['total_scheduled', 'total_published', 'total_productions'],
            'events_by_date',
        ]);
    }
}
