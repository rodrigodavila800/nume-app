<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TranslationAndLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_locale_change_sets_session_and_translates_root_page(): void
    {
        $tenant = Tenant::create([
            'name' => 'NUME Creative',
            'slug' => 'nume',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $tenant->packs()->create([
            'name' => 'Pack Impulso Local — Reels & TikTok PYME (Zaragoza)',
            'slug' => 'impulso-local-zaragoza',
            'description' => 'Especialmente desenhado para comércio, clínicas e serviços locais. 4 vídeos verticais com gancho persuasivo, edição dinâmica e legendas animadas.',
            'video_quantity' => 4,
            'price' => 490.00,
            'currency' => 'eur',
            'status' => 'active',
        ]);

        $responseEs = $this->withSession(['locale' => 'es'])->get('/');
        $responseEs->assertStatus(200);

        $content = $responseEs->getContent();

        $this->assertStringContainsString('Portal de Cliente', $content);
        $this->assertStringContainsString('Tu marca merece ser imposible de ignorar.', $content);
        $this->assertStringContainsString('Planes e Inversión', $content);
        $this->assertStringContainsString('Especialmente diseñado para comercio, clínicas y servicios locales.', $content);
    }

    public function test_portal_profile_page_translates_to_spanish(): void
    {
        $tenant = Tenant::create([
            'name' => 'NUME Creative',
            'slug' => 'nume',
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $cliente = \App\Models\Cliente::create([
            'tenant_id' => $tenant->id,
            'name' => 'Cliente Teste',
            'email' => 'cliente@teste.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->actingAs($cliente, 'cliente')
            ->withSession(['locale' => 'es'])
            ->get(route('portal.profile.edit'));

        $response->assertStatus(200);

        $content = $response->getContent();

        $this->assertStringContainsString('Perfil de Tu Marca y Redes Sociales', $content);
        $this->assertStringContainsString('Datos de la Empresa y Responsable', $content);
        $this->assertStringContainsString('Nombre del Responsable', $content);
        $this->assertStringContainsString('Canales y Redes Sociales Activas', $content);
        $this->assertStringContainsString('ADN de la Marca y Segmento de Mercado', $content);
        $this->assertStringContainsString('Guardar Perfil de la Marca', $content);
        // Assert no emoji vibecoding in labels
        $this->assertStringNotContainsString('📸', $content);
        $this->assertStringNotContainsString('💼', $content);
        $this->assertStringNotContainsString('🏢', $content);
    }
}
