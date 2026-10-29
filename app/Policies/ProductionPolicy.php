<?php

namespace App\Policies;

use App\Models\Production;
use App\Models\User;

class ProductionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isEditor();
    }

    /**
     * Determina se o usuário pode visualizar productions
     */
    public function view(User $user, Production $production): bool
    {
        return $user->tenant_id === $production->tenant_id;
    }

    /**
     * Determina se o usuário pode criar production
     */
    public function create(User $user): bool
    {
        return $user->isEditor();
    }

    /**
     * Determina se o usuário pode editar production
     */
    public function update(User $user, Production $production): bool
    {
        return (int) $user->tenant_id === (int) $production->tenant_id && $user->isEditor();
    }

    /**
     * Determina se o usuário pode deletar production
     */
    public function delete(User $user, Production $production): bool
    {
        return $user->tenant_id === $production->tenant_id && 
               ($user->isAdmin() || $user->id === $production->created_by);
    }
}
