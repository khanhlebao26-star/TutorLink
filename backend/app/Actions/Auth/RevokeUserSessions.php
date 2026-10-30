<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Support\RealtimeSessionChannels;
use Illuminate\Support\Facades\DB;

class RevokeUserSessions
{
    public function handle(
        User $user,
        ?string $exceptSessionId = null,
    ): void {
        $realtime = app(RealtimeSessionChannels::class);
        $realtime->requireDatabaseSessions();

        DB::transaction(function () use ($user, $exceptSessionId, $realtime): void {
            DB::table('users')
                ->where('id', $user->id)
                ->lockForUpdate()
                ->first();

            $sessions = DB::table(config('session.table', 'sessions'))
                ->where('user_id', $user->id);

            if ($exceptSessionId !== null) {
                $sessions->where('id', '<>', $exceptSessionId);
            }

            foreach ((clone $sessions)->pluck('id') as $sessionId) {
                $realtime->tombstone(
                    (int) $user->id,
                    (string) $sessionId,
                );
            }

            $grants = DB::table('realtime_sessions')
                ->where('user_id', $user->id)
                ->whereNull('revoked_at');

            if ($exceptSessionId !== null) {
                $grants->where(
                    'session_hash',
                    '<>',
                    $realtime->hash($exceptSessionId),
                );
            }

            $grants->update([
                'revoked_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ]);

            $sessions->delete();
        }, 3);
    }
}
