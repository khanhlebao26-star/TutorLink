<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;

Broadcast::channel(
    'conversations.{conversationId}',
    function (User $actor, string $conversationId): bool {
        $id = filter_var(
            $conversationId,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );

        if ($id === false) {
            return false;
        }

        return DB::table('conversation_members as members')
            ->join(
                'conversations',
                'conversations.id',
                '=',
                'members.conversation_id',
            )
            ->join('users', 'users.id', '=', 'members.user_id')
            ->where('members.conversation_id', $id)
            ->where('members.user_id', $actor->id)
            ->where('users.status', 'active')
            ->whereIn('users.role', ['customer', 'tutor'])
            ->whereNull('users.deleted_at')
            ->where('conversations.type', 'direct')
            ->whereIn('conversations.status', ['active', 'cancelled'])
            ->exists();
    },
    ['guards' => ['web']],
);
