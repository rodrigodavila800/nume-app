<?php

namespace App\Policies;

use App\Models\Video;
use App\Models\User;

class VideoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isEditor();
    }

    /**
     * Determina se o usuário pode visualizar vídeo
     */
    public function view(User $user, Video $video): bool
    {
        return $user->tenant_id === $video->tenant_id;
    }

    /**
     * Determina se o usuário pode criar vídeo
     */
    public function create(User $user): bool
    {
        return $user->isEditor();
    }

    /**
     * Determina se o usuário pode editar vídeo
     */
    public function update(User $user, Video $video): bool
    {
        // Antes: "isAdmin() || mesmo tenant" liberava qualquer usuário do tenant, até viewer.
        return $user->tenant_id === $video->tenant_id && $user->isEditor();
    }

    /**
     * Determina se o usuário pode deletar vídeo
     */
    public function delete(User $user, Video $video): bool
    {
        return $user->tenant_id === $video->tenant_id && $user->isEditor();
    }
}
