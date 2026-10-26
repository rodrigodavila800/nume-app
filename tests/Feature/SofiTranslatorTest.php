<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Services\SofiTranslatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SofiTranslatorTest extends TestCase
{
    use RefreshDatabase;

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
        $response = $this->actingAs($this->staff())->postJson(route('sofi.translate'), [
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
        $response = $this->actingAs($this->staff())->postJson(route('sofi.translate'), [
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

    public function test_translation_is_closed_to_visitors_and_old_open_route_is_gone(): void
    {
        $this->postJson(route('sofi.translate'), ['text' => 'Início', 'to' => 'es'])->assertUnauthorized();
        $this->postJson('/api/v1/translate', ['text' => 'Início', 'to' => 'es'])->assertNotFound();
    }

    public function test_batch_size_is_capped(): void
    {
        $this->actingAs($this->staff())
            ->postJson(route('sofi.translate'), ['texts' => array_fill(0, 51, 'Início'), 'to' => 'es'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('texts');
    }

    public function test_locale_switch_route_updates_session(): void
    {
        $response = $this->get('/lang/es');
        $response->assertSessionHas('locale', 'es');

        $responsePt = $this->get('/lang/pt_BR');
        $responsePt->assertSessionHas('locale', 'pt_BR');
    }

    private function staff(): User
    {
        $tenant = Tenant::create(['name' => 'Produtora A', 'slug' => 'produtora-a', 'status' => 'active', 'subscription_expires_at' => now()->addYear()]);

        return User::create(['tenant_id' => $tenant->id, 'name' => 'Staff', 'email' => 'staff@ficticio.test', 'password' => 'secret12345', 'role' => 'admin']);
    }
}
