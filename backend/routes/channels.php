<?php

use App\Models\User;
use App\Support\RealtimeSessionChannels;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel(
    'conversations.{conversationId}.sessions.{token}',
    function (User $actor, string $conversationId, string $token): bool {
        $id = filter_var(
            $conversationId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );

        if (
            $id === false
            || preg_match('/\A[a-f0-9]{64}\z/', $token) !== 1
        ) {
            return false;
        }

        return app(RealtimeSessionChannels::class)->authorize(
            request(),
            $id,
            $token,
        );
    },
    ['guards' => ['web']],
);
