<?php

namespace App\Support;

use App\Models\User;

class AdminAuthorization
{
    public function allows(User $actor, string $permission): bool
    {
        return ! $actor->trashed() && $actor->status === 'active'
            && $actor->suspended_at === null && $actor->hasVerifiedEmail()
            && $actor->hasPermission($permission);
    }
}
