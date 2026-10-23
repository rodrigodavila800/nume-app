<?php

namespace Tests\Feature;

use App\Models\ShowcaseVideo;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontShowcaseCarouselTest extends TestCase
{
    use RefreshDatabase;

    public function test_storefront_renders_showcase_carousel_with_shorts_and_conversion_funnel(): void
    {
        $tenant = Tenant::create([
            'name' => 'NUME Creative Test',
            'slug' => 'nume-test',
            'status' => 'active',
            'social_whatsapp' => '+34 976 892 100',
        ]);

        ShowcaseVideo::create([
            'tenant_id' => $tenant->id,
            'title' => 'Daniel Marketing Point Shorts',
            'video_url' => 'https://youtube.com/shorts/Ovla_x3EAgc?si=o-NQDm1D_rq-zvyJ',
            'category' => 'Reels',
            'is_published' => true,
            'is_featured' => true,
            'sort_order' => 1,
        ]);

        $response = $this->get('/vitrine/nume-test');

        $response->assertStatus(200);
        $response->assertSee('swiper-3d-portfolio');
        $response->assertSee('swiper-3d-prev');
        $response->assertSee('swiper-3d-next');
        $response->assertSee('swiper-3d-pagination');
        $response->assertSee('portfolio-format-tabs');
        $response->assertSee('Todos os Formatos');
        $response->assertSee('Shorts & Reels (9:16)');
        $response->assertSee('Vídeos Horizontais (16:9)');
        $response->assertSee('Daniel Marketing Point Shorts');
        $response->assertSee('9:16');
        $response->assertSee('storefront-video-modal');
        $response->assertSee('Quero um Vídeo Neste Padrão');
    }
}
