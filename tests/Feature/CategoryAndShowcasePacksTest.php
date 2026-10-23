<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Pack;
use App\Models\ShowcaseVideo;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\CategoryAndPacksSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryAndShowcasePacksTest extends TestCase
{
    use RefreshDatabase;

    public function test_categories_and_spanish_packs_seeder_populates_catalog_correctly(): void
    {
        $tenant = Tenant::create([
            'name' => 'NUME Creative Zaragoza',
            'slug' => 'nume',
            'status' => 'active',
            'social_whatsapp' => '+34 976 000 111',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $this->seed(CategoryAndPacksSeeder::class);

        $this->assertDatabaseHas('categories', [
            'tenant_id' => $tenant->id,
            'slug' => 'posicionamiento',
            'name' => 'Posicionamiento de Marca',
        ]);

        $this->assertDatabaseHas('packs', [
            'tenant_id' => $tenant->id,
            'slug' => 'pack-posicionamiento-basico',
            'price' => 600.00,
            'currency' => 'eur',
            'vat_rate' => 21.00,
        ]);

        $this->assertDatabaseHas('packs', [
            'tenant_id' => $tenant->id,
            'slug' => 'pack-ads-campana',
            'price' => 700.00,
            'currency' => 'eur',
        ]);

        $response = $this->get('/');
        $response->assertStatus(200);
        $response->assertSee('Pack Posicionamiento Básico');
        $response->assertSee('Campaña de Anuncios (Pack Ads)');
        $response->assertSee('600');
        $response->assertSee('+ IVA');
        $response->assertSee('Añade a tu pack / Opcionais');
        $response->assertSee('Composición Visual & Efectos (VFX)');
    }

    public function test_staff_can_select_category_when_creating_showcase_video(): void
    {
        $tenant = Tenant::create([
            'name' => 'NUME Test',
            'slug' => 'nume-test',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin Test',
            'email' => 'admin@test.com',
            'password' => bcrypt('secret123'),
            'role' => 'admin',
        ]);

        $category = Category::create([
            'tenant_id' => $tenant->id,
            'name' => 'Reels / Shorts (9:16)',
            'slug' => 'reels-shorts',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->get(route('showcase-videos.create'));
        $response->assertStatus(200);
        $response->assertSee('Categoria Estratégica');
        $response->assertSee('Reels / Shorts (9:16)');

        $postResponse = $this->actingAs($user)->post(route('showcase-videos.store'), [
            'title' => 'Vídeo Teste com Categoria',
            'video_url' => 'https://youtube.com/shorts/Ovla_x3EAgc',
            'category_id' => $category->id,
            'sort_order' => 1,
            'is_published' => true,
        ]);

        $postResponse->assertRedirect(route('showcase-videos.index'));
        $this->assertDatabaseHas('showcase_videos', [
            'tenant_id' => $tenant->id,
            'title' => 'Vídeo Teste com Categoria',
            'category_id' => $category->id,
            'is_vertical' => true,
        ]);
    }

    public function test_showcase_videos_can_be_filtered_by_vertical_and_horizontal_format(): void
    {
        $tenant = Tenant::create([
            'name' => 'NUME Zaragoza',
            'slug' => 'nume-zgza',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin Video',
            'email' => 'admin-video@test.com',
            'password' => bcrypt('secret123'),
            'role' => 'admin',
        ]);

        // Cria 1 vídeo vertical e 1 vídeo horizontal
        $verticalVideo = ShowcaseVideo::create([
            'tenant_id' => $tenant->id,
            'title' => 'Shorts Viral de Alta Retenção',
            'video_url' => 'https://www.youtube.com/shorts/Ovla_x3EAgc',
            'is_vertical' => true,
            'sort_order' => 1,
            'is_published' => true,
        ]);

        $horizontalVideo = ShowcaseVideo::create([
            'tenant_id' => $tenant->id,
            'title' => 'Vídeo Institucional 4K',
            'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'is_vertical' => false,
            'sort_order' => 2,
            'is_published' => true,
        ]);

        // Verifica thumbnail fallback inteligente
        $this->assertEquals('https://img.youtube.com/vi/Ovla_x3EAgc/hqdefault.jpg', $verticalVideo->effective_thumbnail_url);
        $this->assertEquals('https://img.youtube.com/vi/dQw4w9WgXcQ/hqdefault.jpg', $horizontalVideo->effective_thumbnail_url);

        // Acessa a listagem completa
        $responseAll = $this->actingAs($user)->get(route('showcase-videos.index'));
        $responseAll->assertStatus(200);
        $responseAll->assertSee('Shorts Viral de Alta Retenção');
        $responseAll->assertSee('Vídeo Institucional 4K');
        $responseAll->assertSee('Verticais (9:16)');
        $responseAll->assertSee('Horizontais (16:9)');

        // Filtra somente verticais
        $responseVertical = $this->actingAs($user)->get(route('showcase-videos.index', ['format' => 'vertical']));
        $responseVertical->assertStatus(200);
        $responseVertical->assertSee('Shorts Viral de Alta Retenção');
        $responseVertical->assertDontSee('Vídeo Institucional 4K');

        // Filtra somente horizontais
        $responseHorizontal = $this->actingAs($user)->get(route('showcase-videos.index', ['format' => 'horizontal']));
        $responseHorizontal->assertStatus(200);
        $responseHorizontal->assertDontSee('Shorts Viral de Alta Retenção');
        $responseHorizontal->assertSee('Vídeo Institucional 4K');
    }
}
