<?php

namespace App\Actions\TutorProfiles;

use App\Models\TutorProfile;
use App\Models\User;
use App\Policies\TutorProfilePolicy;
use App\Support\AdminPermissions;
use App\Support\TutorProfileChecklist;
use App\Support\TutorProfileWorkflow;
use Illuminate\Support\Facades\DB;

class SubmitTutorProfile
{
    public function execute(User $actor, int $profileId): TutorProfile
    {
        return DB::transaction(function () use ($actor, $profileId): TutorProfile {
            $flow = app(TutorProfileWorkflow::class);
            [$actor, $profile] = $flow->lock($actor, $profileId);
            $flow->ensure($actor->role === 'tutor' && $profile->user_id === $actor->id && $actor->hasVerifiedEmail(), 403, 'permission_denied', 'Only the active verified Tutor owner can submit.');
            $revision = $profile->status === 'approved'
                ? DB::table('tutor_profile_revisions')->where('tutor_profile_id', $profile->id)->orderByDesc('revision_no')->lockForUpdate()->first()
                : null;
            if ($profile->status === 'submitted' || $revision?->status === 'submitted') {
                return $profile;
            }
            $flow->ensure($profile->status !== 'suspended', 403, 'profile_suspended', 'Suspended profiles cannot be submitted.');
            $flow->ensure(in_array($profile->status, ['draft', 'changes_requested'], true)
                || ($profile->status === 'approved' && $revision !== null && in_array($revision->status, ['draft', 'changes_requested'], true)),
                409, 'invalid_profile_transition', 'Only drafts or changes-requested profiles or revisions can be submitted.');
            $flow->ensure($actor->status === 'active', 403, 'permission_denied', 'This account is not active.');
            app(TutorProfileChecklist::class)->validate($profile, false, $revision ? json_decode($revision->payload, true, 512, JSON_THROW_ON_ERROR) : null);
            $policy = app(TutorProfilePolicy::class);
            $assigned = $profile->assigned_admin_id ? User::find($profile->assigned_admin_id) : null;
            if ($profile->assigned_admin_id !== null && ($assigned === null
                || ! $policy->permission($assigned, AdminPermissions::VIEW_TUTOR_PROFILES)
                || ! $policy->permission($assigned, AdminPermissions::DECIDE_TUTOR_PROFILES))) {
                $before = $flow->assignment($profile);
                $profile->forceFill(['assigned_admin_id' => null, 'assigned_by' => null, 'assigned_at' => null,
                    'assignment_version' => $profile->assignment_version + 1])->save();
                $flow->audit($actor, 'tutor_profile.assignment', 'tutor_profile', $profile->id, $before, $flow->assignment($profile), 'Assigned reviewer is no longer eligible.');
            }
            if ($revision !== null) {
                DB::table('tutor_profile_revisions')->where('id', $revision->id)->update([
                    'status' => 'submitted', 'version' => $revision->version + 1, 'submitted_at' => now(),
                    'reviewed_by' => null, 'reviewed_at' => null, 'review_reason' => null, 'updated_at' => now(),
                ]);
            } else {
                $profile->forceFill(['status' => 'submitted', 'submitted_at' => now(), 'review_reason' => null,
                    'reviewed_by' => null, 'reviewed_at' => null])->save();
            }
            $flow->audit($actor, 'tutor_profile.submit', 'tutor_profile', $profile->id, [],
                ['status' => 'submitted', 'revision_id' => $revision?->id]);

            return $profile->refresh();
        }, 3);
    }
}
