<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use LogicException;
use stdClass;

class RealtimeSessionChannels
{
    public function requireDatabaseSessions(): void
    {
        $connection = config('session.connection')
            ?: config('database.default');

        if (
            config('session.driver') !== 'database'
            || $connection !== config('database.default')
        ) {
            throw new LogicException(
                'Realtime requires database sessions on the application database connection.',
            );
        }
    }

    public function hash(string $sessionId): string
    {
        return hash_hmac(
            'sha256',
            $sessionId,
            (string) config('app.key'),
        );
    }

    public function channel(int $conversationId, string $token): string
    {
        return 'conversations.'.$conversationId.'.sessions.'.$token;
    }

    public function sessionIsLive(int $userId, string $sessionId): bool
    {
        $this->requireDatabaseSessions();

        return DB::table(config('session.table', 'sessions'))
            ->where('id', $sessionId)
            ->where('user_id', $userId)
            ->where(
                'last_activity',
                '>',
                now()->subMinutes((int) config('session.lifetime', 120))
                    ->getTimestamp(),
            )
            ->exists();
    }

    /** @return list<string> */
    public function liveSessionHashes(int $userId): array
    {
        $this->requireDatabaseSessions();

        return DB::table(config('session.table', 'sessions'))
            ->where('user_id', $userId)
            ->where(
                'last_activity',
                '>',
                now()->subMinutes((int) config('session.lifetime', 120))
                    ->getTimestamp(),
            )
            ->pluck('id')
            ->map(fn (string $id): string => $this->hash($id))
            ->all();
    }

    public function isRevoked(int $userId, string $sessionId): bool
    {
        return DB::table('realtime_sessions')
            ->where('user_id', $userId)
            ->where('session_hash', $this->hash($sessionId))
            ->whereNotNull('revoked_at')
            ->exists();
    }

    /**
     * The caller must hold the User row lock.
     */
    public function tombstone(int $userId, string $sessionId): void
    {
        $now = now('UTC');
        $hash = $this->hash($sessionId);

        DB::table('realtime_sessions')->insertOrIgnore([
            'user_id' => $userId,
            'session_hash' => $hash,
            'channel_token' => bin2hex(random_bytes(32)),
            'expires_at' => $now,
            'revoked_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('realtime_sessions')
            ->where('user_id', $userId)
            ->where('session_hash', $hash)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => $now,
                'updated_at' => $now,
            ]);
    }

    /**
     * The caller must hold the User row lock and validate the session.
     */
    public function grant(int $userId, string $sessionId): stdClass
    {
        $hash = $this->hash($sessionId);

        $record = DB::table('realtime_sessions')
            ->where('user_id', $userId)
            ->where('session_hash', $hash)
            ->first();

        if ($record !== null && $record->revoked_at !== null) {
            throw new LogicException('A revoked realtime session cannot be renewed.');
        }

        $now = now('UTC');
        $expiresAt = $now->copy()->addMinutes(
            (int) config('session.lifetime', 120),
        );

        if ($record === null) {
            $id = DB::table('realtime_sessions')->insertGetId([
                'user_id' => $userId,
                'session_hash' => $hash,
                'channel_token' => bin2hex(random_bytes(32)),
                'expires_at' => $expiresAt,
                'revoked_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $id = (int) $record->id;

            $changes = [
                'expires_at' => $expiresAt,
                'updated_at' => $now,
            ];

            if (CarbonImmutable::parse($record->expires_at)->lessThanOrEqualTo($now)) {
                $changes['channel_token'] = bin2hex(random_bytes(32));
            }

            DB::table('realtime_sessions')
                ->where('id', $id)
                ->update($changes);
        }

        return DB::table('realtime_sessions')->where('id', $id)->first();
    }

    public function canRead(int $userId, int $conversationId): bool
    {
        return DB::table('conversation_members as members')
            ->join('conversations', 'conversations.id', '=', 'members.conversation_id')
            ->join('users', 'users.id', '=', 'members.user_id')
            ->where('members.user_id', $userId)
            ->where('members.conversation_id', $conversationId)
            ->where('users.status', 'active')
            ->whereIn('users.role', ['customer', 'tutor'])
            ->whereNull('users.deleted_at')
            ->where('conversations.type', 'direct')
            ->whereIn('conversations.status', ['active', 'cancelled'])
            ->whereNotExists(function (Builder $query): void {
                $query->selectRaw('1')
                    ->from('tutor_profiles')
                    ->whereColumn('tutor_profiles.user_id', 'users.id')
                    ->where('tutor_profiles.status', 'suspended');
            })
            ->exists();
    }

    public function authorize(
        Request $request,
        int $conversationId,
        string $token,
    ): bool {
        $this->requireDatabaseSessions();

        if (! $request->hasSession() || $request->user() === null) {
            return false;
        }

        $userId = (int) $request->user()->id;
        $sessionId = $request->session()->getId();

        return DB::transaction(function () use ($userId, $sessionId, $conversationId, $token): bool {
            $user = DB::table('users')
                ->where('id', $userId)
                ->lockForUpdate()
                ->first();

            if (
                $user === null
                || ! $this->canRead($userId, $conversationId)
                || ! $this->sessionIsLive($userId, $sessionId)
            ) {
                return false;
            }

            return DB::table('realtime_sessions')
                ->where('user_id', $userId)
                ->where('session_hash', $this->hash($sessionId))
                ->where('channel_token', $token)
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now('UTC'))
                ->exists();
        }, 3);
    }
}
