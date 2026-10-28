<?php

namespace App\Policies;

use App\Models\TutorProfile;
use App\Models\User;
use App\Support\AdminPermissions;

class TutorProfilePolicy
{
    public function permission(User $actor, string $permission): bool
    {
        return $actor->status === 'active' && $actor->hasPermission($permission);
    }

    public function view(User $actor, TutorProfile $profile): bool
    {
        return $this->permission($actor, AdminPermissions::VIEW_TUTOR_PROFILES)
            && (int) $profile->assigned_admin_id === $actor->id;
    }

    public function decide(User $actor, TutorProfile $profile): bool
    {
        return $this->permission($actor, AdminPermissions::DECIDE_TUTOR_PROFILES)
            && (int) $profile->assigned_admin_id === $actor->id;
    }

    public function download(User $actor, TutorProfile $profile): bool
    {
        return $this->view($actor, $profile)
            && $this->permission($actor, AdminPermissions::DOWNLOAD_VERIFICATION_DOCUMENTS);
    }

    public function decideDocument(User $actor, TutorProfile $profile): bool
    {
        return $this->permission($actor, AdminPermissions::DECIDE_VERIFICATION_DOCUMENTS)
            && (int) $profile->assigned_admin_id === $actor->id;
    }
}
