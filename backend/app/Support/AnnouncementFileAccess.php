<?php

namespace App\Support;

use App\Models\Announcement;
use App\Models\File;
use App\Models\Program;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

final class AnnouncementFileAccess
{
    private const ALLOWED_MIMES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    /**
     * Rechecks the current Program, Announcement, File and link under the same
     * lock order used by Announcement writers. Returns null for an unlinked file.
     */
    public function authorizeIfLinked(?User $actor, int $fileId, string $requestId): ?File
    {
        return DB::transaction(function () use ($actor, $fileId, $requestId): ?File {
            $linkHints = DB::table('file_links')
                ->where('file_id', $fileId)
                ->where('resource_type', 'announcement')
                ->orderBy('id')
                ->get();

            if ($linkHints->isEmpty()) {
                return null;
            }

            if (
                $actor === null
                || $linkHints->count() !== 1
                || $linkHints[0]->purpose !== 'announcement_attachment'
            ) {
                $this->notFound($requestId);
            }

            $announcementHint = DB::table('announcements')->where('id', $linkHints[0]->resource_id)->first();
            $programHint = $announcementHint === null
                ? null
                : DB::table('programs')->where('id', $announcementHint->program_id)->first();
            $profileHint = $programHint === null
                ? null
                : DB::table('tutor_profiles')->where('id', $programHint->owner_tutor_profile_id)->first();

            if ($announcementHint === null || $programHint === null || $profileHint === null) {
                $this->notFound($requestId);
            }

            $userIds = array_values(array_unique([
                (int) $actor->id,
                (int) $profileHint->user_id,
                (int) $programHint->customer_id,
            ]));
            sort($userIds, SORT_NUMERIC);

            $users = User::withTrashed()
                ->whereIn('id', $userIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $lockedActor = $users->get($actor->id);

            if (
                ! $lockedActor instanceof User
                || $lockedActor->status !== 'active'
                || $lockedActor->deleted_at !== null
            ) {
                throw new HttpResponseException(ApiErrorResponse::make(
                    403,
                    'account_inactive',
                    'This account is not available.',
                    $requestId,
                ));
            }

            $profile = TutorProfile::query()
                ->whereKey($programHint->owner_tutor_profile_id)
                ->lockForUpdate()
                ->first();
            $program = Program::query()
                ->whereKey($programHint->id)
                ->lockForUpdate()
                ->first();

            if (
                $profile === null
                || $program === null
                || (int) $profile->user_id !== (int) $profileHint->user_id
                || (int) $program->owner_tutor_profile_id !== (int) $profile->id
                || (int) $program->customer_id !== (int) $programHint->customer_id
            ) {
                $this->notFound($requestId);
            }

            $members = DB::table('program_members')
                ->where('program_id', $program->id)
                ->where('member_role', 'customer')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $isOwner = $lockedActor->role === 'tutor'
                && (int) $profile->user_id === (int) $lockedActor->id;
            $isCurrentMember = $lockedActor->role === 'customer'
                && $members->contains(fn (object $member): bool => (int) $member->user_id === (int) $lockedActor->id
                    && $member->status === 'active'
                );

            if (! $isOwner && ! $isCurrentMember) {
                $this->notFound($requestId);
            }

            $announcement = Announcement::query()
                ->whereKey($linkHints[0]->resource_id)
                ->lockForUpdate()
                ->first();

            if (
                $announcement === null
                || (int) $announcement->program_id !== (int) $program->id
                || (int) $announcement->author_id !== (int) $profile->user_id
                || ($announcement->published_at === null && ! $isOwner)
            ) {
                $this->notFound($requestId);
            }

            $file = File::query()->whereKey($fileId)->lockForUpdate()->first();
            $currentLinks = DB::table('file_links')
                ->where('file_id', $fileId)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $announcementLinks = $currentLinks->where('resource_type', 'announcement');

            if (
                $file === null
                || (int) $file->owner_id !== (int) $profile->user_id
                || $file->visibility !== 'private'
                || $file->purpose !== 'announcement_attachment'
                || ! $file->isComplete()
                || (int) $file->size_bytes < 1
                || (int) $file->size_bytes > 5_000_000
                || ! in_array($file->mime_type, self::ALLOWED_MIMES, true)
                || $currentLinks->count() !== 1
                || $announcementLinks->count() !== 1
                || (int) $announcementLinks->first()->resource_id !== (int) $announcement->id
                || $announcementLinks->first()->purpose !== 'announcement_attachment'
            ) {
                $this->notFound($requestId);
            }

            return $file;
        }, attempts: 3);
    }

    private function notFound(string $requestId): never
    {
        throw new HttpResponseException(ApiErrorResponse::make(
            404,
            'file_not_found',
            'File not found.',
            $requestId,
        ));
    }
}
