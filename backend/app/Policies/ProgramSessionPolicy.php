<?php

namespace App\Policies;

use App\Models\Program;
use App\Models\ProgramSession;
use App\Models\User;

class ProgramSessionPolicy
{
    public function viewAny(User $user, Program $program): bool
    {
        return $this->activeActor($user)
            && Program::query()
                ->visibleTo($user)
                ->whereKey($program->id)
                ->exists();
    }

    public function view(User $user, ProgramSession $session): bool
    {
        return $this->viewAny($user, $session->program);
    }

    public function mutate(User $user, Program $program): bool
    {
        return $this->activeActor($user)
            && $user->role === 'tutor'
            && $program->ownerTutorProfile()
                ->where('user_id', $user->id)
                ->exists();
    }

    public function create(User $user, Program $program): bool
    {
        return $this->mutate($user, $program)
            && $program->status === 'active';
    }

    public function update(User $user, ProgramSession $session): bool
    {
        return $this->mutate($user, $session->program)
            && $session->program->status === 'active'
            && $session->status === 'scheduled';
    }

    public function complete(User $user, ProgramSession $session): bool
    {
        return $this->mutate($user, $session->program)
            && in_array(
                $session->program->status,
                ['active', 'paused'],
                true,
            )
            && $session->status === 'scheduled';
    }

    public function cancel(User $user, ProgramSession $session): bool
    {
        return $this->complete($user, $session);
    }

    private function activeActor(User $user): bool
    {
        return $user->status === 'active'
            && $user->deleted_at === null;
    }
}
