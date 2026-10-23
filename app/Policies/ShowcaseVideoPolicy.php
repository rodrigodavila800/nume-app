<?php

namespace App\Policies;

use App\Models\ShowcaseVideo;
use App\Models\User;

class ShowcaseVideoPolicy
{
    public function viewAny(User $user): bool { return $user->isEditor(); }
    public function create(User $user): bool { return $user->isEditor(); }
    public function update(User $user, ShowcaseVideo $video): bool { return $user->isEditor() && $user->tenant_id === $video->tenant_id; }
    public function delete(User $user, ShowcaseVideo $video): bool { return $user->isEditor() && $user->tenant_id === $video->tenant_id; }
}
