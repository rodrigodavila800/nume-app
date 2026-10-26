<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VideoType;

/**
 * Tipos de vídeo: a equipe vê (para escolher ao gerar roteiro); só o admin cria, edita e desativa.
 */
class VideoTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isEditor();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, VideoType $videoType): bool
    {
        return $user->tenant_id === $videoType->tenant_id && $user->isAdmin();
    }

    public function delete(User $user, VideoType $videoType): bool
    {
        return $user->tenant_id === $videoType->tenant_id && $user->isAdmin();
    }
}
