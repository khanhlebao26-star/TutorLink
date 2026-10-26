<?php

namespace App\Actions\Invitations;

use App\Exceptions\InvitationAcceptException;
use App\Models\User;
use App\Support\NotificationOutboxWriter;
use App\Support\TutorEligibility;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AcceptInvitation
{
    /**
     * @return array{program: array<string, mixed>, invitation: array<string, mixed>}
     */
    public function execute(
        User $actor,
        int $invitationId,
        string $idempotencyKey,
        string $requestId,
    ): array {
        $method = 'POST';
        $canonicalPath = "/api/v1/invitations/{$invitationId}/accept";
        $requestHash = hash('sha256', $method."\n".$canonicalPath."\n{}");
        $idempotencyKey = strtolower($idempotencyKey);

        return DB::transaction(function () use (
            $actor,
            $canonicalPath,
            $idempotencyKey,
            $invitationId,
            $method,
            $requestHash,
            $requestId,
        ): array {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
            }

            $context = DB::table('program_invitations as invitations')
                ->join('programs', 'programs.id', '=', 'invitations.program_id')
                ->join('tutor_profiles', 'tutor_profiles.id', '=', 'programs.owner_tutor_profile_id')
                ->where('invitations.id', $invitationId)
                ->select([
                    'invitations.program_id',
                    'invitations.invitee_user_id',
                    'programs.customer_id',
                    'programs.owner_tutor_profile_id',
                    'programs.source_listing_id',
                    'tutor_profiles.user_id as tutor_user_id',
                ])
                ->first();

            if ($context === null || (int) $context->invitee_user_id !== $actor->id) {
                throw $this->failure(404, 'invitation_not_found', 'Invitation not found.');
            }

            $userIds = array_values(array_unique([$actor->id, (int) $context->tutor_user_id]));
            sort($userIds, SORT_NUMERIC);

            $users = DB::table('users')
                ->whereIn('id', $userIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $customer = $users->get($actor->id);
            $tutorUser = $users->get((int) $context->tutor_user_id);

            $profile = DB::table('tutor_profiles')
                ->where('id', $context->owner_tutor_profile_id)
                ->lockForUpdate()
                ->first();

            $listing = null;

            if ($context->source_listing_id !== null) {
                $listing = DB::table('service_listings')
                    ->where('id', $context->source_listing_id)
                    ->lockForUpdate()
                    ->first();
            }

            $program = DB::table('programs')
                ->where('id', $context->program_id)
                ->lockForUpdate()
                ->first();

            $invitation = DB::table('program_invitations')
                ->where('id', $invitationId)
                ->lockForUpdate()
                ->first();

            if (
                $customer === null
                || $profile === null
                || $tutorUser === null
                || $program === null
                || $invitation === null
                || $customer->deleted_at !== null
                || $customer->role !== 'customer'
                || $customer->status !== 'active'
                || (int) $invitation->invitee_user_id !== $actor->id
                || (int) $program->customer_id !== $actor->id
                || (int) $invitation->program_id !== (int) $program->id
                || (int) $profile->id !== (int) $program->owner_tutor_profile_id
                || (int) $profile->user_id !== (int) $tutorUser->id
                || (int) $invitation->invited_by !== (int) $tutorUser->id
            ) {
                throw $this->failure(404, 'invitation_not_found', 'Invitation not found.');
            }

            $now = now('UTC')->toImmutable();
            $receipt = DB::table('idempotency_receipts')
                ->where('actor_id', $actor->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($receipt !== null) {
                if (
                    $receipt->expires_at !== null
                    && CarbonImmutable::parse($receipt->expires_at)->lte($now)
                ) {
                    DB::table('idempotency_receipts')->where('id', $receipt->id)->delete();
                    $receipt = null;
                } elseif (
                    $receipt->method !== $method
                    || $receipt->canonical_path !== $canonicalPath
                    || ! hash_equals($receipt->request_hash, $requestHash)
                ) {
                    throw $this->failure(
                        409,
                        'idempotency_key_reused',
                        'Idempotency-Key was already used for a different request.'
                    );
                } else {
                    $snapshot = json_decode($receipt->response_snapshot, true, 512, JSON_THROW_ON_ERROR);

                    if (! is_array($snapshot)) {
                        throw new \LogicException('Stored invitation response snapshot is invalid.');
                    }

                    return $snapshot;
                }
            }

            if (! app(TutorEligibility::class)->canManagePrograms($tutorUser, $profile, $now)) {
                throw $this->failure(409, 'tutor_ineligible', 'Tutor is no longer eligible for this Program.');
            }

            if ($program->status !== 'draft') {
                throw $this->failure(409, 'program_not_draft', 'Program is not a Draft.');
            }

            if ($invitation->status !== 'pending') {
                throw $this->failure(409, 'invitation_not_pending', 'Invitation is no longer pending.');
            }

            if ((int) $invitation->program_version !== (int) $program->version) {
                throw $this->failure(409, 'invitation_version_stale', 'Invitation no longer matches the current Program version.');
            }

            if (CarbonImmutable::parse($invitation->expires_at)->lte($now)) {
                throw $this->failure(409, 'invitation_expired', 'Invitation has expired.');
            }

            if (
                $program->source_listing_id !== null
                && ($listing === null || DB::table('listing_moderation_holds')
                    ->where('listing_id', $listing->id)
                    ->whereNull('restored_at')
                    ->exists())
            ) {
                throw $this->failure(409, 'listing_moderation_hold', 'The source Listing is under moderation hold.');
            }

            $offerSnapshot = json_decode($invitation->offer_snapshot, true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($offerSnapshot)) {
                throw $this->failure(409, 'invitation_snapshot_invalid', 'Invitation snapshot does not match this Program.');
            }

            $snapshotSourceListingId = $offerSnapshot['source_listing_id'] ?? null;
            $snapshotSourceListingId = $snapshotSourceListingId === null
                ? null
                : (int) $snapshotSourceListingId;
            $programSourceListingId = $program->source_listing_id === null
                ? null
                : (int) $program->source_listing_id;

            if (
                (int) ($offerSnapshot['program_id'] ?? 0) !== (int) $program->id
                || (int) ($offerSnapshot['target_customer_id'] ?? 0) !== (int) $program->customer_id
                || $snapshotSourceListingId !== $programSourceListingId
                || ! is_array($offerSnapshot['offer'] ?? null)
            ) {
                throw $this->failure(409, 'invitation_snapshot_invalid', 'Invitation snapshot does not match this Program.');
            }

            if (DB::table('program_members')->where('program_id', $program->id)->exists()) {
                throw $this->failure(409, 'program_membership_conflict', 'Program already has membership records.');
            }

            $timestamp = $now->format('Y-m-d H:i:s.uP');

            DB::table('program_invitations')
                ->where('id', $invitation->id)
                ->update([
                    'status' => 'accepted',
                    'responded_at' => $timestamp,
                    'accepted_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);

            DB::table('programs')
                ->where('id', $program->id)
                ->update([
                    'status' => 'active',
                    'activated_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);

            $membershipDefaults = [
                'program_id' => $program->id,
                'status' => 'active',
                'joined_at' => $timestamp,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ];

            DB::table('program_members')->insert([
                array_merge($membershipDefaults, [
                    'user_id' => $program->customer_id,
                    'member_role' => 'customer',
                ]),
                array_merge($membershipDefaults, [
                    'user_id' => $tutorUser->id,
                    'member_role' => 'tutor',
                ]),
            ]);

            DB::table('audit_logs')->insert([
                'actor_id' => $actor->id,
                'action' => 'invitation.accept',
                'resource_type' => 'program_invitation',
                'resource_id' => $invitation->id,
                'before' => json_encode([
                    'invitation' => ['status' => 'pending'],
                    'program' => ['status' => 'draft'],
                ], JSON_THROW_ON_ERROR),
                'after' => json_encode([
                    'invitation' => [
                        'status' => 'accepted',
                        'accepted_at' => $this->isoTimestamp($now),
                    ],
                    'program' => [
                        'status' => 'active',
                        'activated_at' => $this->isoTimestamp($now),
                    ],
                ], JSON_THROW_ON_ERROR),
                'request_id' => $requestId,
                'created_at' => $timestamp,
            ]);

            app(NotificationOutboxWriter::class)->record(
                'invitation_accepted',
                'program_invitation',
                (int) $invitation->id,
                "invitation:{$invitation->id}:accepted",
                [(int) $tutorUser->id],
                [
                    'deep_link' => "/programs/{$program->id}",
                    'program_id' => (int) $program->id,
                ],
            );

            $response = [
                'program' => $this->programDto($program, $now),
                'invitation' => $this->invitationDto($invitation, $program, $offerSnapshot, $now),
            ];

            DB::table('idempotency_receipts')->insert([
                'id' => (string) Str::uuid(),
                'actor_id' => $actor->id,
                'idempotency_key' => $idempotencyKey,
                'method' => $method,
                'canonical_path' => $canonicalPath,
                'request_hash' => $requestHash,
                'response_status' => 200,
                'response_snapshot' => json_encode($response, JSON_THROW_ON_ERROR),
                'created_at' => $timestamp,
                'expires_at' => $now->addDays(30)->format('Y-m-d H:i:s.uP'),
            ]);

            return $response;
        }, 3);
    }

    /** @return array<string, mixed> */
    private function programDto(object $program, CarbonImmutable $now): array
    {
        return [
            'id' => (int) $program->id,
            'owner_tutor_profile_id' => (int) $program->owner_tutor_profile_id,
            'customer_id' => (int) $program->customer_id,
            'source_listing_id' => $program->source_listing_id === null ? null : (int) $program->source_listing_id,
            'status' => 'active',
            'version' => (int) $program->version,
            'title' => $program->title,
            'goal' => $program->goal,
            'level' => $program->level,
            'delivery_mode' => $program->delivery_mode,
            'agreed_fee_minor' => (int) $program->agreed_fee_minor,
            'currency' => $program->currency,
            'fee_unit' => $program->fee_unit,
            'location_text' => $program->location_text,
            'meeting_url' => $program->meeting_url,
            'schedule_note' => $program->schedule_note,
            'timezone' => $program->timezone,
            'planned_session_count' => $program->planned_session_count === null ? null : (int) $program->planned_session_count,
            'activated_at' => $this->isoTimestamp($now),
            'allowed_actions' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array<string, mixed>
     */
    private function invitationDto(
        object $invitation,
        object $program,
        array $snapshot,
        CarbonImmutable $now,
    ): array {
        return [
            'id' => (int) $invitation->id,
            'program_id' => (int) $program->id,
            'customer_id' => (int) $program->customer_id,
            'status' => 'accepted',
            'program_version' => (int) $invitation->program_version,
            'offer_snapshot' => $snapshot,
            'sent_at' => $this->isoTimestamp(CarbonImmutable::parse($invitation->created_at)),
            'expires_at' => $this->isoTimestamp(CarbonImmutable::parse($invitation->expires_at)),
            'responded_at' => $this->isoTimestamp($now),
            'accepted_at' => $this->isoTimestamp($now),
        ];
    }

    private function isoTimestamp(CarbonImmutable $timestamp): string
    {
        return $timestamp->setTimezone('UTC')->format('Y-m-d\TH:i:s.u\Z');
    }

    private function failure(int $status, string $code, string $message): InvitationAcceptException
    {
        return new InvitationAcceptException($status, $code, $message);
    }
}
