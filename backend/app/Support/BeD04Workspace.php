<?php

namespace App\Support;

use App\Models\File;
use App\Models\Program;
use App\Models\TutorProfile;
use App\Models\User;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class BeD04Workspace
{
    public function visible(?User $actor, int $programId, string $requestId): Program
    {
        $this->requireActiveActor($actor, $requestId);

        $program = Program::query()
            ->visibleTo($actor)
            ->whereKey($programId)
            ->first();

        if ($program === null) {
            $this->fail(404, 'program_not_found', 'Program not found.', $requestId);
        }

        return $program;
    }

    public function owner(?User $actor, int $programId, string $requestId): Program
    {
        $program = $this->visible($actor, $programId, $requestId);

        if (
            $actor->role !== 'tutor'
            || ! $program->ownerTutorProfile()->where('user_id', $actor->id)->exists()
        ) {
            $this->fail(403, 'workspace_mutation_forbidden', 'Only the Tutor owner can change this resource.', $requestId);
        }

        return $program;
    }

    /**
     * Runs a database-only writer after locking actor/owner/customer Users in
     * ID order, then TutorProfile, Program, and its Customer membership.
     * The callback may next lock a resource, Files, FileLinks, receipt/audit/outbox.
     *
     * @template T
     *
     * @param  Closure(Program, User, object|null): T  $writer
     * @return T
     */
    public function withLockedOwnerProgram(
        User $actor,
        int $programId,
        string $requestId,
        Closure $writer,
    ): mixed {
        $this->owner($actor, $programId, $requestId);

        return DB::transaction(function () use ($actor, $programId, $requestId, $writer): mixed {
            $hint = DB::table('programs')
                ->join('tutor_profiles', 'tutor_profiles.id', '=', 'programs.owner_tutor_profile_id')
                ->where('programs.id', $programId)
                ->first([
                    'programs.owner_tutor_profile_id',
                    'programs.customer_id',
                    'tutor_profiles.user_id as owner_user_id',
                ]);

            if ($hint === null) {
                $this->fail(404, 'program_not_found', 'Program not found.', $requestId);
            }

            $userIds = array_values(array_unique([
                (int) $actor->id,
                (int) $hint->owner_user_id,
                (int) $hint->customer_id,
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
                $this->fail(403, 'account_inactive', 'This account is not available.', $requestId);
            }

            if ($lockedActor->role !== 'tutor') {
                $this->fail(403, 'workspace_mutation_forbidden', 'Only the Tutor owner can change this resource.', $requestId);
            }

            $profile = TutorProfile::query()
                ->whereKey($hint->owner_tutor_profile_id)
                ->lockForUpdate()
                ->first();

            $program = Program::query()
                ->whereKey($programId)
                ->lockForUpdate()
                ->first();

            if ($program === null) {
                $this->fail(404, 'program_not_found', 'Program not found.', $requestId);
            }

            if (
                $profile === null
                || (int) $profile->user_id !== (int) $hint->owner_user_id
                || (int) $program->owner_tutor_profile_id !== (int) $hint->owner_tutor_profile_id
                || (int) $program->customer_id !== (int) $hint->customer_id
            ) {
                $this->fail(409, 'program_state_conflict', 'Program access changed. Reload and retry.', $requestId);
            }

            if ((int) $profile->user_id !== (int) $lockedActor->id) {
                if (! Program::query()->visibleTo($lockedActor)->whereKey($programId)->exists()) {
                    $this->fail(404, 'program_not_found', 'Program not found.', $requestId);
                }

                $this->fail(403, 'workspace_mutation_forbidden', 'Only the Tutor owner can change this resource.', $requestId);
            }

            $member = DB::table('program_members')
                ->where('program_id', $programId)
                ->where('user_id', $program->customer_id)
                ->where('member_role', 'customer')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            return $writer($program, $lockedActor, $member);
        }, attempts: 3);
    }

    /** @param list<int> $fileIds
     * @return Collection<int, File>
     */
    public function lockFiles(array $fileIds): Collection
    {
        $this->assertWriteTransaction();
        $ids = array_values(array_unique(array_map('intval', $fileIds)));
        sort($ids, SORT_NUMERIC);

        return File::query()
            ->whereIn('id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /** @param list<int> $fileIds
     * @return Collection<int, object>
     */
    public function lockAnnouncementFileLinks(int $announcementId, array $fileIds): Collection
    {
        $this->assertWriteTransaction();
        $ids = array_values(array_unique(array_map('intval', $fileIds)));
        sort($ids, SORT_NUMERIC);

        return DB::table('file_links')
            ->where('resource_type', 'announcement')
            ->where('resource_id', $announcementId)
            ->whereIn('file_id', $ids)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    /** @param list<int> $fileIds
     * @return Collection<int, object>
     */
    public function lockFileLinksForFiles(array $fileIds): Collection
    {
        $this->assertWriteTransaction();
        $ids = array_values(array_unique(array_map('intval', $fileIds)));
        sort($ids, SORT_NUMERIC);

        return DB::table('file_links')
            ->whereIn('file_id', $ids)
            ->orderBy('file_id')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
    }

    private function assertWriteTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('D04 resource and file locks must share the writer transaction.');
        }
    }

    private function requireActiveActor(?User $actor, string $requestId): void
    {
        if ($actor === null) {
            $this->fail(401, 'unauthenticated', 'Authentication is required.', $requestId);
        }

        if ($actor->status !== 'active' || $actor->deleted_at !== null) {
            $this->fail(403, 'account_inactive', 'This account is not available.', $requestId);
        }
    }

    /** @param array<string, mixed> $errors */
    private function fail(int $status, string $code, string $message, string $requestId, array $errors = []): never
    {
        throw new HttpResponseException(ApiErrorResponse::make($status, $code, $message, $requestId, $errors));
    }
}
