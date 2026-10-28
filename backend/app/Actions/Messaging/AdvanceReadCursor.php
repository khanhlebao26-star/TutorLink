<?php

namespace App\Actions\Messaging;

use App\Models\User;
use App\Support\ApiErrorResponse;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use stdClass;

class AdvanceReadCursor
{
    public function execute(User $actor, int $conversationId, int $messageId, string $requestId): stdClass
    {
        return DB::transaction(function () use ($actor, $conversationId, $messageId, $requestId): stdClass {
            $currentActor = User::whereKey($actor->id)->lockForUpdate()->first();

            if ($currentActor === null || $currentActor->status !== 'active') {
                $this->fail(403, 'account_inactive', 'This account is not available.', $requestId);
            }

            if (! in_array($currentActor->role, ['customer', 'tutor'], true)) {
                $this->fail(404, 'conversation_not_found', 'Conversation not found.', $requestId);
            }

            $conversation = DB::table('conversations')->where('id', $conversationId)
                ->where('type', 'direct')->whereIn('status', ['active', 'cancelled'])
                ->whereExists(function (Builder $query) use ($currentActor): void {
                    $query->selectRaw('1')->from('conversation_members')
                        ->whereColumn('conversation_members.conversation_id', 'conversations.id')
                        ->where('user_id', $currentActor->id);
                })->lockForUpdate()->first(['id']);

            $memberQuery = DB::table('conversation_members')
                ->where('conversation_id', $conversationId)->where('user_id', $currentActor->id);
            $member = (clone $memberQuery)->lockForUpdate()->first([
                'conversation_id',
                'last_read_message_id',
                'last_read_at',
            ]);

            if ($conversation === null || $member === null) {
                $this->fail(404, 'conversation_not_found', 'Conversation not found.', $requestId);
            }

            $target = DB::table('messages')->where('id', $messageId)->where('conversation_id', $conversationId)
                ->whereNotNull('created_at')->sharedLock()->first(['id', 'created_at']);

            if ($target === null) {
                $this->fail(422, 'read_target_invalid', 'Choose a valid message in this Conversation.', $requestId);
            }

            if ($member->last_read_message_id !== null) {
                $previous = DB::table('messages')->where('id', $member->last_read_message_id)
                    ->where('conversation_id', $conversationId)->whereNotNull('created_at')
                    ->sharedLock()->first(['id', 'created_at']);

                if ($previous === null) {
                    $this->fail(409, 'read_cursor_inconsistent', 'The stored read cursor requires review.', $requestId);
                }

                $targetAt = CarbonImmutable::parse($target->created_at);
                $previousAt = CarbonImmutable::parse($previous->created_at);
                $newer = $targetAt->greaterThan($previousAt)
                    || ($targetAt->equalTo($previousAt) && (int) $target->id > (int) $previous->id);

                if (! $newer) {
                    return $member;
                }
            }

            $memberQuery->update([
                'last_read_message_id' => $messageId,
                'last_read_at' => CarbonImmutable::now('UTC')->format('Y-m-d H:i:sP'),
            ]);

            return $memberQuery->first(['conversation_id', 'last_read_message_id', 'last_read_at']);
        }, 3);
    }

    private function fail(int $status, string $code, string $message, string $requestId): never
    {
        throw new HttpResponseException(ApiErrorResponse::make($status, $code, $message, $requestId));
    }
}
