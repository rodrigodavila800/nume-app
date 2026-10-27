<?php

namespace App\Ai;

use App\Models\AiUsageLog;
use Illuminate\Support\Facades\DB;

/**
 * Orçamento mensal de tokens de IA por produtora (tenant) e por cliente do portal.
 *
 * Limite zero ou nulo = sem limite. Quando o consumo do mês corrente atinge o teto,
 * SofiProvider::options() devolve null e todos os serviços caem no motor heurístico.
 */
class AiBudget
{
    public function tenantLimit(?int $tenantId = null): ?int
    {
        $limit = (int) config('sofi.budgets.monthly_tokens_per_tenant', 0);

        return $limit > 0 ? $limit : null;
    }

    public function clienteLimit(?int $clienteId = null): ?int
    {
        $limit = (int) config('sofi.budgets.monthly_tokens_per_cliente', 0);

        return $limit > 0 ? $limit : null;
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
            $clienteLimit = $this->clienteLimit($clienteId);
            if ($clienteLimit !== null && $this->tokensThisMonth($tenantId, $clienteId) >= $clienteLimit) {
                return false;
            }
        }

        return true;
    }
}
