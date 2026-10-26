<?php

namespace App\Policies;

use App\Models\Roteiro;
use App\Models\User;

class RoteiroPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isEditor();
    }

    /**
     * Determina se o usuário pode visualizar roteiro
     */
    public function view(User $user, Roteiro $roteiro): bool
    {
        return $user->tenant_id === $roteiro->tenant_id;
    }

    /**
     * Determina se o usuário pode criar roteiro
     */
    public function create(User $user): bool
    {
        return $user->isEditor();
    }

    /**
     * Criar roteiro antes de o cliente aprovar o plano de marketing (com justificativa).
     */
    public function createWithoutPlan(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determina se o usuário pode editar roteiro
     */
    public function update(User $user, Roteiro $roteiro): bool
    {
        return $user->tenant_id === $roteiro->tenant_id && 
               ($user->isAdmin() || $user->id === $roteiro->created_by);
    }

    /**
     * Determina se o usuário pode aprovar/rejeitar roteiro
     */
    public function approve(User $user, Roteiro $roteiro): bool
    {
        return $user->tenant_id === $roteiro->tenant_id && $user->isEditor();
    }

    /**
     * Determina se o usuário pode deletar roteiro
     */
    public function delete(User $user, Roteiro $roteiro): bool
    {
        return $user->tenant_id === $roteiro->tenant_id && 
               ($user->isAdmin() || $user->id === $roteiro->created_by);
    }
}
