<?php

namespace App\Policies;

use App\Models\Pack;
use App\Models\User;

class PackPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isEditor();
    }

    public function view(User $user, Pack $pack): bool
    {
        return $user->tenant_id === $pack->tenant_id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Pack $pack): bool
    {
        return $user->tenant_id === $pack->tenant_id && $user->isAdmin();
    }

    public function delete(User $user, Pack $pack): bool
    {
        return $user->tenant_id === $pack->tenant_id && $user->isAdmin();
    }
}
