<?php

namespace App\Jobs;

use App\Support\RealtimeSessionChannels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;

class BroadcastConversationChanged implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public readonly int $conversationId,
    ) {
        $this->onQueue('broadcasts');
    }

    public function backoff(): int
    {
        return 5;
    }

    public function handle(RealtimeSessionChannels $sessions): void
    {
        $sessions->requireDatabaseSessions();

        DB::transaction(function () use ($sessions): void {
            $memberIds = DB::table('conversation_members')
                ->where('conversation_id', $this->conversationId)
                ->orderBy('user_id')
                ->pluck('user_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();

            if (count($memberIds) !== 2) {
                return;
            }

            DB::table('users')
                ->whereIn('id', $memberIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $channels = [];

            foreach ($memberIds as $userId) {
                if (! $sessions->canRead($userId, $this->conversationId)) {
                    continue;
                }

                $hashes = $sessions->liveSessionHashes($userId);

                if ($hashes === []) {
                    continue;
                }

                $tokens = DB::table('realtime_sessions')
                    ->where('user_id', $userId)
                    ->whereIn('session_hash', $hashes)
                    ->whereNull('revoked_at')
                    ->where('expires_at', '>', now('UTC'))
                    ->pluck('channel_token');

                foreach ($tokens as $token) {
                    $channels[] = 'private-'.$sessions->channel(
                        $this->conversationId,
                        $token,
                    );
                }
            }

            if ($channels === []) {
                return;
            }

            /*
             * Keep the User locks until transport publishing completes.
             * Revocation uses the same locks.
             */
            Broadcast::connection()->broadcast(
                array_values(array_unique($channels)),
                'conversation.messages_changed',
                ['conversation_id' => $this->conversationId],
            );
        });
    }
}
