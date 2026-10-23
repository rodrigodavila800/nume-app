<?php

namespace App\Policies;

use App\Models\Quote;
use App\Models\User;

class QuotePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isEditor();
    }

    public function view(User $user, Quote $quote): bool
    {
        return $user->tenant_id === $quote->tenant_id && $user->isEditor();
    }

    public function create(User $user): bool
    {
        return $user->isEditor();
    }

    public function update(User $user, Quote $quote): bool
    {
        return $user->tenant_id === $quote->tenant_id && $user->isEditor();
    }

    public function delete(User $user, Quote $quote): bool
    {
        return $user->tenant_id === $quote->tenant_id && $user->isAdmin();
    }

    public function send(User $user, Quote $quote): bool
    {
        return $user->tenant_id === $quote->tenant_id && $user->isEditor();
    }
}
