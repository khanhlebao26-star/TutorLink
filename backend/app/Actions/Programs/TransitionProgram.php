<?php

namespace App\Actions\Programs;

use App\Exceptions\ProgramActionException;
use App\Models\Program;
use App\Models\User;
use App\Support\InvitationTransitionWriter;
use App\Support\NotificationOutboxWriter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class TransitionProgram
{
    private const TRANSITIONS = [
        'pause' => ['from' => ['active'], 'to' => 'paused', 'notification' => 'program_paused'],
        'resume' => ['from' => ['paused'], 'to' => 'active', 'notification' => 'program_resumed'],
        'complete' => ['from' => ['active', 'paused'], 'to' => 'completed', 'notification' => 'program_completed'],
        'cancel' => ['from' => ['draft', 'active', 'paused'], 'to' => 'cancelled', 'notification' => 'program_cancelled'],
    ];

    public function execute(User $actor, int $programId, string $transition, string $requestId): Program
    {
        if (! isset(self::TRANSITIONS[$transition])) {
            throw new \InvalidArgumentException('Unsupported Program transition.');
        }

        return DB::transaction(function () use ($actor, $programId, $requestId, $transition): Program {
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
                || $tutor->deleted_at !== null
                || $tutor->role !== 'tutor'
                || (int) $profile->user_id !== (int) $tutor->id
                || (int) $program->owner_tutor_profile_id !== (int) $profile->id
            ) {
                throw $this->failure(404, 'program_not_found', 'Program not found.');
            }

            if ($tutor->status !== 'active') {
                throw $this->failure(403, 'account_inactive', 'This account is not available.');
            }

            $definition = self::TRANSITIONS[$transition];

            if (! in_array($program->status, $definition['from'], true)) {
                throw $this->failure(
                    409,
                    'program_transition_invalid',
                    "Program cannot be {$transition} from its current status.",
                );
            }

            $now = CarbonImmutable::now('UTC');

            if ($transition === 'cancel' && $program->status === 'draft') {
                $invitation = DB::table('program_invitations')
                    ->where('program_id', $programId)
                    ->where('status', 'pending')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->first();

                if ($invitation !== null) {
                    $writer = app(InvitationTransitionWriter::class);

                    if (CarbonImmutable::parse($invitation->expires_at)->lte($now)) {
                        $writer->expire($invitation, $actor->id, $requestId, $now);
                    } else {
                        $writer->cancel($invitation, $actor->id, $requestId, $now);
                    }
                }
            }

            if (in_array($transition, ['complete', 'cancel'], true) && $program->status !== 'draft') {
                $blockers = $this->blockers($programId);

                if ($blockers['sessions'] !== [] || $blockers['tasks'] !== []) {
                    $code = $transition === 'complete'
                        ? 'program_completion_blocked'
                        : 'program_cancellation_blocked';
                    $message = $transition === 'complete'
                        ? 'Sessions and Tasks must be terminal before completing the Program.'
                        : 'Sessions and Tasks must be terminal before cancelling the Program.';

                    throw $this->failure(409, $code, $message, ['blockers' => $blockers]);
                }
            }

            $recipients = [];

            if ($program->status !== 'draft') {
                $recipients = DB::table('program_members')
                    ->where('program_id', $programId)
                    ->where('status', 'active')
                    ->where('user_id', '!=', $actor->id)
                    ->orderBy('user_id')
                    ->pluck('user_id')
                    ->map(fn ($id): int => (int) $id)
                    ->all();

                if ($recipients === []) {
                    throw $this->failure(
                        409,
                        'program_membership_conflict',
                        'The active Program has no other active member to receive its lifecycle event.',
                    );
                }
            }

            $timestamp = $now->format('Y-m-d H:i:s.uP');
            $changes = [
                'status' => $definition['to'],
                'updated_at' => $timestamp,
            ];

            if (in_array($definition['to'], ['completed', 'cancelled'], true)) {
                $changes['ends_at'] = $timestamp;
            }

            DB::table('programs')->where('id', $programId)->update($changes);

            $auditId = DB::table('audit_logs')->insertGetId([
                'actor_id' => $actor->id,
                'action' => "program.{$transition}",
                'resource_type' => 'program',
                'resource_id' => $programId,
                'before' => json_encode(['status' => $program->status], JSON_THROW_ON_ERROR),
                'after' => json_encode(['status' => $definition['to']], JSON_THROW_ON_ERROR),
                'request_id' => $requestId,
                'created_at' => $timestamp,
            ]);

            if ($program->status !== 'draft') {
                app(NotificationOutboxWriter::class)->record(
                    $definition['notification'],
                    'program',
                    $programId,
                    "program:{$programId}:{$transition}:{$auditId}",
                    $recipients,
                    ['deep_link' => "/programs/{$programId}"],
                );
            }

            return Program::query()->withActionContext()->findOrFail($programId);
        }, 3);
    }

    /** @return array{sessions: list<array{id: int, status: string, required_action: string}>, tasks: list<array{id: int, status: string, required_action: string}>} */
    private function blockers(int $programId): array
    {
        $sessions = DB::table('program_sessions')
            ->where('program_sessions.program_id', $programId)
            ->where('program_sessions.status', 'scheduled')
            ->orderBy('program_sessions.id')
            ->get(['program_sessions.id', 'program_sessions.status'])
            ->map(fn (object $session): array => [
                'id' => (int) $session->id,
                'status' => $session->status,
                'required_action' => 'complete_or_cancel_session',
            ])
            ->all();
        $tasks = DB::table('tasks')
            ->where('program_id', $programId)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->orderBy('id')
            ->get(['id', 'status'])
            ->map(fn (object $task): array => [
                'id' => (int) $task->id,
                'status' => $task->status,
                'required_action' => 'complete_or_cancel_task',
            ])
            ->all();

        return ['sessions' => $sessions, 'tasks' => $tasks];
    }

    /** @param array<string, mixed> $errors */
    private function failure(int $status, string $code, string $message, array $errors = []): ProgramActionException
    {
        return new ProgramActionException($status, $code, $message, $errors);
    }
}
