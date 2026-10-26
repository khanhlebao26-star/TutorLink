<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class TutorEligibility
{
    public function canManagePrograms(object $user, object $profile, CarbonImmutable $now): bool
    {
        if (
            $user->deleted_at !== null
            || $user->role !== 'tutor'
            || $user->status !== 'active'
            || $user->email_verified_at === null
            || $profile->status !== 'approved'
            || $profile->suspended_at !== null
        ) {
            return false;
        }

        return DB::table('verification_documents as documents')
            ->join('files', 'files.id', '=', 'documents.file_id')
            ->where('documents.tutor_profile_id', $profile->id)
            ->where('documents.status', 'approved')
            ->whereIn('documents.document_type', ['identity_card', 'passport'])
            ->where(function ($query) use ($now): void {
                $query->whereNull('documents.expires_at')
                    ->orWhere('documents.expires_at', '>', $now);
            })
            ->where('files.owner_id', $user->id)
            ->where('files.purpose', 'verification_document')
            ->where('files.scan_status', 'clean')
            ->exists();
    }
}
