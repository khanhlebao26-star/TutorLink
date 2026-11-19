<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class TutorEligibility
{
    public function canManagePrograms(object $user, object $profile, CarbonImmutable $now): bool
    {
        return $this->ineligibilityReasons($user, $profile, $now) === [];
    }

    /** @return list<string> */
    public function ineligibilityReasons(object $user, ?object $profile, CarbonImmutable $now): array
    {
        $reasons = [];
        if ($user->deleted_at !== null || $user->role !== 'tutor' || $user->status !== 'active') {
            $reasons[] = 'Tài khoản gia sư cần đang hoạt động.';
        }
        if ($user->email_verified_at === null) {
            $reasons[] = 'Gia sư cần xác minh email tài khoản.';
        }
        if ($profile === null || $profile->status !== 'approved') {
            $reasons[] = 'Hồ sơ gia sư cần được quản trị viên phê duyệt.';
        }
        if ($profile !== null && $profile->suspended_at !== null) {
            $reasons[] = 'Hồ sơ gia sư đang bị đình chỉ.';
        }
        if ($reasons !== []) {
            return $reasons;
        }

        if (! config('tutor.verification_documents_required', true)) {
            return [];
        }

        $documents = DB::table('verification_documents as documents')
            ->join('files', 'files.id', '=', 'documents.file_id')
            ->where('documents.tutor_profile_id', $profile->id)
            ->where('documents.status', 'approved')
            ->where(function ($query) use ($now): void {
                $query->whereNull('documents.expires_at')
                    ->orWhere('documents.expires_at', '>', $now);
            })
            ->where('files.owner_id', $user->id)
            ->where('files.purpose', 'verification_document')
            ->where('files.scan_status', 'clean')
            ->where('files.visibility', 'private');

        if (! (clone $documents)->whereIn('documents.document_type', ['identity_card', 'passport'])->exists()) {
            $reasons[] = 'Gia sư cần có CCCD/CMND hoặc hộ chiếu đã được duyệt, còn hiệu lực và đã kiểm tra tệp thành công.';
        }

        $claims = $profile->qualification_claims ?? [];
        if (is_string($claims)) {
            $claims = json_decode($claims, true, 512, JSON_THROW_ON_ERROR);
        }

        foreach (collect($claims)->pluck('type')->unique() as $type) {
            if (! in_array($type, ['degree', 'certificate'], true) || ! (clone $documents)->where('documents.document_type', $type)->exists()) {
                $reasons[] = 'Gia sư cần có tài liệu được duyệt, còn hiệu lực cho từng loại bằng cấp/chứng chỉ đã khai báo.';
                break;
            }
        }

        return $reasons;
    }
}
