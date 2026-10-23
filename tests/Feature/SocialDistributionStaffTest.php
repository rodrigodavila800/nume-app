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

class SocialDistributionStaffTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $staffA;
    private User $staffB;
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

        $this->staffA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Staff Alpha',
            'email' => 'staff@alpha.com',
            'password' => bcrypt('Senha@Segura123'),
            'role' => 'editor',
        ]);

        $this->staffB = User::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Staff Beta',
            'email' => 'staff@beta.com',
            'password' => bcrypt('Senha@Segura123'),
            'role' => 'editor',
        ]);

        $this->clienteA = Cliente::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Cliente Alpha',
            'email' => 'cliente@alpha.com',
            'password' => bcrypt('Senha@Segura123'),
        ]);

        $this->clienteB = Cliente::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Cliente Beta',
            'email' => 'cliente@beta.com',
            'password' => bcrypt('Senha@Segura123'),
        ]);
    }

    public function test_staff_can_view_distributions_index(): void
    {
        VideoPublication::create([
            'tenant_id' => $this->tenantA->id,
            'cliente_id' => $this->clienteA->id,
            'titulo' => 'Vídeo Campanha Alpha 01',
            'plataformas' => ['instagram', 'tiktok'],
            'status' => 'publicado',
        ]);

        $response = $this->actingAs($this->staffA)
            ->get(route('distributions.index'));

        $response->assertStatus(200);
        $response->assertSee('Distribuição Social Multiplataforma');
        $response->assertSee('Vídeo Campanha Alpha 01');
    }

    public function test_tenant_isolation_prevents_seeing_other_tenants_publications(): void
    {
        VideoPublication::create([
            'tenant_id' => $this->tenantB->id,
            'cliente_id' => $this->clienteB->id,
            'titulo' => 'Publicação Secreta do Concorrente Beta',
            'plataformas' => ['instagram'],
            'status' => 'publicado',
        ]);

        $response = $this->actingAs($this->staffA)
            ->get(route('distributions.index'));

        $response->assertStatus(200);
        $response->assertDontSee('Publicação Secreta do Concorrente Beta');
    }

    public function test_staff_can_refresh_publication_status(): void
    {
        $publication = VideoPublication::create([
            'tenant_id' => $this->tenantA->id,
            'cliente_id' => $this->clienteA->id,
            'upload_post_request_id' => 'mock_req_staff_test',
            'titulo' => 'Publicação com Polling',
            'plataformas' => ['instagram'],
            'status' => 'processando',
        ]);

        $response = $this->actingAs($this->staffA)
            ->post(route('distributions.refresh', $publication));

        $response->assertRedirect();
        $this->assertEquals('publicado', $publication->fresh()->status);
    }

    public function test_staff_can_view_video_details_with_distribution_pipeline(): void
    {
        $production = Production::create([
            'tenant_id' => $this->tenantA->id,
            'cliente_id' => $this->clienteA->id,
            'created_by' => $this->staffA->id,
            'title' => 'Produção Piloto Teste',
            'status' => 'approved',
        ]);

        $video = Video::create([
            'tenant_id' => $this->tenantA->id,
            'production_id' => $production->id,
            'title' => 'Corte Final Piloto',
            'video_url' => 'https://youtube.com/shorts/x2BAPBEbSpk',
            'status' => 'published',
            'published_at' => now(),
            'sofi_seo_title' => 'Título SEO de Impacto',
        ]);

        $response = $this->actingAs($this->staffA)
            ->get(route('videos.show', ['production' => $production, 'video' => $video]));

        $response->assertStatus(200);
        $response->assertSee('Fluxo de Entrega &amp; Distribuição Social Multiplataforma', false);
        $response->assertSee('Assistir no YouTube Shorts');
        $response->assertSee('Disparo Direto para as Redes do Cliente');
    }

    public function test_staff_can_distribute_video_to_social_platforms(): void
    {
        $production = Production::create([
            'tenant_id' => $this->tenantA->id,
            'cliente_id' => $this->clienteA->id,
            'created_by' => $this->staffA->id,
            'title' => 'Produção de Lançamento',
            'status' => 'approved',
        ]);

        $video = Video::create([
            'tenant_id' => $this->tenantA->id,
            'production_id' => $production->id,
            'title' => 'Vídeo Oficial da Campanha',
            'video_url' => 'https://youtube.com/shorts/campaign123',
            'status' => 'published',
            'published_at' => now(),
            'sofi_seo_title' => 'SEO Title Campanha',
        ]);

        $response = $this->actingAs($this->staffA)
            ->post(route('videos.distribute', ['production' => $production, 'video' => $video]), [
                'plataformas' => ['youtube', 'instagram'],
                'video_url' => 'https://youtube.com/shorts/campaign123',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('video_publications', [
            'tenant_id' => $this->tenantA->id,
            'cliente_id' => $this->clienteA->id,
            'video_id' => $video->id,
            'status' => 'processando',
        ]);
    }
}
