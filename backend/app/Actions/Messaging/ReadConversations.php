<?php

namespace App\Actions\Messaging;

use App\Models\User;
use App\Support\ApiErrorResponse;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use stdClass;

final class ReadConversations
{
    public function query(User $actor): Builder
    {
        $latestId = DB::table('messages as newest')
            ->select('newest.id')
            ->whereColumn('newest.conversation_id', 'c.id')
            ->orderByDesc('newest.created_at')->orderByDesc('newest.id')->limit(1);

        $unread = DB::table('messages as unread')
            ->selectRaw('COUNT(*)')
            ->whereColumn('unread.conversation_id', 'c.id')
            ->whereColumn('unread.sender_id', 'peer.user_id')
            ->where('unread.message_type', '!=', 'system')
            ->where(function (Builder $query): void {
                $query->whereNull('read_message.id')
                    ->orWhereColumn('unread.created_at', '>', 'read_message.created_at')
                    ->orWhere(function (Builder $tie): void {
                        $tie->whereColumn('unread.created_at', 'read_message.created_at')
                            ->whereColumn('unread.id', '>', 'read_message.id');
                    });
            });

        return DB::table('conversations as c')
            ->join('conversation_members as mine', function (JoinClause $join) use ($actor): void {
                $join->on('mine.conversation_id', 'c.id')->where('mine.user_id', $actor->id);
            })
            ->join('conversation_members as peer', function (JoinClause $join) use ($actor): void {
                $join->on('peer.conversation_id', 'c.id')->where('peer.user_id', '!=', $actor->id);
            })
            ->join('users as peer_user', 'peer_user.id', '=', 'peer.user_id')
            ->leftJoin('messages as latest', function (JoinClause $join) use ($latestId): void {
                $join->on('latest.conversation_id', 'c.id')->where('latest.id', '=', $latestId);
            })
            ->leftJoin('messages as read_message', function (JoinClause $join): void {
                $join->on('read_message.id', 'mine.last_read_message_id')
                    ->on('read_message.conversation_id', 'c.id');
            })
            ->whereIn('c.status', ['active', 'cancelled'])
            ->where('c.type', 'direct')
            ->whereRaw('? IN (?, ?)', [$actor->role, 'customer', 'tutor'])
            ->select([
                'c.id',
                'c.status',
                'c.context_listing_id',
                'c.created_at',
                'peer.user_id as peer_id',
                'peer_user.full_name as peer_name',
                'read_message.id as last_read_message_id',
                'latest.id as latest_message_id',
                'latest.message_type as latest_message_type',
                'latest.body as latest_body',
                'latest.created_at as latest_message_at',
            ])
            ->selectSub($unread, 'unread_count')
            ->selectRaw('CASE WHEN latest.id IS NULL THEN 0 ELSE 1 END AS has_message')
            ->selectRaw('COALESCE(latest.created_at, c.created_at) AS activity_at')
            ->selectRaw('COALESCE(latest.id, c.id) AS activity_id');
    }

    public function find(User $actor, int $conversation, string $requestId): stdClass
    {
        $record = $this->query($actor)->where('c.id', $conversation)->first();

        if ($record === null) {
            throw new HttpResponseException(ApiErrorResponse::make(
                404,
                'conversation_not_found',
                'Conversation not found.',
                $requestId,
            ));
        }

        return $record;
    }
}
