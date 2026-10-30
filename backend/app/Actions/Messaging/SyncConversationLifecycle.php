<?php

namespace App\Actions\Messaging;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use LogicException;

class SyncConversationLifecycle
{
    /**
     * Call at the end of a relationship-changing transaction.
     * The caller must already hold both User locks in ascending ID order
     * and the TutorProfile lock.
     */
    public function execute(
        int $tutorProfileId,
        int $customerId,
    ): bool {
        if (DB::transactionLevel() === 0) {
            throw new LogicException(
                'Conversation lifecycle requires an existing transaction.'
            );
        }

        $tutorUserId = DB::table('tutor_profiles')
            ->where('id', $tutorProfileId)
            ->value('user_id');

        if ($tutorUserId === null) {
            throw new LogicException('TutorProfile does not exist.');
        }

        $userIds = [(int) $tutorUserId, $customerId];
        sort($userIds, SORT_NUMERIC);

        $conversation = DB::table('conversations')
            ->where('type', 'direct')
            ->where('direct_key', implode(':', $userIds))
            ->lockForUpdate()
            ->first();

        if (
            $conversation === null
            || $conversation->status !== 'active'
        ) {
            return false;
        }

        $programs = DB::table('programs')
            ->where('owner_tutor_profile_id', $tutorProfileId)
            ->where('customer_id', $customerId);

        $hasCompletedProgram = (clone $programs)
            ->where('status', 'completed')
            ->exists();

        if (! $hasCompletedProgram) {
            return false;
        }

        $hasOngoingProgram = (clone $programs)
            ->whereIn('status', ['active', 'paused'])
            ->exists();

        if ($hasOngoingProgram) {
            return false;
        }

        $now = CarbonImmutable::now('UTC');
        $timestamp = $now->format('Y-m-d H:i:s.uP');

        $hasPendingInvitation = DB::table('program_invitations as invitations')
            ->join(
                'programs',
                'programs.id',
                '=',
                'invitations.program_id',
            )
            ->where(
                'programs.owner_tutor_profile_id',
                $tutorProfileId,
            )
            ->where('programs.customer_id', $customerId)
            ->where('invitations.invitee_user_id', $customerId)
            ->where('invitations.status', 'pending')
            ->where('invitations.expires_at', '>', $timestamp)
            ->exists();

        if ($hasPendingInvitation) {
            return false;
        }

        return DB::table('conversations')
            ->where('id', $conversation->id)
            ->where('status', 'active')
            ->update([
                'status' => 'cancelled',
                'ended_at' => $timestamp,
                'updated_at' => $timestamp,
            ]) === 1;
    }
}
