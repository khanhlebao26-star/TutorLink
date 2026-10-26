<?php

namespace App\Actions\Invitations;

use App\Exceptions\ProgramActionException;
use App\Models\User;
use App\Support\InvitationTransitionWriter;
use App\Support\NotificationOutboxWriter;
use App\Support\TutorEligibility;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SendInvitation
{
    private const OFFER_FIELDS = [
        'title',
        'goal',
        'level',
        'delivery_mode',
        'agreed_fee_minor',
        'currency',
        'fee_unit',
        'location_text',
        'meeting_url',
        'schedule_note',
        'timezone',
        'planned_session_count',
    ];

    /** @param array{expected_version: int|string} $data
     * @return array<string, mixed>
     */
    public function execute(
        User $actor,
        int $programId,
        array $data,
        string $idempotencyKey,
        string $requestId,
    ): array {
        $method = 'POST';
        $canonicalPath = "/api/v1/programs/{$programId}/invitations";
        $payload = ['expected_version' => (int) $data['expected_version']];
        $requestHash = hash('sha256', $method."\n".$canonicalPath."\n".json_encode($payload, JSON_THROW_ON_ERROR));
        $idempotencyKey = strtolower($idempotencyKey);

        return DB::transaction(function () use (
            $actor,
            $canonicalPath,
            $data,
            $idempotencyKey,
            $method,
            $programId,
            $requestHash,
            $requestId,
        ): array {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
            }

            $context = DB::table('programs')
                ->join('tutor_profiles', 'tutor_profiles.id', '=', 'programs.owner_tutor_profile_id')
                ->where('programs.id', $programId)
                ->select([
                    'programs.customer_id',
                    'programs.owner_tutor_profile_id',
                    'programs.source_listing_id',
                    'tutor_profiles.user_id as tutor_user_id',
                ])
                ->first();

            if ($context === null || (int) $context->tutor_user_id !== $actor->id) {
                throw $this->failure(404, 'program_not_found', 'Program not found.');
            }

            $userIds = array_values(array_unique([$actor->id, (int) $context->customer_id]));
            sort($userIds, SORT_NUMERIC);
            $users = DB::table('users')
                ->whereIn('id', $userIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            $tutor = $users->get($actor->id);
            $customer = $users->get((int) $context->customer_id);
            $profile = DB::table('tutor_profiles')
                ->where('id', $context->owner_tutor_profile_id)
                ->lockForUpdate()
                ->first();
            $listing = $context->source_listing_id === null
                ? null
                : DB::table('service_listings')
                    ->where('id', $context->source_listing_id)
                    ->lockForUpdate()
                    ->first();
            $program = DB::table('programs')->where('id', $programId)->lockForUpdate()->first();

            if (
                $tutor === null
                || $profile === null
                || $program === null
                || $customer === null
                || $tutor->deleted_at !== null
                || $tutor->role !== 'tutor'
                || (int) $profile->user_id !== (int) $tutor->id
                || (int) $program->owner_tutor_profile_id !== (int) $profile->id
            ) {
                throw $this->failure(404, 'program_not_found', 'Program not found.');
            }

            $now = CarbonImmutable::now('UTC');
            $receipt = DB::table('idempotency_receipts')
                ->where('actor_id', $actor->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($receipt !== null) {
                if ($receipt->expires_at !== null && CarbonImmutable::parse($receipt->expires_at)->lte($now)) {
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
                        'Idempotency-Key was already used for a different request.',
                    );
                } else {
                    $snapshot = json_decode($receipt->response_snapshot, true, 512, JSON_THROW_ON_ERROR);

                    if (! is_array($snapshot['invitation'] ?? null)) {
                        throw new \LogicException('Stored Invitation response snapshot is invalid.');
                    }

                    return $snapshot['invitation'];
                }
            }

            if (DB::table('program_invitations')
                ->where('invited_by', $actor->id)
                ->where('idempotency_key', $idempotencyKey)
                ->exists()) {
                throw $this->failure(
                    409,
                    'idempotency_key_reused',
                    'Idempotency-Key was already used for an Invitation.',
                );
            }

            if ($customer->deleted_at !== null || $customer->role !== 'customer' || $customer->status !== 'active') {
                throw $this->failure(
                    422,
                    'customer_invalid',
                    'The Program target is not an active Customer.',
                    ['customer_id' => ['Choose an active Customer account.']],
                );
            }

            if (! app(TutorEligibility::class)->canManagePrograms($tutor, $profile, $now)) {
                throw $this->failure(403, 'tutor_ineligible', 'Tutor is not eligible to send an Invitation.');
            }

            if ($program->status !== 'draft') {
                throw $this->failure(409, 'program_not_draft', 'Only a Draft Program can receive an Invitation.');
            }

            if ($program->source_listing_id !== null) {
                if ($listing === null || (int) $listing->tutor_profile_id !== (int) $profile->id) {
                    throw $this->failure(409, 'source_listing_invalid', 'The source Listing is no longer available.');
                }

                if (DB::table('listing_moderation_holds')
                    ->where('listing_id', $listing->id)
                    ->whereNull('restored_at')
                    ->exists()) {
                    throw $this->failure(409, 'listing_moderation_hold', 'The source Listing is under moderation hold.');
                }
            }

            $invitation = DB::table('program_invitations')
                ->where('program_id', $programId)
                ->where('status', 'pending')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($invitation !== null) {
                if (CarbonImmutable::parse($invitation->expires_at)->gt($now)) {
                    throw $this->failure(409, 'program_offer_locked', 'A pending Invitation locks this Program Draft.');
                }

                app(InvitationTransitionWriter::class)->expire($invitation, $actor->id, $requestId, $now);
            }

            if ((int) $program->version !== (int) $data['expected_version']) {
                throw $this->failure(
                    409,
                    'stale_program_version',
                    'The Program has changed. Reload it and retry with the current version.',
                    ['expected_version' => ['The expected version is stale.']],
                );
            }

            $offer = [];

            foreach (self::OFFER_FIELDS as $field) {
                $offer[$field] = $program->{$field};
            }
            $offer['agreed_fee_minor'] = (int) $offer['agreed_fee_minor'];
            $offer['planned_session_count'] = $offer['planned_session_count'] === null
                ? null
                : (int) $offer['planned_session_count'];

            $snapshot = [
                'program_id' => $programId,
                'target_customer_id' => (int) $program->customer_id,
                'source_listing_id' => $program->source_listing_id === null
                    ? null
                    : (int) $program->source_listing_id,
                'offer' => $offer,
                'source_listing' => $listing === null ? null : [
                    'id' => (int) $listing->id,
                    'title' => $listing->title,
                    'description' => $listing->description,
                    'delivery_mode' => $listing->delivery_mode,
                    'city' => $listing->city,
                    'district' => $listing->district,
                    'price_from_minor' => (int) $listing->price_from_minor,
                    'currency' => $listing->currency,
                    'price_unit' => $listing->price_unit,
                ],
            ];
            $sentAt = $now->format('Y-m-d H:i:s.uP');
            $expiresAt = $now->addHours(72);
            $invitationId = DB::table('program_invitations')->insertGetId([
                'program_id' => $programId,
                'program_version' => (int) $program->version,
                'invitee_user_id' => (int) $program->customer_id,
                'invited_by' => $actor->id,
                'status' => 'pending',
                'offer_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'idempotency_key' => $idempotencyKey,
                'expires_at' => $expiresAt->format('Y-m-d H:i:s.uP'),
                'responded_at' => null,
                'accepted_at' => null,
                'created_at' => $sentAt,
                'updated_at' => $sentAt,
            ]);

            DB::table('programs')->where('id', $programId)->update([
                'offer_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'snapshot_locked_at' => $sentAt,
                'updated_at' => $sentAt,
            ]);

            DB::table('audit_logs')->insert([
                'actor_id' => $actor->id,
                'action' => 'invitation.send',
                'resource_type' => 'program_invitation',
                'resource_id' => $invitationId,
                'before' => null,
                'after' => json_encode([
                    'status' => 'pending',
                    'program_id' => $programId,
                    'program_version' => (int) $program->version,
                    'expires_at' => $expiresAt->format('Y-m-d\TH:i:s.u\Z'),
                ], JSON_THROW_ON_ERROR),
                'request_id' => $requestId,
                'created_at' => $sentAt,
            ]);

            app(NotificationOutboxWriter::class)->record(
                'invitation_received',
                'program_invitation',
                $invitationId,
                "invitation:{$invitationId}:received",
                [(int) $program->customer_id],
                ['deep_link' => "/invitations/{$invitationId}"],
            );

            $invitationDto = (array) DB::table('program_invitations')->where('id', $invitationId)->first();
            DB::table('idempotency_receipts')->insert([
                'id' => (string) Str::uuid(),
                'actor_id' => $actor->id,
                'idempotency_key' => $idempotencyKey,
                'method' => $method,
                'canonical_path' => $canonicalPath,
                'request_hash' => $requestHash,
                'response_status' => 201,
                'response_snapshot' => json_encode(['invitation' => $invitationDto], JSON_THROW_ON_ERROR),
                'created_at' => $sentAt,
                'expires_at' => $now->addDays(30)->format('Y-m-d H:i:s.uP'),
            ]);

            return $invitationDto;
        }, 3);
    }

    /** @param array<string, list<string>> $errors */
    private function failure(int $status, string $code, string $message, array $errors = []): ProgramActionException
    {
        return new ProgramActionException($status, $code, $message, $errors);
    }
}
