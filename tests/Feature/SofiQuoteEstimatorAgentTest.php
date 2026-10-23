<?php

namespace Tests\Feature;

use App\Ai\Agents\SofiQuoteEstimatorAgent;
use App\Models\AiUsageLog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Quotes\SofiQuoteEstimatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Prompts\AgentPrompt;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\StructuredTextResponse;
use RuntimeException;
use Tests\TestCase;

/**
 * SofiQuoteEstimatorService no Laravel AI SDK. Nenhum teste chama a API real.
 */
class SofiQuoteEstimatorAgentTest extends TestCase
{
    use RefreshDatabase;

    private const TEXT = 'Quero 3 vídeos para redes da Bodega Aragón com imagens aéreas de drone no vinhedo, dia inteiro de gravação.';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config(['sofi.use_ai_sdk' => true, 'sofi.provider' => 'gemini', 'sofi.model' => null, 'sofi.keys.gemini' => 'chave-de-teste']);
    }

    public function test_structured_output_is_mapped_to_the_same_array_as_today(): void
    {
        SofiQuoteEstimatorAgent::fake([[
            'pedido' => [
                'cliente' => 'Bodega Aragón',
                'projeto' => '',
                'videos' => 3,
                'pre' => 2,
                'gravacao' => 8,
                'edicao' => 9,
                'motion' => '1.5',
                'filmmakers' => 0,
                'drone' => true,
            ],
            'resumo' => '3 vídeos com captação aérea.',
            'suposicoes' => ['Jornada completa de 8h.'],
            'duvidas' => ['Confirmar a data da vindima.'],
        ]])->preventStrayPrompts();

        $reading = app(SofiQuoteEstimatorService::class)->estimate(self::TEXT);

        // Mesma normalização do caminho antigo: projeto vazio vira null, mínimos aplicados.
        $this->assertSame([
            'pedido' => [
                'cliente' => 'Bodega Aragón',
                'projeto' => null,
                'videos' => 3,
                'pre' => 2.0,
                'gravacao' => 8.0,
                'edicao' => 9.0,
                'motion' => 1.5,
                'filmmakers' => 1,
                'drone' => true,
            ],
            'resumo' => '3 vídeos com captação aérea.',
            'suposicoes' => ['Jornada completa de 8h.'],
            'duvidas' => ['Confirmar a data da vindima.'],
        ], $reading);

        SofiQuoteEstimatorAgent::assertPrompted(self::TEXT);
    }

    public function test_provider_failure_falls_back_to_heuristic_without_exception(): void
    {
        SofiQuoteEstimatorAgent::fake([
            fn () => throw new RuntimeException('Falha simulada do provedor'),
        ])->preventStrayPrompts();

        $reading = app(SofiQuoteEstimatorService::class)->estimate(self::TEXT);

        $this->assertSame($this->heuristic(self::TEXT), $reading);
        SofiQuoteEstimatorAgent::assertPromptedTimes(1);
    }

    public function test_output_without_pedido_falls_back_to_heuristic(): void
    {
        SofiQuoteEstimatorAgent::fake([['resumo' => 'incompleto']])->preventStrayPrompts();

        $this->assertSame($this->heuristic(self::TEXT), app(SofiQuoteEstimatorService::class)->estimate(self::TEXT));
    }

    public function test_usage_is_recorded_on_the_right_tenant_for_two_tenants(): void
    {
        [$tenantA, $userA] = $this->tenantWithAdmin('a');
        [$tenantB, $userB] = $this->tenantWithAdmin('b');

        SofiQuoteEstimatorAgent::fake([
            $this->structured(100, 20),
            $this->structured(300, 60),
        ])->preventStrayPrompts();

        $this->actingAs($userA)->postJson(route('quotes.estimate'), ['description' => self::TEXT])
            ->assertOk()->assertJsonPath('reading.pedido.cliente', 'Bodega Aragón');
        $this->actingAs($userB)->postJson(route('quotes.estimate'), ['description' => self::TEXT])
            ->assertOk();

        $this->assertSame(2, AiUsageLog::count());

        $logA = AiUsageLog::forTenant($tenantA->id)->sole();
        $this->assertSame([SofiQuoteEstimatorAgent::class, 'gemini', 100, 20], [$logA->agent, $logA->provider, $logA->input_tokens, $logA->output_tokens]);

        $logB = AiUsageLog::forTenant($tenantB->id)->sole();
        $this->assertSame([300, 60], [$logB->input_tokens, $logB->output_tokens]);

        // Cada tenant no seu provider sob demanda, sem nada derivado da chave.
        SofiQuoteEstimatorAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->provider->name() === "sofi_gemini_t{$tenantA->id}");
        SofiQuoteEstimatorAgent::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->provider->name() === "sofi_gemini_t{$tenantB->id}");
    }

    public function test_switch_on_without_key_skips_agent(): void
    {
        config(['sofi.keys.gemini' => null]);

        SofiQuoteEstimatorAgent::fake()->preventStrayPrompts();

        $this->assertSame($this->heuristic(self::TEXT), app(SofiQuoteEstimatorService::class)->estimate(self::TEXT));
        SofiQuoteEstimatorAgent::assertNeverPrompted();
    }

    public function test_switch_off_never_calls_agent(): void
    {
        config(['sofi.use_ai_sdk' => false]);

        SofiQuoteEstimatorAgent::fake()->preventStrayPrompts();

        $this->assertSame($this->heuristic(self::TEXT), app(SofiQuoteEstimatorService::class)->estimate(self::TEXT));
        SofiQuoteEstimatorAgent::assertNeverPrompted();
        $this->assertSame(0, AiUsageLog::count());
    }

    private function heuristic(string $text): array
    {
        $service = app(SofiQuoteEstimatorService::class);

        return (fn () => $this->buildFallbackEstimate($text))->call($service);
    }

    private function structured(int $input, int $output): StructuredTextResponse
    {
        $data = [
            'pedido' => ['cliente' => 'Bodega Aragón', 'projeto' => 'Vídeos para redes', 'videos' => 3, 'pre' => 2, 'gravacao' => 8, 'edicao' => 9, 'motion' => 0, 'filmmakers' => 2, 'drone' => true],
            'resumo' => '3 vídeos com captação aérea.',
            'suposicoes' => [],
            'duvidas' => [],
        ];

        return new StructuredTextResponse($data, json_encode($data), new TextUsage($input, $output), new Meta);
    }

    /**
     * @return array{0: Tenant, 1: User}
     */
    private function tenantWithAdmin(string $suffix): array
    {
        $tenant = Tenant::create([
            'name' => "Produtora {$suffix}",
            'slug' => "produtora-{$suffix}",
            'status' => 'active',
            'subscription_expires_at' => now()->addYear(),
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => "Staff {$suffix}",
            'email' => "staff-{$suffix}@produtora.es",
            'password' => 'secret12345',
            'role' => 'admin',
        ]);

        return [$tenant, $user];
    }
}
