<?php

namespace App\Actions\Programs;

use App\Exceptions\ProgramActionException;
use App\Models\Program;
use App\Models\User;
use App\Support\NotificationOutboxWriter;
use App\Support\TutorEligibility;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class UpdateProgramDraft
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

    /** @param array<string, mixed> $data */
    public function execute(User $actor, int $programId, array $data, string $requestId): Program
    {
        return DB::transaction(function () use ($actor, $programId, $data, $requestId): Program {
            $context = DB::table('programs as programs')
                ->join('tutor_profiles', 'tutor_profiles.id', '=', 'programs.owner_tutor_profile_id')
                ->where('programs.id', $programId)
                ->select([
                    'programs.owner_tutor_profile_id',
                    'tutor_profiles.user_id as tutor_user_id',
                ])
                ->first();

            if ($context === null || (int) $context->tutor_user_id !== $actor->id) {
                throw new ProgramActionException(404, 'program_not_found', 'Program not found.');
            }

            $tutor = DB::table('users')
                ->where('id', $actor->id)
                ->lockForUpdate()
                ->first();
            $profile = DB::table('tutor_profiles')
                ->where('id', $context->owner_tutor_profile_id)
                ->lockForUpdate()
                ->first();
            $program = DB::table('programs')
                ->where('id', $programId)
                ->lockForUpdate()
                ->first();

            if (
                $tutor === null
                || $profile === null
                || $program === null
                || (int) $program->owner_tutor_profile_id !== (int) $profile->id
                || (int) $profile->user_id !== (int) $tutor->id
            ) {
                throw new ProgramActionException(404, 'program_not_found', 'Program not found.');
            }

            $now = CarbonImmutable::now('UTC');

            if (! app(TutorEligibility::class)->canManagePrograms($tutor, $profile, $now)) {
                throw new ProgramActionException(
                    403,
                    'tutor_ineligible',
                    'Tutor is not eligible to edit this Program.',
                );
            }

            if ($program->status !== 'draft') {
                throw new ProgramActionException(409, 'program_not_draft', 'Only a Draft Program can be edited.');
            }

            $invitation = DB::table('program_invitations')
                ->where('program_id', $programId)
                ->where('status', 'pending')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($invitation !== null) {
                $expiresAt = CarbonImmutable::parse($invitation->expires_at);

                if ($expiresAt->gt($now)) {
                    throw new ProgramActionException(
                        409,
                        'program_offer_locked',
                        'A pending Invitation locks this Program Draft.',
                    );
                }

                $timestamp = $now->format('Y-m-d H:i:s.uP');
                $expiredAt = $expiresAt->format('Y-m-d H:i:s.uP');

                DB::table('program_invitations')
                    ->where('id', $invitation->id)
                    ->update([
                        'status' => 'expired',
                        'responded_at' => $expiredAt,
                        'updated_at' => $timestamp,
                    ]);

                DB::table('audit_logs')->insert([
                    'actor_id' => $actor->id,
                    'action' => 'invitation.expire',
                    'resource_type' => 'program_invitation',
                    'resource_id' => $invitation->id,
                    'before' => json_encode(['status' => 'pending'], JSON_THROW_ON_ERROR),
                    'after' => json_encode([
                        'status' => 'expired',
                        'responded_at' => $expiresAt->setTimezone('UTC')->format('Y-m-d\TH:i:s.u\Z'),
                    ], JSON_THROW_ON_ERROR),
                    'request_id' => $requestId,
                    'created_at' => $timestamp,
                ]);

                app(NotificationOutboxWriter::class)->record(
                    'invitation_expired',
                    'program_invitation',
                    (int) $invitation->id,
                    "invitation:{$invitation->id}:expired",
                    [$actor->id],
                    ['deep_link' => "/programs/{$programId}"],
                );
            }

            if ((int) $program->version !== (int) $data['expected_version']) {
                throw new ProgramActionException(
                    409,
                    'stale_program_version',
                    'The Program has changed. Reload it and retry with the current version.',
                    ['expected_version' => ['The expected version is stale.']],
                );
            }

            $changes = array_intersect_key($data, array_flip(self::OFFER_FIELDS));
            $nextVersion = (int) $program->version + 1;
            $timestamp = $now->format('Y-m-d H:i:s.uP');

            DB::table('programs')
                ->where('id', $programId)
                ->update([
                    ...$changes,
                    'version' => $nextVersion,
                    'updated_at' => $timestamp,
                ]);

            DB::table('audit_logs')->insert([
                'actor_id' => $actor->id,
                'action' => 'program.update',
                'resource_type' => 'program',
                'resource_id' => $programId,
                'before' => json_encode([
                    'status' => 'draft',
                    'version' => (int) $program->version,
                ], JSON_THROW_ON_ERROR),
                'after' => json_encode([
                    'status' => 'draft',
                    'version' => $nextVersion,
                    'fields' => array_keys($changes),
                ], JSON_THROW_ON_ERROR),
                'request_id' => $requestId,
                'created_at' => $timestamp,
            ]);

            return Program::query()
                ->withActionContext()
                ->findOrFail($programId);
        }, 3);
    }
}
