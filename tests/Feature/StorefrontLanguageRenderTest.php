<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Pack;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontLanguageRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_storefront_renders_completely_in_spanish(): void
    {
        $tenant = Tenant::create([
            'name' => 'NUME Creative',
            'slug' => 'nume-creative',
            'domain' => 'nume-creative.test',
            'status' => 'active',
        ]);

        Pack::create([
            'tenant_id' => $tenant->id,
            'name' => 'Reels Pro',
            'slug' => 'reels-pro',
            'description' => 'Pacote profissional de vídeos verticais',
            'video_quantity' => 4,
            'price' => 590,
            'status' => 'active',
        ]);

        $response = $this->withSession(['locale' => 'es'])
            ->get('/vitrine/' . $tenant->slug);

        $response->assertStatus(200);

        // Verifica elementos chaves em Espanhol
        $response->assertSee('Todos los Formatos');
        $response->assertSee('Shorts & Reels (9:16)');
        $response->assertSee('Vídeos Horizontales (16:9)');
        $response->assertSee('Quiero un Vídeo con este Estándar');
        $response->assertSee('La Metodología NUME');
        $response->assertSee('Estrategia antes de la Cámara');
        $response->assertSee('Telemetría Audiovisual & Rendimiento');
        $response->assertSee('Arsenal Técnico &amp; Nichos Audiovisuales', false);
        $response->assertSee('Reels Virales');
        $response->assertSee('VSL &amp; Ventas', false);
        $response->assertSee('Autoridad &amp; Podcasts', false);
        $response->assertSee('Planes e Inversión');
        $response->assertSee('Paquetes de Vídeos Estratégicos');
        $response->assertSee('Total estimado con IVA:');
        $response->assertSee('window.NUME_I18N', false);
        $response->assertSee('Ver Shorts');
        $response->assertSee('Ver Vídeo');
    }

    public function test_storefront_renders_completely_in_portuguese(): void
    {
        $tenant = Tenant::create([
            'name' => 'NUME Creative',
            'slug' => 'nume-creative',
            'domain' => 'nume-creative.test',
            'status' => 'active',
        ]);

        Pack::create([
            'tenant_id' => $tenant->id,
            'name' => 'Reels Pro',
            'slug' => 'reels-pro',
            'description' => 'Pacote profissional de vídeos verticais',
            'video_quantity' => 4,
            'price' => 590,
            'status' => 'active',
        ]);

        $response = $this->withSession(['locale' => 'pt_BR'])
            ->get('/vitrine/' . $tenant->slug);

        $response->assertStatus(200);

        // Verifica elementos chaves em Português
        $response->assertSee('Todos os Formatos');
        $response->assertSee('Shorts & Reels (9:16)');
        $response->assertSee('Vídeos Horizontais (16:9)');
        $response->assertSee('Quero um Vídeo Neste Padrão');
        $response->assertSee('A Metodologia NUME');
        $response->assertSee('Estratégia antes da Câmera');
        $response->assertSee('Telemetria Audiovisual & Performance');
        $response->assertSee('Arsenal Técnico & Nichos Audiovisuais');
        $response->assertSee('Reels Virais');
        $response->assertSee('VSL & Vendas');
        $response->assertSee('Autoridade & Podcasts');
        $response->assertSee('Planos & Investimento');
        $response->assertSee('Pacotes de Vídeos Estratégicos');
        $response->assertSee('Total estimado com IVA:');
        $response->assertSee('Assistir Shorts');
        $response->assertSee('Assistir Vídeo');
    }
}
