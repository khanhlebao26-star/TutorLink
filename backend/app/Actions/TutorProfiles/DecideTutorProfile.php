<?php

namespace App\Actions\TutorProfiles;

use App\Models\TutorProfile;
use App\Models\User;
use App\Policies\TutorProfilePolicy;
use App\Support\AdminPermissions;
use App\Support\NotificationOutboxWriter;
use App\Support\TutorProfileChecklist;
use App\Support\TutorProfileWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DecideTutorProfile
{
    /** @param array<string, mixed> $data */
    public function execute(User $actor, int $profileId, string $status, array $data): TutorProfile
    {
        $data = Validator::make($data, [
            'assignment_version' => ['required', 'integer', 'min:0'],
            'expected_version' => ['sometimes', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:2', 'max:2000', 'regex:/\\S/'],
        ])->validate();

        return DB::transaction(function () use ($actor, $profileId, $status, $data): TutorProfile {
            $flow = app(TutorProfileWorkflow::class);
            [$actor, $profile] = $flow->lock($actor, $profileId);
            $flow->ensure(app(TutorProfilePolicy::class)->permission($actor, AdminPermissions::DECIDE_TUTOR_PROFILES), 403, 'permission_denied', 'Permission denied.');
            $flow->ensure((int) $profile->assigned_admin_id === $actor->id, 404, 'tutor_profile_not_found', 'Tutor profile not found.');
            $flow->ensure((int) $profile->assignment_version === (int) $data['assignment_version'], 409, 'stale_assignment_version', 'Assignment has changed. Reload the profile.');
            $flow->ensure(in_array($status, ['approved', 'changes_requested', 'rejected'], true), 409, 'invalid_profile_transition', 'Invalid decision.');
            $flow->ensure($profile->user !== null && $profile->user->status === 'active' && $profile->user->hasVerifiedEmail(), 409, 'tutor_ineligible', 'Tutor account must be active and verified.');
            $revision = $profile->status === 'approved'
                ? DB::table('tutor_profile_revisions')->where('tutor_profile_id', $profile->id)->where('status', 'submitted')->lockForUpdate()->first()
                : null;
            $flow->ensure($profile->status === 'submitted' || $revision !== null, 409, 'invalid_profile_transition', 'Only submitted profiles or revisions can be decided.');
            $payload = $revision ? json_decode($revision->payload, true, 512, JSON_THROW_ON_ERROR) : null;
            if ($revision !== null) {
                $flow->ensure(isset($data['expected_version']) && (int) $data['expected_version'] === (int) $revision->version, 409, 'stale_revision_version', 'The revision has changed. Reload it before deciding.');
            }
            if ($status === 'approved') {
                app(TutorProfileChecklist::class)->validate($profile, true, $payload);
            }
            $before = ['status' => $profile->status, 'revision_id' => $revision?->id, 'revision_version' => $revision?->version];
            if ($revision !== null) {
                DB::table('tutor_profile_revisions')->where('id', $revision->id)->update([
                    'status' => $status, 'version' => $revision->version + 1, 'reviewed_by' => $actor->id,
                    'reviewed_at' => now(), 'review_reason' => $data['reason'], 'updated_at' => now(),
                ]);
                if ($status === 'approved') {
                    $specialization = $payload['specialization_id'];
                    unset($payload['specialization_id']);
                    $profile->forceFill($payload)->save();
                    $profile->specializations()->sync([$specialization]);
                }
            } else {
                $profile->forceFill(['status' => $status, 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'review_reason' => $data['reason']])->save();
            }
            $action = 'tutor_profile_'.$status;
            $verb = match ($status) {
                'approved' => 'approve', 'changes_requested' => 'changes-requested', 'rejected' => 'reject'
            };
            $auditId = $flow->audit($actor, $action, 'tutor_profile', $profile->id, $before,
                ['status' => $profile->status, 'revision_id' => $revision?->id, 'decision' => $status], $data['reason']);
            app(NotificationOutboxWriter::class)->record($action, 'tutor_profile', $profile->id,
                "profile:{$profile->id}:{$verb}:{$auditId}", [$profile->user_id], ['deep_link' => '/profile']);

            return $profile->refresh()->load('user');
        }, 3);
    }
}
