<?php

namespace App\Actions\Invitations;

use App\Actions\Messaging\SyncConversationLifecycle;
use App\Exceptions\ProgramActionException;
use App\Models\User;
use App\Support\InvitationTransitionWriter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ResolveInvitation
{
    /** @return array<string, mixed> */
    public function execute(User $actor, int $invitationId, string $action, ?string $reason, string $requestId): array
    {
        if (! in_array($action, ['reject', 'cancel'], true)) {
            throw new \InvalidArgumentException('Unsupported Invitation decision.');
        }

        return $this->resolve($actor, $invitationId, $action, $reason, $requestId)
            ?? throw new \LogicException('An Invitation decision must return a record.');
    }

    public function expire(int $invitationId, string $requestId): bool
    {
        return $this->resolve(null, $invitationId, 'expire', null, $requestId) !== null;
    }

    /** @return array<string, mixed>|null */
    private function resolve(?User $actor, int $invitationId, string $action, ?string $reason, string $requestId): ?array
    {
        return DB::transaction(function () use ($actor, $invitationId, $action, $reason, $requestId): ?array {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
            }

            $context = DB::table('program_invitations as invitations')
                ->join('programs', 'programs.id', '=', 'invitations.program_id')
                ->join('tutor_profiles', 'tutor_profiles.id', '=', 'programs.owner_tutor_profile_id')
                ->where('invitations.id', $invitationId)
                ->select([
                    'invitations.program_id', 'invitations.invitee_user_id',
                    'programs.customer_id', 'programs.owner_tutor_profile_id', 'programs.source_listing_id',
                    'tutor_profiles.user_id as tutor_user_id',
                ])->first();

            if ($context === null) {
                if ($action === 'expire') {
                    return null;
                }

                throw new ProgramActionException(404, 'invitation_not_found', 'Invitation not found.');
            }

            $expectedActorId = $action === 'reject' ? (int) $context->invitee_user_id : (int) $context->tutor_user_id;

            if ($actor !== null && $actor->id !== $expectedActorId) {
                throw new ProgramActionException(404, 'invitation_not_found', 'Invitation not found.');
            }

            $userIds = array_values(array_unique([(int) $context->customer_id, (int) $context->tutor_user_id]));
            sort($userIds, SORT_NUMERIC);
            $users = DB::table('users')->whereIn('id', $userIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $profile = DB::table('tutor_profiles')->where('id', $context->owner_tutor_profile_id)->lockForUpdate()->first();

            if ($context->source_listing_id !== null) {
                DB::table('service_listings')->where('id', $context->source_listing_id)->lockForUpdate()->first();
            }

            $program = DB::table('programs')->where('id', $context->program_id)->lockForUpdate()->first();
            $invitation = DB::table('program_invitations')->where('id', $invitationId)->lockForUpdate()->first();

            if ($program === null || $invitation === null || $profile === null
                || (int) $program->owner_tutor_profile_id !== (int) $profile->id
                || (int) $profile->user_id !== (int) $context->tutor_user_id
                || (int) $program->customer_id !== (int) $invitation->invitee_user_id
                || (int) $program->id !== (int) $invitation->program_id) {
                throw new ProgramActionException(409, 'invitation_context_changed', 'Invitation context has changed.');
            }

            if ($actor !== null) {
                $currentActor = $users->get($actor->id);
                $requiredRole = $action === 'reject' ? 'customer' : 'tutor';

                if ($currentActor === null || $currentActor->deleted_at !== null || $currentActor->role !== $requiredRole) {
                    throw new ProgramActionException(404, 'invitation_not_found', 'Invitation not found.');
                }

                if ($currentActor->status !== 'active') {
                    throw new ProgramActionException(403, 'account_inactive', 'This account is not available.');
                }
            }

            $now = CarbonImmutable::now('UTC');
            $isExpired = CarbonImmutable::parse($invitation->expires_at)->lte($now);

            if ($action === 'expire' && ($invitation->status !== 'pending' || ! $isExpired)) {
                return null;
            }

            if ($invitation->status !== 'pending') {
                throw new ProgramActionException(409, 'invitation_not_pending', 'Invitation is no longer pending.');
            }

            if ($program->status !== 'draft') {
                throw new ProgramActionException(409, 'program_not_draft', 'Program is not a Draft.');
            }

            if ($action !== 'expire' && $isExpired) {
                throw new ProgramActionException(409, 'invitation_expired', 'Invitation has expired.');
            }

            $writer = app(InvitationTransitionWriter::class);

            if ($action === 'expire') {
                $writer->expire($invitation, null, $requestId, $now, (int) $context->tutor_user_id);
            } elseif ($action === 'reject') {
                $writer->reject($invitation, $actor->id, (int) $context->tutor_user_id, $requestId, $now, $reason);
            } else {
                $writer->cancel($invitation, $actor->id, $requestId, $now, $reason);
            }

            app(SyncConversationLifecycle::class)->execute((int) $profile->id, (int) $program->customer_id);

            return (array) DB::table('program_invitations')->where('id', $invitationId)->first();
        }, 3);
    }
}
