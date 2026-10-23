<?php

namespace Tests\Feature;

use App\Ai\Agents\SofiTranslatorAgent;
use App\Services\SofiTranslatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Prompts\AgentPrompt;
use RuntimeException;
use Tests\TestCase;

/**
 * Piloto do Laravel AI SDK no SofiTranslatorService.
 * Nenhum teste chama a API real: o agente usa o fake do SDK e o HTTP bloqueia requisições não simuladas.
 */
class SofiTranslatorAgentTest extends TestCase
{
    use RefreshDatabase;

    /** Fora do lang/es.json e com termos do fallback léxico (roteiro, produção). */
    private const TEXT = 'Nosso roteiro de produção, texto fora do dicionário';

    private const LEXICAL_FALLBACK = 'Nosso guion de producción, texto fora do dicionário';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Http::preventStrayRequests();
    }

    public function test_switch_on_with_key_returns_agent_response(): void
    {
        config(['sofi.use_ai_sdk' => true, 'sofi.provider' => 'gemini', 'sofi.keys.gemini' => 'chave-de-teste']);

        SofiTranslatorAgent::fake(['Hola mundo'])->preventStrayPrompts();

        $translated = app(SofiTranslatorService::class)->translate('Olá mundo, texto fora do dicionário', 'es');

        $this->assertSame('Hola mundo', $translated);
        SofiTranslatorAgent::assertPrompted('Olá mundo, texto fora do dicionário');
    }

    public function test_configured_model_and_timeout_reach_the_sdk(): void
    {
        config([
            'sofi.use_ai_sdk' => true,
            'sofi.provider' => 'anthropic',
            'sofi.model' => 'claude-haiku-5-5',
            'sofi.timeout' => 12,
            'sofi.keys.anthropic' => 'chave-de-teste',
        ]);

        SofiTranslatorAgent::fake(['Nuestro guion'])->preventStrayPrompts();

        app(SofiTranslatorService::class)->translate(self::TEXT, 'es');

        SofiTranslatorAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->model === 'claude-haiku-5-5'
            && $prompt->timeout === 12
            && $prompt->provider->name() === 'sofi_anthropic');
    }

    public function test_agent_failure_falls_back_to_lexical_translation(): void
    {
        config(['sofi.use_ai_sdk' => true, 'sofi.provider' => 'gemini', 'sofi.keys.gemini' => 'chave-de-teste']);

        SofiTranslatorAgent::fake([
            fn () => throw new RuntimeException('Falha simulada do provedor'),
        ])->preventStrayPrompts();

        $translated = app(SofiTranslatorService::class)->translate(self::TEXT, 'es');

        $this->assertSame(self::LEXICAL_FALLBACK, $translated);
        SofiTranslatorAgent::assertPromptedTimes(1);
    }

    public function test_switch_on_without_key_skips_agent_and_falls_back(): void
    {
        config(['sofi.use_ai_sdk' => true, 'sofi.provider' => 'gemini', 'sofi.keys.gemini' => null]);

        SofiTranslatorAgent::fake()->preventStrayPrompts();

        $translated = app(SofiTranslatorService::class)->translate(self::TEXT, 'es');

        $this->assertSame(self::LEXICAL_FALLBACK, $translated);
        SofiTranslatorAgent::assertNeverPrompted();
    }

    public function test_switch_off_never_calls_agent(): void
    {
        config(['sofi.use_ai_sdk' => false, 'sofi.keys.gemini' => 'chave-de-teste']);

        SofiTranslatorAgent::fake()->preventStrayPrompts();

        $translated = app(SofiTranslatorService::class)->translate(self::TEXT, 'es');

        $this->assertSame(self::LEXICAL_FALLBACK, $translated);
        SofiTranslatorAgent::assertNeverPrompted();
    }
}
