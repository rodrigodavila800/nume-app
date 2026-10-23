<?php

namespace App\Ai\Middleware;

use App\Models\AiUsageLog;
use Closure;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\AiManager;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\PendingStep;
use Throwable;

/**
 * Grava o uso de tokens de cada step do agente em ai_usage_logs.
 *
 * O tenant vem do construtor (passado pelo agente), nunca de auth() nem do scope
 * BelongsToTenant: em fila, webhook e scheduler não há usuário logado.
 * Uma falha ao gravar nunca derruba a resposta da IA.
 */
class RecordAiUsage
{
    public function __construct(
        protected ?int $tenantId,
        protected string $agent,
    ) {}

    public function handle(PendingStep $step, Closure $next)
    {
        return $next($step)->then(function (StepResponse $response) use ($step) {
            try {
                AiUsageLog::create([
                    'tenant_id' => $this->tenantId,
                    'agent' => $this->agent,
                    'provider' => $this->driverFor($step->provider),
                    'model' => $response->meta->model ?? $step->model,
                    'input_tokens' => $response->usage->inputTokens,
                    'output_tokens' => $response->usage->outputTokens,
                    'cache_read_input_tokens' => $response->usage->cacheReadInputTokens ?? 0,
                    'cache_write_input_tokens' => $response->usage->cacheWriteInputTokens ?? 0,
                    'invocation_id' => $step->invocationId,
                ]);
            } catch (Throwable $e) {
                Log::warning('RecordAiUsage falhou ao gravar uso: '.get_class($e));
            }
        });
    }

    /**
     * Driver ('gemini', 'anthropic') em vez do nome sob demanda (sofi_<driver>_t<id>).
     */
    protected function driverFor(string $providerName): string
    {
        try {
            return app(AiManager::class)->textProvider($providerName)->driver();
        } catch (Throwable) {
            return $providerName;
        }
    }
}
