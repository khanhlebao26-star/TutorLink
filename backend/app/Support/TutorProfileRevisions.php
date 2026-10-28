<?php

namespace App\Support;

use App\Models\File;
use App\Models\TutorProfile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class TutorProfileRevisions
{
    /** @param array<string, mixed> $changes */
    public function save(TutorProfile $profile, array $changes, ?int $expectedVersion): void
    {
        $flow = app(TutorProfileWorkflow::class);
        $revision = DB::table('tutor_profile_revisions')->where('tutor_profile_id', $profile->id)
            ->orderByDesc('revision_no')->lockForUpdate()->first();
        $flow->ensure($revision?->status !== 'rejected', 409, 'revision_rejected', 'Rejected revisions cannot be self-resubmitted.');
        $flow->ensure($revision?->status !== 'submitted', 409, 'revision_locked', 'A submitted revision cannot be edited.');
        $isOpen = $revision !== null && in_array($revision->status, ['draft', 'changes_requested'], true);
        if ($isOpen) {
            $flow->ensure($expectedVersion !== null && $expectedVersion === (int) $revision->version, 409, 'stale_revision_version', 'The revision has changed. Reload it before editing.');
            $payload = json_decode($revision->payload, true, 512, JSON_THROW_ON_ERROR);
        } else {
            $flow->ensure($expectedVersion === null || $expectedVersion === 0, 409, 'stale_revision_version', 'There is no open revision matching this version.');
            $payload = [...$profile->only(['headline', 'bio', 'experience_years', 'avatar_file_id', 'qualification_claims']),
                'specialization_id' => $profile->specializations()->value('specializations.id')];
        }
        $payload = array_replace($payload, array_intersect_key($changes, array_flip([
            'headline', 'bio', 'experience_years', 'avatar_file_id', 'qualification_claims', 'specialization_id',
        ])));
        if (($payload['avatar_file_id'] ?? null) !== null) {
            $file = File::lockForUpdate()->find($payload['avatar_file_id']);
            $flow->ensure(app(TutorProfileChecklist::class)->validFile($file, $profile->user_id, 'avatar'), 422, 'invalid_revision_avatar', 'Revision avatar must be a complete file owned by the Tutor.');
        }
        if ($isOpen) {
            DB::table('tutor_profile_revisions')->where('id', $revision->id)->update([
                'avatar_file_id' => $payload['avatar_file_id'] ?? null,
                'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'version' => $revision->version + 1, 'updated_at' => now(),
            ]);
        } else {
            DB::table('tutor_profile_revisions')->insert([
                'tutor_profile_id' => $profile->id, 'revision_no' => ($revision?->revision_no ?? 0) + 1,
                'status' => 'draft', 'version' => 1, 'avatar_file_id' => $payload['avatar_file_id'] ?? null, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    /** @return array<string, mixed>|null */
    public function latest(TutorProfile $profile): ?array
    {
        $revision = DB::table('tutor_profile_revisions')->where('tutor_profile_id', $profile->id)->orderByDesc('revision_no')->first();
        if ($revision === null) {
            return null;
        }

        return ['id' => $revision->id, 'revision_no' => $revision->revision_no, 'status' => $revision->status,
            'version' => $revision->version, 'payload' => json_decode($revision->payload, true, 512, JSON_THROW_ON_ERROR),
            'submitted_at' => $revision->submitted_at ? CarbonImmutable::parse($revision->submitted_at)->toISOString() : null, 'reviewed_by' => $revision->reviewed_by,
            'reviewed_at' => $revision->reviewed_at ? CarbonImmutable::parse($revision->reviewed_at)->toISOString() : null, 'review_reason' => $revision->review_reason];
    }
}
