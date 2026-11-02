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

class SaveSession
{
    /**
     * A null Session ID means create.
     *
     * @param  array<string, mixed>  $data  Validated request data.
     * @return array{status: int, body: array<string, mixed>}
     */
    public function execute(
        User $actor,
        int $programId,
        ?int $sessionId,
        array $data,
        string $idempotencyKey,
        string $requestId,
    ): array {
        $creating = $sessionId === null;
        $method = $creating ? 'POST' : 'PATCH';

        $path = $creating
            ? "/api/v1/programs/{$programId}/sessions"
            : "/api/v1/sessions/{$sessionId}";

        $data = $this->normalize($data, $creating);

        // Fixed ASCII keys; values are strings, null or safe integers.
        $canonicalJson = json_encode(
            $data,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_LINE_TERMINATORS
            | JSON_THROW_ON_ERROR,
        );

        $requestHash = hash(
            'sha256',
            "{$method}\n{$path}\n{$canonicalJson}",
        );

        $idempotencyKey = strtolower($idempotencyKey);

        return DB::transaction(function () use ($actor, $programId, $sessionId, $creating, $method, $path, $data, $requestHash, $idempotencyKey, $requestId): array {
            $context = DB::table('programs')
                ->join(
                    'tutor_profiles',
                    'tutor_profiles.id',
                    '=',
                    'programs.owner_tutor_profile_id',
                )
                ->where('programs.id', $programId)
                ->first([
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
                ->whereKey($programId)
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

            $session = null;

            if (! $creating) {
                $session = ProgramSession::query()
                    ->where('program_id', $programId)
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
            }

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
                        $receipt->method !== $method
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

            $allowed = $creating
                ? $policy->create($lockedActor, $program)
                : $policy->update($lockedActor, $session);

            if (! $allowed) {
                throw new SessionActionException(
                    409,
                    'session_state_conflict',
                    'The current Program or Session state blocks this action.',
                );
            }

            if (
                ! $creating
                && $session->version !== $data['expected_version']
            ) {
                throw new SessionActionException(
                    409,
                    'stale_session_version',
                    'The Session has changed. Reload it before editing.',
                    [
                        'expected_version' => [
                            'The expected version is stale.',
                        ],
                    ],
                );
            }

            $startsAt = CarbonImmutable::parse(
                $data['planned_starts_at']
                ?? $session?->planned_starts_at,
            );

            $endsAt = CarbonImmutable::parse(
                $data['planned_ends_at']
                ?? $session?->planned_ends_at,
            );

            if ($endsAt->lessThanOrEqualTo($startsAt)) {
                throw new SessionActionException(
                    422,
                    'validation_failed',
                    'The given data was invalid.',
                    [
                        'planned_ends_at' => [
                            'The end time must be after the start time.',
                        ],
                    ],
                );
            }

            $changes = $data;
            unset($changes['expected_version']);

            $before = $creating ? null : [
                'status' => $session->status,
                'version' => $session->version,
            ];

            $timestamp = $now->format('Y-m-d H:i:s.uP');

            if ($creating) {
                $sequence = (int) DB::table('program_sessions')
                    ->where('program_id', $programId)
                    ->where('program_version', $program->version)
                    ->max('sequence_number') + 1;

                $sessionId = DB::table('program_sessions')
                    ->insertGetId([
                        ...$changes,
                        'program_id' => $programId,
                        'program_version' => $program->version,
                        'sequence_number' => $sequence,
                        'timezone' => $program->timezone,
                        'status' => 'scheduled',
                        'version' => 1,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ]);
            } else {
                foreach ($changes as $field => $value) {
                    $current = $session->getAttribute($field);

                    if (
                        in_array(
                            $field,
                            ['planned_starts_at', 'planned_ends_at'],
                            true,
                        )
                    ) {
                        $current = CarbonImmutable::instance($current)
                            ->utc()
                            ->format('Y-m-d\TH:i:s.u\Z');
                    }

                    if ($current === $value) {
                        unset($changes[$field]);
                    }
                }

                if ($changes !== []) {
                    if ($session->version >= 2147483647) {
                        throw new SessionActionException(
                            409,
                            'session_version_exhausted',
                            'The Session version cannot be increased.',
                        );
                    }

                    DB::table('program_sessions')
                        ->where('id', $sessionId)
                        ->update([
                            ...$changes,
                            'version' => $session->version + 1,
                            'updated_at' => $timestamp,
                        ]);
                }
            }

            $saved = ProgramSession::query()
                ->with('actual')
                ->findOrFail($sessionId);

            if ($creating || $changes !== []) {
                DB::table('audit_logs')->insert([
                    'actor_id' => $lockedActor->id,
                    'action' => $creating
                        ? 'session.create'
                        : 'session.update',
                    'resource_type' => 'session',
                    'resource_id' => $saved->id,
                    'before' => $before === null
                        ? null
                        : json_encode($before, JSON_THROW_ON_ERROR),
                    'after' => json_encode([
                        'status' => $saved->status,
                        'version' => $saved->version,
                        'fields' => array_keys($changes),
                    ], JSON_THROW_ON_ERROR),
                    'request_id' => $requestId,
                    'created_at' => $timestamp,
                ]);
            }

            $status = $creating ? 201 : 200;

            $body = [
                'data' => (new SessionResource($saved))->resolve(),
            ];

            DB::table('idempotency_receipts')->insert([
                'id' => (string) Str::uuid(),
                'actor_id' => $lockedActor->id,
                'idempotency_key' => $idempotencyKey,
                'method' => $method,
                'canonical_path' => $path,
                'request_hash' => $requestHash,
                'response_status' => $status,
                'response_snapshot' => json_encode(
                    $body,
                    JSON_THROW_ON_ERROR,
                ),
                'created_at' => $timestamp,
                'expires_at' => $now->addDays(30)
                    ->format('Y-m-d H:i:s.uP'),
            ]);

            return [
                'status' => $status,
                'body' => $body,
            ];
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalize(array $data, bool $creating): array
    {
        if ($creating) {
            $data = [
                'location_text' => null,
                'meeting_url' => null,
                ...$data,
            ];
        }

        foreach (['planned_starts_at', 'planned_ends_at'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = CarbonImmutable::parse($data[$field])
                    ->utc()
                    ->format('Y-m-d\TH:i:s.u\Z');
            }
        }

        ksort($data, SORT_STRING);

        return $data;
    }
}
