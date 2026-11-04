<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Services\SofiSalesConsultantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Voz comercial da Sofi (vitrine e WhatsApp): assistente virtual, recomenda direto, sem currículo
 * inventado nem estatística com fonte. Dados fictícios.
 */
class SofiSalesPersonaTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sales_voice_is_an_honest_assistant_that_recommends_directly(): void
    {
        $tenant = Tenant::create(['name' => 'Produtora Alpha', 'slug' => 'produtora-alpha']);

        foreach ([false, true] as $isSpanish) {
            $prompt = implode("\n", app(SofiSalesConsultantService::class)->salesPromptParts($tenant, $isSpanish, 'Qual pacote é ideal?'));

            $this->assertStringContainsString($isSpanish ? 'una IA, no una persona' : 'uma IA, não uma pessoa', $prompt);
            $this->assertStringContainsString($isSpanish ? 'recomienda YA el paquete' : 'recomende JÁ o pacote', $prompt);
            $this->assertStringNotContainsString('ex-diretora', $prompt);
            $this->assertStringNotContainsString('ex-directora', $prompt);
            $this->assertStringNotContainsString('Wyzowl', $prompt);
            $this->assertStringNotContainsString($isSpanish ? 'PUEDES CITAR' : 'PODE CITAR', $prompt);
            $this->assertStringNotContainsString($isSpanish ? 'OBLIGATORIO — usa estos datos' : 'OBRIGATÓRIO — use esses dados', $prompt);
        }
    }
}
