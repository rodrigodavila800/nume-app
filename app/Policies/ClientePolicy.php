<?php

namespace App\Policies;

use App\Models\Cliente;
use App\Models\User;

class ClientePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isEditor();
    }

    public function view(User $user, Cliente $cliente): bool
    {
        return $user->tenant_id === $cliente->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Cliente $cliente): bool
    {
        return $user->tenant_id === $cliente->tenant_id && $user->isAdmin();
    }

    public function delete(User $user, Cliente $cliente): bool
    {
        return $user->tenant_id === $cliente->tenant_id && $user->isAdmin();
    }
}
