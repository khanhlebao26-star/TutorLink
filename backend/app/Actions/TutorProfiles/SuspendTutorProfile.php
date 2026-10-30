<?php

namespace App\Actions\TutorProfiles;

use App\Actions\Auth\RevokeUserSessions;
use App\Models\TutorProfile;
use App\Models\User;
use App\Policies\TutorProfilePolicy;
use App\Support\AdminPermissions;
use App\Support\NotificationOutboxWriter;
use App\Support\TutorProfileWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SuspendTutorProfile
{
    public function execute(User $actor, int $profileId, bool $suspend, string $reason): TutorProfile
    {
        Validator::make(['reason' => $reason], [
            'reason' => ['required', 'string', 'min:2', 'max:2000', 'regex:/\\S/'],
        ])->validate();

        return DB::transaction(function () use ($actor, $profileId, $suspend, $reason): TutorProfile {
            $flow = app(TutorProfileWorkflow::class);
            [$actor, $profile] = $flow->lock($actor, $profileId);
            $policy = app(TutorProfilePolicy::class);
            $flow->ensure($policy->permission($actor, AdminPermissions::SUSPEND_TUTOR_PROFILES), 403, 'permission_denied', 'Permission denied.');
            $user = $profile->user;
            $flow->ensure($user !== null && $actor->id !== $user->id, 403, 'permission_denied', 'Permission denied.');
            $flow->ensure($suspend ? $profile->status !== 'suspended' : $profile->status === 'suspended', 409, 'invalid_profile_transition', 'The requested profile transition is not allowed.');
            if ($suspend && $user->status === 'active') {
                $flow->ensure($policy->permission($actor, AdminPermissions::SUSPEND_ACCOUNTS), 403, 'permission_denied', 'Suspending an active account also requires account suspension permission.');
                $beforeUser = $user->only(['status', 'suspended_at']);
                $user->forceFill(['status' => 'suspended', 'suspended_at' => now(), 'remember_token' => str()->random(60)])->save();
                $user->tokens()->delete();
                $flow->audit($actor, 'account_suspended', 'user', $user->id, $beforeUser, $user->only(['status', 'suspended_at']), $reason);
            }
            if ($suspend) {
                app(RevokeUserSessions::class)->handle($user);
            }
            $before = $profile->only(['status', 'suspended_at', 'suspension_reason', 'status_before_suspension']);
            $restoredStatus = $profile->status_before_suspension;
            if (! $suspend) {
                $flow->ensure(in_array($restoredStatus, ['draft', 'submitted', 'changes_requested', 'approved', 'rejected'], true), 409, 'missing_previous_profile_status', 'The previous profile state must be resolved before restoration.');
            }
            $profile->forceFill([
                'status_before_suspension' => $suspend ? $profile->status : null,
                'status' => $suspend ? 'suspended' : $restoredStatus,
                'suspended_at' => $suspend ? now() : null,
                'suspension_reason' => $suspend ? $reason : null,
            ])->save();
            $verb = $suspend ? 'suspend' : 'restore';
            $type = $suspend ? 'tutor_profile_suspended' : 'tutor_profile_restored';
            $auditId = $flow->audit($actor, $type, 'tutor_profile', $profile->id, $before, $profile->only(array_keys($before)), $reason);
            app(NotificationOutboxWriter::class)->record(
                $type,
                'tutor_profile',
                $profile->id,
                "profile:{$profile->id}:{$verb}:{$auditId}",
                [$profile->user_id],
                ['deep_link' => '/profile']
            );

            return $profile->refresh()->load('user');
        }, 3);
    }
}
