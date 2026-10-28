<?php

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TestActors;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function k034Chat(User $customer, User $tutor, array $attributes = []): int
{
    $ids = [(int) $customer->id, (int) $tutor->id];
    sort($ids, SORT_NUMERIC);
    $chat = Conversation::create(array_replace([
        'direct_key' => implode(':', $ids),
        'activated_at' => now('UTC'),
    ], $attributes));
    $chat->members()->attach($ids, ['joined_at' => now('UTC')]);

    return (int) $chat->id;
}

function k034Message(int $conversation, User $sender, array $attributes = []): int
{
    return DB::table('messages')->insertGetId(array_replace([
        'conversation_id' => $conversation,
        'sender_id' => $sender->id,
        'client_message_id' => (string) Str::uuid(),
        'message_type' => 'text',
        'body' => 'Hello',
        'created_at' => '2026-10-28 04:00:00+00',
        'updated_at' => '2026-10-28 04:00:00+00',
    ], $attributes));
}

function k034Url(int $conversation, array $query = []): string
{
    return '/api/v1/conversations/'.$conversation.'/messages?'.http_build_query($query);
}

beforeEach(function (): void {
    $this->customer = TestActors::customer();
    $this->tutor = TestActors::tutor();
    $this->chat = k034Chat($this->customer, $this->tutor);
});

test('all three reads require a session', function (): void {
    foreach (['/api/v1/conversations', '/api/v1/conversations/'.$this->chat, k034Url($this->chat)] as $url) {
        $this->getJson($url)->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
    }
});

test('inbox scopes before returning private data and returns only minimal peer fields', function (): void {
    k034Chat(TestActors::customer(), TestActors::tutor());
    $requestId = (string) Str::uuid();
    $response = $this->actingAs($this->customer)->getJson('/api/v1/conversations', ['X-Request-ID' => $requestId])
        ->assertOk()->assertHeader('X-Request-ID', $requestId)->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $this->chat)->assertJsonPath('data.0.preview', null)
        ->assertJsonPath('data.0.unread_count', 0)->assertJsonPath('data.0.peer.id', $this->tutor->id);

    expect(array_keys($response->json('data.0.peer')))->toBe(['id', 'display_name']);
    expect($response->json('data.0'))->not->toHaveKeys(['direct_key', 'email', 'phone', 'permissions']);
});

test('outsider gets 404 before cursor validation and cannot distinguish missing chats', function (): void {
    $this->actingAs(TestActors::customer());
    foreach ([$this->chat, 999999999] as $id) {
        $this->getJson('/api/v1/conversations/'.$id)->assertNotFound()->assertJsonPath('code', 'conversation_not_found');
        $this->getJson(k034Url($id, ['after' => 'bad', 'before' => 'bad']))
            ->assertNotFound()->assertJsonPath('code', 'conversation_not_found');
    }
});

test('admin has no participant inbox or conversation read access', function (): void {
    $this->actingAs(TestActors::admin())->getJson('/api/v1/conversations')->assertForbidden();
    $this->getJson('/api/v1/conversations/'.$this->chat)->assertNotFound();
    $this->getJson(k034Url($this->chat))->assertNotFound();
});

test('a suspended actor with an existing session cannot read', function (): void {
    $this->customer->update(['status' => 'suspended']);
    $this->actingAs($this->customer->fresh());
    foreach (['/api/v1/conversations', '/api/v1/conversations/'.$this->chat, k034Url($this->chat)] as $url) {
        $this->getJson($url)->assertForbidden()->assertJsonPath('code', 'account_inactive');
    }
});

test('cancelled chat and suspended peer remain readable without any read side effects', function (): void {
    $message = k034Message($this->chat, $this->tutor);
    Conversation::findOrFail($this->chat)->update(['status' => 'cancelled', 'ended_at' => now()]);
    $this->tutor->update(['status' => 'suspended']);
    $this->actingAs($this->customer)->getJson('/api/v1/conversations')->assertOk()->assertJsonCount(1, 'data');
    $this->getJson('/api/v1/conversations/'.$this->chat)->assertOk()->assertJsonPath('data.status', 'cancelled');
    $this->getJson(k034Url($this->chat))->assertOk()->assertJsonPath('data.0.id', $message);
    $this->assertDatabaseHas('conversations', ['id' => $this->chat, 'status' => 'cancelled']);
    $this->assertDatabaseHas('conversation_members', [
        'conversation_id' => $this->chat,
        'user_id' => $this->customer->id,
        'last_read_message_id' => null,
        'last_read_at' => null,
    ]);
    $this->assertDatabaseCount('messages', 1);
});

test('unread counts only peer messages after the composite read position', function (): void {
    $read = k034Message($this->chat, $this->tutor);
    k034Message($this->chat, $this->customer);
    k034Message($this->chat, $this->tutor, ['message_type' => 'system', 'body' => 'System']);
    k034Message($this->chat, $this->tutor);
    k034Message($this->chat, $this->tutor, ['created_at' => '2026-10-27 04:00:00+00']);
    DB::table('conversation_members')->where('conversation_id', $this->chat)
        ->where('user_id', $this->customer->id)->update(['last_read_message_id' => $read]);

    $this->actingAs($this->customer)->getJson('/api/v1/conversations')
        ->assertOk()->assertJsonPath('data.0.unread_count', 1)->assertJsonPath('data.0.last_read_message_id', $read);
    $this->getJson('/api/v1/conversations/'.$this->chat)->assertOk()->assertJsonPath('data.unread_count', 1);
});

test('a legacy read marker from another conversation is not used as a valid marker', function (): void {
    $otherTutor = TestActors::tutor();
    $other = k034Chat(TestActors::customer(), $otherTutor);
    $foreign = k034Message($other, $otherTutor, ['created_at' => '2026-10-29 04:00:00+00']);
    k034Message($this->chat, $this->tutor);
    DB::table('conversation_members')->where('conversation_id', $this->chat)
        ->where('user_id', $this->customer->id)->update(['last_read_message_id' => $foreign]);
    $this->actingAs($this->customer)->getJson('/api/v1/conversations/'.$this->chat)
        ->assertOk()->assertJsonPath('data.last_read_message_id', null)->assertJsonPath('data.unread_count', 1);
});

test('file preview is generic and history hides internal metadata', function (): void {
    k034Message($this->chat, $this->tutor, [
        'message_type' => 'file',
        'body' => 'private-name.pdf',
        'metadata' => json_encode(['object_key' => 'secret/object']),
        'payload_hash' => str_repeat('a', 64),
    ]);
    $this->actingAs($this->customer)->getJson('/api/v1/conversations')
        ->assertOk()->assertJsonPath('data.0.preview.text', 'Tệp đính kèm')->assertDontSee('private-name.pdf');
    $history = $this->getJson(k034Url($this->chat))->assertOk();
    expect(array_keys($history->json('data.0')))->toBe([
        'id',
        'conversation_id',
        'sender_id',
        'client_message_id',
        'message_type',
        'body',
        'created_at',
    ]);
    $history->assertDontSee('object_key')->assertDontSee('secret/object')->assertDontSee('payload_hash');
});

test('history visits every message with identical timestamps exactly once', function (): void {
    $ids = [];
    for ($i = 0; $i < 55; $i++) {
        $ids[] = k034Message($this->chat, $this->tutor);
    }
    $this->actingAs($this->customer);
    $query = ['per_page' => 7];
    $seen = [];
    do {
        $response = $this->getJson(k034Url($this->chat, $query))->assertOk();
        $seen = array_merge($seen, array_column($response->json('data'), 'id'));
        $query['before'] = $response->json('meta.next_cursor');
    } while ($response->json('meta.has_more'));

    expect($seen)->toBe(array_reverse($ids));
    expect(count(array_unique($seen)))->toBe(55);
});

test('polling drains all pages of new messages in ascending order', function (): void {
    k034Message($this->chat, $this->tutor);
    $initial = $this->actingAs($this->customer)->getJson(k034Url($this->chat))->assertOk();
    $cursor = $initial->json('meta.polling_cursor');
    $new = [];
    for ($i = 0; $i < 55; $i++) {
        $new[] = k034Message($this->chat, $this->tutor);
    }
    $seen = [];
    do {
        $page = $this->getJson(k034Url($this->chat, ['per_page' => 9, 'after' => $cursor]))->assertOk();
        $seen = array_merge($seen, array_column($page->json('data'), 'id'));
        $cursor = $page->json('meta.polling_cursor');
    } while ($page->json('meta.has_more'));

    expect($seen)->toBe($new);
    $this->getJson(k034Url($this->chat, ['after' => $cursor]))->assertOk()
        ->assertJsonCount(0, 'data')->assertJsonPath('meta.polling_cursor', $cursor);
});

test('an empty conversation provides a usable polling origin for the first messages', function (): void {
    $empty = $this->actingAs($this->customer)->getJson(k034Url($this->chat))->assertOk()->assertJsonCount(0, 'data');
    $cursor = $empty->json('meta.polling_cursor');
    expect($cursor)->toBeString()->not->toBeEmpty();
    $first = k034Message($this->chat, $this->tutor);
    $this->getJson(k034Url($this->chat, ['after' => $cursor]))->assertOk()->assertJsonPath('data.0.id', $first);
    $this->getJson(k034Url($this->chat, ['before' => $cursor]))->assertUnprocessable();
});

test('loading older history never replaces the polling checkpoint', function (): void {
    for ($i = 0; $i < 3; $i++) {
        k034Message($this->chat, $this->tutor);
    }
    $first = $this->actingAs($this->customer)->getJson(k034Url($this->chat, ['per_page' => 1]))->assertOk();
    $this->getJson(k034Url($this->chat, ['before' => $first->json('meta.next_cursor')]))
        ->assertOk()->assertJsonPath('meta.polling_cursor', null);
});

test('page size defaults to 20 and accepts 50', function (): void {
    for ($i = 0; $i < 55; $i++) {
        k034Message($this->chat, $this->tutor);
    }
    $this->actingAs($this->customer)->getJson(k034Url($this->chat))->assertOk()->assertJsonCount(20, 'data')
        ->assertJsonPath('meta.per_page', 20)->assertJsonPath('meta.has_more', true);
    $this->getJson(k034Url($this->chat, ['per_page' => 50]))->assertOk()->assertJsonCount(50, 'data');
});

test('invalid query parameters return the agreed error envelope', function (): void {
    $this->actingAs($this->customer);
    foreach ([
        ['per_page' => 0],
        ['per_page' => 51],
        ['per_page' => 'abc'],
        ['after' => ''],
        ['before' => ['bad']],
        ['after' => 'a', 'before' => 'b'],
        ['sender_id' => $this->tutor->id],
    ] as $query) {
        $this->getJson(k034Url($this->chat, $query))->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')->assertJsonStructure(['code', 'message', 'request_id', 'errors']);
    }
    $this->getJson('/api/v1/conversations?per_page=51')->assertUnprocessable();
    $this->getJson('/api/v1/conversations?page=2')->assertUnprocessable();
});

test('message cursors reject tampering and a different conversation scope', function (): void {
    $other = k034Chat($this->customer, TestActors::tutor());
    k034Message($this->chat, $this->tutor);
    $token = $this->actingAs($this->customer)->getJson(k034Url($this->chat))->assertOk()->json('meta.polling_cursor');
    $this->getJson(k034Url($other, ['after' => $token]))->assertUnprocessable()->assertJsonPath('code', 'invalid_cursor');
    $this->getJson(k034Url($this->chat, ['after' => $token.'x']))->assertUnprocessable()->assertJsonPath('code', 'invalid_cursor');
});

test('inbox uses cursor pagination with messages first and deterministic ordering of empty chats', function (): void {
    $newestFirst = [];
    for ($i = 0; $i < 4; $i++) {
        $sender = $i === 0 ? $this->tutor : TestActors::tutor();
        $id = $i === 0 ? $this->chat : k034Chat($this->customer, $sender);
        k034Message($id, $sender);
        array_unshift($newestFirst, $id);
    }
    $empty1 = k034Chat($this->customer, TestActors::tutor(), ['created_at' => '2026-10-28 06:00:00+00']);
    $empty2 = k034Chat($this->customer, TestActors::tutor(), ['created_at' => '2026-10-28 06:00:00+00']);
    $expected = array_merge($newestFirst, [$empty2, $empty1]);
    $this->actingAs($this->customer);
    $query = ['per_page' => 2];
    $seen = [];
    do {
        $page = $this->getJson('/api/v1/conversations?'.http_build_query($query))->assertOk();
        $seen = array_merge($seen, array_column($page->json('data'), 'id'));
        $query['cursor'] = $page->json('meta.next_cursor');
    } while ($page->json('meta.has_more'));
    expect($seen)->toBe($expected);
});

test('inbox cursor cannot be reused by another actor or as a message cursor', function (): void {
    k034Chat($this->customer, TestActors::tutor());
    $token = $this->actingAs($this->customer)->getJson('/api/v1/conversations?per_page=1')
        ->assertOk()->json('meta.next_cursor');
    $this->getJson(k034Url($this->chat, ['after' => $token]))->assertUnprocessable();
    $this->actingAs($this->tutor)->getJson('/api/v1/conversations?'.http_build_query(['cursor' => $token]))
        ->assertUnprocessable()->assertJsonPath('code', 'invalid_cursor');
});

test('pagination preserves fractional timestamps when the database stores them', function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL timestamp precision check.');
    }
    DB::statement('ALTER TABLE messages ALTER COLUMN created_at TYPE timestamptz(6)');
    $a = k034Message($this->chat, $this->tutor, ['created_at' => '2026-10-28 04:00:00.900001+00']);
    $b = k034Message($this->chat, $this->tutor, ['created_at' => '2026-10-28 04:00:00.100001+00']);
    $c = k034Message($this->chat, $this->tutor, ['created_at' => '2026-10-28 04:00:00.500001+00']);
    $first = $this->actingAs($this->customer)->getJson(k034Url($this->chat, ['per_page' => 1]))->assertOk()
        ->assertJsonPath('data.0.id', $a)->assertJsonPath('data.0.created_at', '2026-10-28T04:00:00.900001Z');
    $second = $this->getJson(k034Url($this->chat, ['per_page' => 1, 'before' => $first->json('meta.next_cursor')]))
        ->assertOk()->assertJsonPath('data.0.id', $c);
    $this->getJson(k034Url($this->chat, ['before' => $second->json('meta.next_cursor')]))
        ->assertOk()->assertJsonPath('data.0.id', $b);
});
