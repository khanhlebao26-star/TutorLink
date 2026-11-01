<?php

namespace App\Actions\Sessions;

use App\Exceptions\SessionActionException;
use App\Http\Resources\SessionResource;
use App\Models\Program;
use App\Models\ProgramSession;
use App\Models\User;
use App\Policies\ProgramSessionPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TransitionSession
{
    /**
     * @param  array<string, mixed>  $data  Validated request data.
     * @return array{status: int, body: array<string, mixed>}
     */
    public function execute(
        User $actor,
        int $sessionId,
        string $transition,
        array $data,
        string $idempotencyKey,
        string $requestId,
    ): array {
        if (! in_array($transition, ['complete', 'cancel'], true)) {
            throw new \InvalidArgumentException(
                'Unsupported Session transition.',
            );
        }

        $targetStatus = $transition === 'complete'
            ? 'completed'
            : 'cancelled';

        $path = "/api/v1/sessions/{$sessionId}/{$transition}";

        $payload = [
            'expected_version' => $data['expected_version'],
        ];

        if ($transition === 'cancel') {
            $payload['reason'] = $data['reason'] ?? null;
        }

        ksort($payload, SORT_STRING);

        // Fixed ASCII keys; values are strings, null or safe integers.
        $canonicalJson = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_LINE_TERMINATORS
            | JSON_THROW_ON_ERROR,
        );

        $requestHash = hash(
            'sha256',
            "POST\n{$path}\n{$canonicalJson}",
        );

        $idempotencyKey = strtolower($idempotencyKey);

        return DB::transaction(function () use ($actor, $sessionId, $transition, $targetStatus, $path, $payload, $requestHash, $idempotencyKey, $requestId): array {
            $context = DB::table('program_sessions')
                ->join(
                    'programs',
                    'programs.id',
                    '=',
                    'program_sessions.program_id',
                )
                ->join(
                    'tutor_profiles',
                    'tutor_profiles.id',
                    '=',
                    'programs.owner_tutor_profile_id',
                )
                ->where('program_sessions.id', $sessionId)
                ->first([
                    'program_sessions.program_id',
                    'programs.customer_id',
                    'programs.owner_tutor_profile_id',
                    'tutor_profiles.user_id as tutor_user_id',
                ]);

            if (
                $context === null
                || (int) $context->tutor_user_id !== (int) $actor->id
            ) {
                throw new SessionActionException(
                    404,
                    'session_not_found',
                    'Session not found.',
                );
            }

            $userIds = array_values(array_unique([
                (int) $context->tutor_user_id,
                (int) $context->customer_id,
            ]));

            sort($userIds, SORT_NUMERIC);

            $users = User::withTrashed()
                ->whereIn('id', $userIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $lockedActor = $users->get($actor->id);

            $profile = DB::table('tutor_profiles')
                ->where('id', $context->owner_tutor_profile_id)
                ->lockForUpdate()
                ->first();

            $program = Program::query()
                ->whereKey($context->program_id)
                ->lockForUpdate()
                ->first();

            if (
                $profile === null
                || $program === null
                || (int) $profile->user_id !== (int) $actor->id
                || (int) $program->owner_tutor_profile_id
                !== (int) $profile->id
                || (int) $program->customer_id
                !== (int) $context->customer_id
            ) {
                throw new SessionActionException(
                    404,
                    'session_not_found',
                    'Session not found.',
                );
            }

            if (
                $lockedActor === null
                || $lockedActor->status !== 'active'
                || $lockedActor->deleted_at !== null
            ) {
                throw new SessionActionException(
                    403,
                    'account_inactive',
                    'This account is not available.',
                );
            }

            $policy = app(ProgramSessionPolicy::class);

            if (! $policy->mutate($lockedActor, $program)) {
                throw new SessionActionException(
                    403,
                    'session_mutation_forbidden',
                    'Only the Tutor owner can change Sessions.',
                );
            }

            $session = ProgramSession::query()
                ->where('program_id', $program->id)
                ->whereKey($sessionId)
                ->lockForUpdate()
                ->first();

            if ($session === null) {
                throw new SessionActionException(
                    404,
                    'session_not_found',
                    'Session not found.',
                );
            }

            $session->setRelation('program', $program);

            $now = CarbonImmutable::now('UTC');

            $receipt = DB::table('idempotency_receipts')
                ->where('actor_id', $lockedActor->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($receipt !== null) {
                if (
                    $receipt->expires_at !== null
                    && CarbonImmutable::parse($receipt->expires_at)
                        ->lessThanOrEqualTo($now)
                ) {
                    DB::table('idempotency_receipts')
                        ->where('id', $receipt->id)
                        ->delete();
                } else {
                    if (
                        $receipt->method !== 'POST'
                        || $receipt->canonical_path !== $path
                        || ! hash_equals(
                            $receipt->request_hash,
                            $requestHash,
                        )
                    ) {
                        throw new SessionActionException(
                            409,
                            'idempotency_key_reused',
                            'Idempotency-Key was used for another request.',
                        );
                    }

                    if ($session->status !== $targetStatus) {
                        throw new SessionActionException(
                            409,
                            'session_transition_conflict',
                            'The Session does not match the stored transition.',
                        );
                    }

                    $body = json_decode(
                        $receipt->response_snapshot,
                        true,
                        512,
                        JSON_THROW_ON_ERROR,
                    );

                    if (! is_array($body)) {
                        throw new \LogicException(
                            'Stored Session response is invalid.',
                        );
                    }

                    return [
                        'status' => (int) $receipt->response_status,
                        'body' => $body,
                    ];
                }
            }

            if (
                $session->status !== 'scheduled'
                || $session->terminal_replay !== null
            ) {
                $terminal = $session->terminal_replay;

                $matches = is_array($terminal)
                    && $session->status === $targetStatus
                    && (int) ($terminal['actor_id'] ?? 0)
                    === (int) $lockedActor->id
                    && ($terminal['action'] ?? null) === $transition
                    && is_string($terminal['request_hash'] ?? null)
                    && hash_equals(
                        $terminal['request_hash'],
                        $requestHash,
                    )
                    && ($terminal['response_status'] ?? null) === 200
                    && is_array(
                        $terminal['response_snapshot'] ?? null,
                    );

                if (! $matches) {
                    throw new SessionActionException(
                        409,
                        'session_transition_conflict',
                        'The Session is terminal or the replay does not match.',
                    );
                }

                $result = [
                    'status' => 200,
                    'body' => $terminal['response_snapshot'],
                ];

                $this->remember(
                    $lockedActor,
                    $idempotencyKey,
                    $path,
                    $requestHash,
                    $result,
                    $now,
                );

                return $result;
            }

            $allowed = $transition === 'complete'
                ? $policy->complete($lockedActor, $session)
                : $policy->cancel($lockedActor, $session);

            if (! $allowed) {
                throw new SessionActionException(
                    409,
                    'session_state_conflict',
                    'The current Program or Session state blocks this action.',
                );
            }

            if ($session->version !== $payload['expected_version']) {
                throw new SessionActionException(
                    409,
                    'stale_session_version',
                    'The Session has changed. Reload it before continuing.',
                    [
                        'expected_version' => [
                            'The expected version is stale.',
                        ],
                    ],
                );
            }

            if ($session->version >= 2147483647) {
                throw new SessionActionException(
                    409,
                    'session_version_exhausted',
                    'The Session version cannot be increased.',
                );
            }

            if (
                DB::table('lesson_sessions')
                    ->where('program_session_id', $sessionId)
                    ->exists()
            ) {
                throw new SessionActionException(
                    409,
                    'session_actual_conflict',
                    'The scheduled Session already has an actual record.',
                );
            }

            $before = [
                'status' => $session->status,
                'version' => $session->version,
            ];

            $nextVersion = $session->version + 1;
            $timestamp = $now->format('Y-m-d H:i:s.uP');
            $actualId = null;

            if ($transition === 'complete') {
                $actualId = DB::table('lesson_sessions')
                    ->insertGetId([
                        'program_session_id' => $sessionId,
                        'status' => 'completed',
                        'started_at' => null,
                        'ended_at' => null,
                        'completion_note' => null,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ]);
            }

            DB::table('program_sessions')
                ->where('id', $sessionId)
                ->update([
                    'status' => $targetStatus,
                    'version' => $nextVersion,
                    'updated_at' => $timestamp,
                ]);

            DB::table('audit_logs')->insert([
                'actor_id' => $lockedActor->id,
                'action' => "session.{$transition}",
                'resource_type' => 'session',
                'resource_id' => $sessionId,
                'before' => json_encode(
                    $before,
                    JSON_THROW_ON_ERROR,
                ),
                'after' => json_encode([
                    'status' => $targetStatus,
                    'version' => $nextVersion,
                    'actual_id' => $actualId,
                ], JSON_THROW_ON_ERROR),
                'reason' => $payload['reason'] ?? null,
                'request_id' => $requestId,
                'created_at' => $timestamp,
            ]);

            $saved = ProgramSession::query()
                ->with('actual')
                ->findOrFail($sessionId);

            $result = [
                'status' => 200,
                'body' => [
                    'data' => (new SessionResource($saved))->resolve(),
                ],
            ];

            DB::table('program_sessions')
                ->where('id', $sessionId)
                ->update([
                    'terminal_replay' => json_encode([
                        'actor_id' => (int) $lockedActor->id,
                        'action' => $transition,
                        'payload' => $payload,
                        'request_hash' => $requestHash,
                        'response_status' => 200,
                        'response_snapshot' => $result['body'],
                    ], JSON_THROW_ON_ERROR),
                ]);

            $this->remember(
                $lockedActor,
                $idempotencyKey,
                $path,
                $requestHash,
                $result,
                $now,
            );

            return $result;
        }, 3);
    }

    /**
     * @param  array{status: int, body: array<string, mixed>}  $result
     */
    private function remember(
        User $actor,
        string $idempotencyKey,
        string $path,
        string $requestHash,
        array $result,
        CarbonImmutable $now,
    ): void {
        DB::table('idempotency_receipts')->insert([
            'id' => (string) Str::uuid(),
            'actor_id' => $actor->id,
            'idempotency_key' => $idempotencyKey,
            'method' => 'POST',
            'canonical_path' => $path,
            'request_hash' => $requestHash,
            'response_status' => $result['status'],
            'response_snapshot' => json_encode(
                $result['body'],
                JSON_THROW_ON_ERROR,
            ),
            'created_at' => $now->format('Y-m-d H:i:s.uP'),
            'expires_at' => $now->addDays(30)
                ->format('Y-m-d H:i:s.uP'),
        ]);
    }
}
