<?php

namespace App\Policies;

use App\Models\User;

/**
 * Uso e limites mensais de IA: restrito ao admin da produtora.
 */
class AiUsageLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function updateLimits(User $user): bool
    {
        return $user->isAdmin();
    }
}
