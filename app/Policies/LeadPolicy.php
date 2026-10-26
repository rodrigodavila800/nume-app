<?php

namespace App\Policies;

use App\Models\Lead;
use App\Models\User;

class LeadPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isEditor();
    }

    public function view(User $user, Lead $lead): bool
    {
        return $user->tenant_id === $lead->tenant_id && $user->isEditor();
    }

    public function create(User $user): bool
    {
        return $user->isEditor();
    }

    public function update(User $user, Lead $lead): bool
    {
        return $user->tenant_id === $lead->tenant_id && $user->isEditor() && $lead->anonymized_at === null;
    }

    /**
     * Exportar e anonimizar dados pessoais (RGPD): só admin.
     */
    public function managePersonalData(User $user, Lead $lead): bool
    {
        return $user->tenant_id === $lead->tenant_id && $user->isAdmin();
    }
}
