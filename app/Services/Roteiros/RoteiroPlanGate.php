<?php

namespace App\Services\Roteiros;

use App\Models\MarketingPlan;
use App\Models\Roteiro;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Regra do analista: roteiro só nasce depois do plano de marketing aprovado pelo cliente.
 * O admin pode liberar sem plano, com justificativa que fica gravada no roteiro.
 */
class RoteiroPlanGate
{
    /**
     * O cliente tem plano aprovado? Tenant explícito: também roda fora de request autenticado.
     */
    public function clienteHasApprovedPlan(int $tenantId, ?int $clienteId): bool
    {
        return $clienteId !== null && MarketingPlan::forTenant($tenantId)
            ->where('cliente_id', $clienteId)
            ->where('status', MarketingPlan::STATUS_APPROVED)
            ->exists();
    }

    /**
     * Dados de liberação para gravar no roteiro: [] quando há plano aprovado; com justificativa
     * quando um admin libera; ValidationException em qualquer outro caso.
     *
     * @return array{plan_override_reason?: string, plan_override_by?: int}
     *
     * @throws ValidationException
     */
    public function authorizeCreation(int $tenantId, ?int $clienteId, User $user, ?string $reason): array
    {
        if ($this->clienteHasApprovedPlan($tenantId, $clienteId)) {
            return [];
        }

        if (! $user->can('createWithoutPlan', Roteiro::class)) {
            throw ValidationException::withMessages([
                'plan_override_reason' => __('O cliente ainda não aprovou o plano de marketing. Só um admin pode criar roteiro antes disso.'),
            ]);
        }

        $reason = trim((string) $reason);
        if (mb_strlen($reason) < 10) {
            throw ValidationException::withMessages([
                'plan_override_reason' => __('Explique em pelo menos 10 caracteres por que este roteiro será criado sem plano aprovado.'),
            ]);
        }

        return ['plan_override_reason' => mb_substr($reason, 0, 1000), 'plan_override_by' => $user->id];
    }
}
