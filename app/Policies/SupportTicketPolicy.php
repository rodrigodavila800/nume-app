<?php

namespace App\Policies;

use App\Models\SupportTicket;
use App\Models\User;

/**
 * Lado da equipe. O portal confere dono (tenant + cliente) no próprio controller.
 */
class SupportTicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isEditor();
    }

    public function view(User $user, SupportTicket $ticket): bool
    {
        return $user->tenant_id === $ticket->tenant_id && $user->isEditor();
    }

    public function update(User $user, SupportTicket $ticket): bool
    {
        return $this->view($user, $ticket);
    }
}
