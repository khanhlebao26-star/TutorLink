<?php

namespace App\Policies;

use App\Models\Program;
use App\Models\User;

class ProgramPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['tutor', 'customer'], true);
    }

    public function update(User $user, Program $program): bool
    {
        return $user->role === 'tutor'
            && $program->ownerTutorProfile()
                ->where('user_id', $user->id)
                ->exists();
    }

    public function create(User $user): bool
    {
        return $user->role === 'tutor' && $user->status === 'active';
    }
}
