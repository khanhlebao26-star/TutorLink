<?php

namespace Tests\Support;

use App\Models\Conversation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class MessageSendFixtures
{
    public static function conversation(User $customer, User $tutor): int
    {
        $ids = [(int) $customer->id, (int) $tutor->id];
        sort($ids, SORT_NUMERIC);
        $chat = Conversation::create(['direct_key' => implode(':', $ids), 'activated_at' => now('UTC')]);
        $chat->members()->attach($ids, ['joined_at' => now('UTC')]);

        return (int) $chat->id;
    }

    /** @return array{client_message_id:string,message_type:string,body:string,file_ids:array} */
    public static function payload(string $body = 'Hello'): array
    {
        return ['client_message_id' => (string) Str::uuid(), 'message_type' => 'text', 'body' => $body, 'file_ids' => []];
    }

    /** @param array<string,mixed> $overrides */
    public static function seed(int $conversation, User $sender, array $overrides = []): int
    {
        $timestamp = CarbonImmutable::now('UTC')->format('Y-m-d H:i:s.uP');

        return DB::table('messages')->insertGetId(array_replace([
            'conversation_id' => $conversation,
            'sender_id' => $sender->id,
            'client_message_id' => (string) Str::uuid(),
            'message_type' => 'text',
            'body' => 'Existing text',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ], $overrides));
    }
}
