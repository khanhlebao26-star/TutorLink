<?php

namespace App\Actions\TutorProfiles;

use App\Models\User;
use App\Models\VerificationDocument;
use App\Policies\TutorProfilePolicy;
use App\Support\AdminPermissions;
use App\Support\TutorProfileChecklist;
use App\Support\TutorProfileWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DecideVerificationDocument
{
    /** @param array<string, mixed> $data */
    public function execute(User $actor, int $documentId, bool $approve, array $data): VerificationDocument
    {
        $data = Validator::make($data, [
            'assignment_version' => ['required', 'integer', 'min:0'],
            'expected_version' => ['sometimes', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:2', 'max:2000', 'regex:/\\S/'],
        ])->validate();

        return DB::transaction(function () use ($actor, $documentId, $approve, $data): VerificationDocument {
            $flow = app(TutorProfileWorkflow::class);
            $context = VerificationDocument::findOrFail($documentId);
            [$actor, $profile] = $flow->lock($actor, $context->tutor_profile_id);
            $flow->ensure(app(TutorProfilePolicy::class)->permission($actor, AdminPermissions::DECIDE_VERIFICATION_DOCUMENTS), 403, 'permission_denied', 'Permission denied.');
            $flow->ensure((int) $profile->assigned_admin_id === $actor->id, 404, 'tutor_profile_not_found', 'Tutor profile not found.');
            $flow->ensure($profile->assignment_version === (int) $data['assignment_version'], 409, 'stale_assignment_version', 'Assignment has changed.');
            $flow->ensure($profile->status !== 'suspended', 409, 'profile_suspended', 'Suspended profiles cannot be reviewed.');
            $document = VerificationDocument::lockForUpdate()->findOrFail($documentId);
            $flow->ensure($document->status === 'pending', 409, 'invalid_document_transition', 'Only pending documents can be decided.');
            if ($approve) {
                $flow->ensure(app(TutorProfileChecklist::class)->validFile($document->file, $profile->user_id, 'verification_document')
                    && ($document->expires_at === null || $document->expires_at->isFuture()), 409, 'document_ineligible', 'Document must be complete and unexpired.');
            }
            $document->forceFill(['status' => $approve ? 'approved' : 'rejected', 'reviewed_by' => $actor->id,
                'reviewed_at' => now(), 'review_reason' => $data['reason']])->save();
            $flow->audit($actor, $approve ? 'verification_document.approve' : 'verification_document.reject', 'verification_document', $document->id,
                ['status' => 'pending'], ['status' => $document->status, 'tutor_profile_id' => $profile->id], $data['reason']);

            return $document;
        }, 3);
    }
}
