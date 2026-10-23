<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Production;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformSeoAndSocialsTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $admin;
    protected Cliente $cliente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'NUME Vídeos Estratégicos',
            'slug' => 'nume',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $this->admin = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin NUME',
            'email' => 'admin@numecreative.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $this->cliente = Cliente::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente Teste',
            'email' => 'cliente@teste.com',
            'password' => bcrypt('password123'),
            'company_name' => 'Zaragoza Tech Solutions',
            'brand_niche' => 'Logística Industrial',
            'target_audience' => 'Diretores de Operações',
        ]);
    }

    public function test_admin_can_access_platform_settings_and_update_socials_and_seo(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.settings.platform'));

        $response->assertStatus(200);
        $response->assertSee('Redes Sociais & SEO da Plataforma');

        $updateResponse = $this->actingAs($this->admin)
            ->post(route('admin.settings.platform.update'), [
                'social_instagram' => '@numecreative',
                'social_youtube' => 'https://youtube.com/@numecreative',
                'social_linkedin' => 'https://linkedin.com/company/numecreative',
                'social_whatsapp' => '+34600000000',
                'seo_meta_title' => 'NUME | Vídeos Estratégicos de Alta Conversão',
                'seo_meta_description' => 'Produção de vídeos focada em autoridade e retenção.',
            ]);

        $updateResponse->assertRedirect(route('admin.settings.platform'));

        $this->tenant->refresh();
        $this->assertEquals('@numecreative', $this->tenant->social_instagram);
        $this->assertEquals('NUME | Vídeos Estratégicos de Alta Conversão', $this->tenant->seo_meta_title);
        $this->assertTrue(count($this->tenant->socialLinks()) >= 3);
    }

    public function test_sofi_can_generate_tenant_seo_via_ajax_endpoint(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('admin.settings.platform.regenerate-seo'));

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);

        $this->tenant->refresh();
        $this->assertNotEmpty($this->tenant->seo_meta_title);
        $this->assertNotEmpty($this->tenant->seo_meta_description);
        $this->assertNotNull($this->tenant->seo_last_generated_at);
        $this->assertTrue($this->tenant->hasSeoGenerated());
    }

    public function test_publishing_video_generates_sofi_seo_pack_for_client(): void
    {
        $production = Production::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $this->cliente->id,
            'created_by' => $this->admin->id,
            'title' => 'Vídeo Institucional B2B',
            'status' => 'in_production',
            'uuid' => (string) Str::uuid(),
        ]);

        $video = Video::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'production_id' => $production->id,
            'title' => 'Corte Oficial 01',
            'video_url' => 'https://example.com/video.mp4',
            'status' => 'ready',
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('videos.publish', [$production, $video]));

        $response->assertSessionHas('success');

        $video->refresh();
        $this->assertEquals('published', $video->status);
        $this->assertNotEmpty($video->sofi_seo_title);
        $this->assertNotEmpty($video->sofi_seo_description);
        $this->assertNotEmpty($video->sofi_hashtags);
        $this->assertNotNull($video->sofi_seo_generated_at);
        $this->assertStringContainsString('Zaragoza Tech Solutions', $video->sofi_seo_title);

        // Cliente acessa a produção no portal e vê o Sofi SEO Pack
        $portalResponse = $this->actingAs($this->cliente, 'cliente')
            ->get(route('portal.productions.show', $production));

        $portalResponse->assertStatus(200);
        $portalResponse->assertSee('Sofi SEO Pack: Estratégia de Postagem Recomendada');
        $portalResponse->assertSee($video->sofi_seo_title);
        $portalResponse->assertSee('Copiar Tudo');
    }
}
