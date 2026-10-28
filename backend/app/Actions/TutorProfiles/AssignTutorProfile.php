<?php

namespace App\Actions\TutorProfiles;

use App\Models\TutorProfile;
use App\Models\User;
use App\Policies\TutorProfilePolicy;
use App\Support\AdminPermissions;
use App\Support\TutorProfileWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AssignTutorProfile
{
    /** @param array<string, mixed> $data */
    public function execute(User $actor, int $profileId, array $data): TutorProfile
    {
        $data = Validator::make($data, [
            'assigned_admin_id' => ['present', 'nullable', 'integer'],
            'assignment_version' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'min:2', 'max:2000', 'regex:/\\S/'],
        ])->validate();

        return DB::transaction(function () use ($actor, $profileId, $data): TutorProfile {
            $flow = app(TutorProfileWorkflow::class);
            [$actor, $profile] = $flow->lock($actor, $profileId, $data['assigned_admin_id']);
            $policy = app(TutorProfilePolicy::class);
            $flow->ensure($policy->permission($actor, AdminPermissions::ASSIGN_TUTOR_PROFILES), 403, 'permission_denied', 'Permission denied.');
            $flow->ensure((int) $profile->assignment_version === (int) $data['assignment_version'], 409, 'stale_assignment_version', 'Assignment has changed. Reload it before retrying.');
            if ($data['assigned_admin_id'] !== null) {
                $target = User::find($data['assigned_admin_id']);
                $flow->ensure($target !== null
                    && $policy->permission($target, AdminPermissions::VIEW_TUTOR_PROFILES)
                    && $policy->permission($target, AdminPermissions::DECIDE_TUTOR_PROFILES), 422, 'invalid_assigned_admin', 'The assigned Admin must be active and able to view and decide tutor profiles.');
            }
            $before = $flow->assignment($profile);
            $profile->forceFill([
                'assigned_admin_id' => $data['assigned_admin_id'], 'assigned_by' => $actor->id,
                'assigned_at' => now(), 'assignment_version' => $profile->assignment_version + 1,
            ])->save();
            $flow->audit($actor, 'tutor_profile.assignment', 'tutor_profile', $profile->id, $before, $flow->assignment($profile), $data['reason']);

            return $profile;
        }, 3);
    }
}
