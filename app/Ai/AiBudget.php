<?php

namespace App\Ai;

use App\Models\AiUsageLog;
use App\Models\TenantSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Orçamento mensal de tokens de IA por produtora (tenant) e por cliente do portal.
 *
 * Limite zero ou nulo = sem limite. Quando o consumo do mês corrente atinge o teto,
 * SofiProvider::options() devolve null e todos os serviços caem no motor heurístico.
 * Ao atingir 80% do limite da produtora no mês, registra aviso para o admin.
 */
class AiBudget
{
    public const TENANT_LIMIT_KEY = 'ai_monthly_token_limit';

    public const CLIENTE_LIMIT_KEY = 'ai_monthly_cliente_token_limit';

    public const WARNED_MONTH_KEY = 'ai_budget_warned_month';

    public function tenantLimit(?int $tenantId = null): ?int
    {
        if ($tenantId !== null) {
            $configured = TenantSetting::get($tenantId, self::TENANT_LIMIT_KEY);
            if ($configured !== null && $configured !== '') {
                $limit = (int) $configured;

                return $limit > 0 ? $limit : null;
            }
        }

        $limit = (int) config('sofi.budgets.monthly_tokens_per_tenant', 0);

        return $limit > 0 ? $limit : null;
    }

    public function clienteLimit(?int $tenantId = null): ?int
    {
        if ($tenantId !== null) {
            $configured = TenantSetting::get($tenantId, self::CLIENTE_LIMIT_KEY);
            if ($configured !== null && $configured !== '') {
                $limit = (int) $configured;

                return $limit > 0 ? $limit : null;
            }
        }

        $limit = (int) config('sofi.budgets.monthly_tokens_per_cliente', 0);

        return $limit > 0 ? $limit : null;
    }

    public function setLimits(int $tenantId, ?int $tenantLimit, ?int $clienteLimit): void
    {
        TenantSetting::set($tenantId, self::TENANT_LIMIT_KEY, $tenantLimit && $tenantLimit > 0 ? (string) $tenantLimit : null);
        TenantSetting::set($tenantId, self::CLIENTE_LIMIT_KEY, $clienteLimit && $clienteLimit > 0 ? (string) $clienteLimit : null);
    }

    /**
     * Tokens (entrada + saída) gastos no mês corrente pelo tenant (ou por um cliente daquele tenant).
     */
    public function tokensThisMonth(int $tenantId, ?int $clienteId = null): int
    {
        $query = AiUsageLog::forTenant($tenantId)
            ->where('created_at', '>=', now()->startOfMonth());

        if ($clienteId !== null) {
            $query->where('cliente_id', $clienteId);
        }

        return (int) $query->sum(DB::raw('input_tokens + output_tokens'));
    }

    /**
     * Confere se o tenant e (quando informado) o cliente ainda têm saldo de tokens no mês.
     */
    public function allows(?int $tenantId = null, ?int $clienteId = null): bool
    {
        if ($tenantId === null) {
            return true;
        }

        $tenantLimit = $this->tenantLimit($tenantId);
        if ($tenantLimit !== null && $this->tokensThisMonth($tenantId) >= $tenantLimit) {
            return false;
        }

        if ($clienteId !== null) {
            $clienteLimit = $this->clienteLimit($tenantId);
            if ($clienteLimit !== null && $this->tokensThisMonth($tenantId, $clienteId) >= $clienteLimit) {
                return false;
            }
        }

        return true;
    }

    /**
     * Resumo do orçamento mensal da produtora (com sinalização aos 80% e 100%).
     *
     * @return array{used: int, limit: ?int, cliente_limit: ?int, percent: int, at_warning: bool, exhausted: bool}
     */
    public function summary(int $tenantId): array
    {
        $used = $this->tokensThisMonth($tenantId);
        $limit = $this->tenantLimit($tenantId);
        $clienteLimit = $this->clienteLimit($tenantId);
        $percent = $limit !== null && $limit > 0 ? (int) round(($used / $limit) * 100) : 0;
        $warningThreshold = $limit !== null ? (int) ceil($limit * 0.8) : null;

        return [
            'used' => $used,
            'limit' => $limit,
            'cliente_limit' => $clienteLimit,
            'percent' => $percent,
            'at_warning' => $warningThreshold !== null && $used >= $warningThreshold,
            'exhausted' => $limit !== null && $used >= $limit,
        ];
    }

    /**
     * Registra aviso único no mês quando o consumo da produtora atinge 80% do limite.
     */
    public function checkThresholdAlert(?int $tenantId): void
    {
        if ($tenantId === null) {
            return;
        }

        $summary = $this->summary($tenantId);
        if (! $summary['at_warning']) {
            return;
        }

        $currentMonth = now()->format('Y-m');
        if (TenantSetting::get($tenantId, self::WARNED_MONTH_KEY) === $currentMonth) {
            return;
        }

        TenantSetting::set($tenantId, self::WARNED_MONTH_KEY, $currentMonth);

        Log::warning('sofi.budget.threshold_80_reached', [
            'tenant_id' => $tenantId,
            'used_tokens' => $summary['used'],
            'limit_tokens' => $summary['limit'],
            'percent' => $summary['percent'],
        ]);
    }
}
