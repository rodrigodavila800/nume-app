<?php

namespace Tests\Feature;

use App\Services\SofiTranslatorService;
use Tests\TestCase;

class SofiTranslatorTest extends TestCase
{
    public function test_translator_service_translates_known_phrases_from_dictionary(): void
    {
        $translator = app(SofiTranslatorService::class);

        $translated = $translator->translate('Vídeos Estratégicos que Escalam Marcas', 'es', 'pt_BR');
        $this->assertEquals('Videos Estratégicos que Escalan Marcas', $translated);

        $translatedCta = $translator->translate('Quero um Vídeo Neste Padrão', 'es', 'pt_BR');
        $this->assertEquals('Quiero un Vídeo con este Estándar', $translatedCta);
    }

    public function test_translator_service_handles_batch_translations(): void
    {
        $translator = app(SofiTranslatorService::class);

        $results = $translator->translateBatch([
            'home' => 'Início',
            'method' => 'Metodologia',
            'metrics' => 'Métricas',
        ], 'es', 'pt_BR');

        $this->assertEquals('Inicio', $results['home']);
        $this->assertEquals('Metodología', $results['method']);
        $this->assertEquals('Métricas', $results['metrics']);
    }

    public function test_api_translation_endpoint_returns_json(): void
    {
        $response = $this->postJson('/api/v1/translate', [
            'text' => 'Início',
            'to' => 'es',
            'from' => 'pt_BR',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'translation' => 'Inicio',
                'target' => 'es',
                'source' => 'pt_BR',
            ]);
    }

    public function test_api_translation_batch_endpoint_returns_json(): void
    {
        $response = $this->postJson('/api/v1/translate', [
            'texts' => [
                'Início',
                'Quero um Vídeo Neste Padrão',
            ],
            'to' => 'es',
            'from' => 'pt_BR',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'translations' => [
                    'Inicio',
                    'Quiero un Vídeo con este Estándar',
                ],
                'target' => 'es',
                'source' => 'pt_BR',
            ]);
    }

    public function test_locale_switch_route_updates_session(): void
    {
        $response = $this->get('/lang/es');
        $response->assertSessionHas('locale', 'es');

        $responsePt = $this->get('/lang/pt_BR');
        $responsePt->assertSessionHas('locale', 'pt_BR');
    }
}
