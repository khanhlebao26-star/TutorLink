<?php

use App\Models\Conversation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\MessageSendFixtures as Fixtures;
use Tests\Support\TestActors;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-28T08:00:00.987654Z'));
    $this->customer = TestActors::customer();
    $this->tutor = TestActors::tutor();
    $this->chat = Fixtures::conversation($this->customer, $this->tutor);
    $this->url = '/api/v1/conversations/'.$this->chat.'/read';
});

afterEach(function (): void {
    $this->travelBack();
});

test('first read advances only the current member and updates inbox unread consistently', function (): void {
    $first = Fixtures::seed($this->chat, $this->tutor);
    Fixtures::seed($this->chat, $this->tutor);
    $requestId = (string) Str::uuid();
    $this->actingAs($this->customer)->getJson('/api/v1/conversations')->assertOk()->assertJsonPath('data.0.unread_count', 2);
    $response = $this->patchJson($this->url, ['last_read_message_id' => $first], ['X-Request-ID' => $requestId])
        ->assertOk()->assertHeader('X-Request-ID', $requestId)->assertJsonPath('data', [
            'conversation_id' => $this->chat,
            'last_read_message_id' => $first,
            'last_read_at' => '2026-10-28T08:00:00.000000Z',
        ]);
    expect(array_keys($response->json('data')))->toBe(['conversation_id', 'last_read_message_id', 'last_read_at']);
    $this->getJson('/api/v1/conversations')->assertOk()->assertJsonPath('data.0.unread_count', 1)
        ->assertJsonPath('data.0.last_read_message_id', $first);
    $this->assertDatabaseHas('conversation_members', [
        'conversation_id' => $this->chat,
        'user_id' => $this->tutor->id,
        'last_read_message_id' => null,
        'last_read_at' => null,
    ]);
});

test('equal and older targets return the effective marker without changing its timestamp', function (): void {
    $old = Fixtures::seed($this->chat, $this->tutor);
    $new = Fixtures::seed($this->chat, $this->tutor);
    $initial = $this->actingAs($this->customer)->patchJson($this->url, ['last_read_message_id' => $new])->assertOk()->json('data');
    $this->travel(30)->seconds();
    foreach ([$old, $new] as $target) {
        $this->patchJson($this->url, ['last_read_message_id' => $target])->assertOk()->assertJsonPath('data', $initial);
    }
});

test('timestamp including fractions takes priority over a numerically larger message ID', function (): void {
    $newerSmallId = Fixtures::seed($this->chat, $this->tutor, ['created_at' => '2026-10-28 07:00:00.900001+00']);
    $olderLargeId = Fixtures::seed($this->chat, $this->tutor, ['created_at' => '2026-10-28 07:00:00.100001+00']);
    $this->actingAs($this->customer)->patchJson($this->url, ['last_read_message_id' => $olderLargeId])->assertOk();
    $this->patchJson($this->url, ['last_read_message_id' => $newerSmallId])->assertOk()->assertJsonPath('data.last_read_message_id', $newerSmallId);
    $this->patchJson($this->url, ['last_read_message_id' => $olderLargeId])->assertOk()->assertJsonPath('data.last_read_message_id', $newerSmallId);
});

test('ID breaks ties between messages with identical timestamps', function (): void {
    $first = Fixtures::seed($this->chat, $this->tutor);
    $second = Fixtures::seed($this->chat, $this->tutor);
    $third = Fixtures::seed($this->chat, $this->tutor);
    $this->actingAs($this->customer)->patchJson($this->url, ['last_read_message_id' => $second])->assertOk();
    $this->patchJson($this->url, ['last_read_message_id' => $first])->assertOk()->assertJsonPath('data.last_read_message_id', $second);
    $this->patchJson($this->url, ['last_read_message_id' => $third])->assertOk()->assertJsonPath('data.last_read_message_id', $third);
});

test('missing and foreign target messages return the same neutral error without writes', function (): void {
    $otherTutor = TestActors::tutor();
    $other = Fixtures::conversation($this->customer, $otherTutor);
    $foreign = Fixtures::seed($other, $otherTutor);
    $this->actingAs($this->customer);
    $messages = [];
    foreach ([999999999, $foreign] as $target) {
        $response = $this->patchJson($this->url, ['last_read_message_id' => $target])->assertUnprocessable()
            ->assertJsonPath('code', 'read_target_invalid')->assertJsonStructure(['code', 'message', 'request_id', 'errors']);
        $messages[] = $response->json('message');
    }
    expect($messages[0])->toBe($messages[1]);
    $this->assertDatabaseHas('conversation_members', ['conversation_id' => $this->chat, 'user_id' => $this->customer->id, 'last_read_message_id' => null]);
});

test('null reset missing field non-integer and non-positive targets are rejected', function (): void {
    $message = Fixtures::seed($this->chat, $this->tutor);
    $this->actingAs($this->customer)->patchJson($this->url, ['last_read_message_id' => $message])->assertOk();
    foreach ([
        [],
        ['last_read_message_id' => null],
        ['last_read_message_id' => 0],
        ['last_read_message_id' => -1],
        ['last_read_message_id' => '1'],
        ['last_read_message_id' => true],
        ['last_read_message_id' => 1.5],
        ['last_read_message_id' => []],
    ] as $payload) {
        $this->patchJson($this->url, $payload)->assertUnprocessable()->assertJsonPath('code', 'validation_failed');
    }
    $this->assertDatabaseHas('conversation_members', ['conversation_id' => $this->chat, 'user_id' => $this->customer->id, 'last_read_message_id' => $message]);
});

test('client cannot choose another member or set server-owned read fields', function (): void {
    $message = Fixtures::seed($this->chat, $this->tutor);
    $this->actingAs($this->customer);
    foreach (['user_id' => $this->tutor->id, 'member_id' => $this->tutor->id, 'conversation_id' => $this->chat, 'last_read_at' => '2026-10-29T00:00:00Z'] as $key => $value) {
        $this->patchJson($this->url, ['last_read_message_id' => $message, $key => $value])->assertUnprocessable();
    }
    $this->assertDatabaseHas('conversation_members', ['conversation_id' => $this->chat, 'user_id' => $this->tutor->id, 'last_read_message_id' => null]);
});

test('outsider and Admin receive 404 before validation including when the chat does not exist', function (): void {
    foreach ([TestActors::customer(), TestActors::admin()] as $actor) {
        $this->actingAs($actor)->patchJson($this->url, ['last_read_message_id' => null])->assertNotFound()->assertJsonPath('code', 'conversation_not_found');
        $this->patchJson('/api/v1/conversations/999999999/read', [])->assertNotFound()->assertJsonPath('code', 'conversation_not_found');
    }
});

test('unauthenticated and suspended actors cannot change read state', function (): void {
    $message = Fixtures::seed($this->chat, $this->tutor);
    $this->patchJson($this->url, ['last_read_message_id' => $message])->assertUnauthorized();
    $this->customer->update(['status' => 'suspended']);
    $this->actingAs($this->customer->fresh())->patchJson($this->url, ['last_read_message_id' => $message])->assertForbidden();
    $this->assertDatabaseHas('conversation_members', ['conversation_id' => $this->chat, 'user_id' => $this->customer->id, 'last_read_message_id' => null]);
});

test('cancelled chat with suspended peer remains readable and is not reopened', function (): void {
    $message = Fixtures::seed($this->chat, $this->tutor);
    Conversation::findOrFail($this->chat)->update(['status' => 'cancelled', 'ended_at' => now()]);
    $this->tutor->update(['status' => 'suspended']);
    $this->actingAs($this->customer)->patchJson($this->url, ['last_read_message_id' => $message])->assertOk();
    $this->assertDatabaseHas('conversations', ['id' => $this->chat, 'status' => 'cancelled']);
    $this->assertDatabaseCount('messages', 1);
    $this->assertDatabaseCount('notification_outbox', 0);
});

test('either participant may mark own or system messages without changing peer read state', function (): void {
    $own = Fixtures::seed($this->chat, $this->customer);
    Fixtures::seed($this->chat, $this->tutor);
    $system = Fixtures::seed($this->chat, $this->tutor, ['message_type' => 'system']);
    $this->actingAs($this->customer)->patchJson($this->url, ['last_read_message_id' => $own])->assertOk();
    $this->getJson('/api/v1/conversations')->assertOk()->assertJsonPath('data.0.unread_count', 1);
    $this->patchJson($this->url, ['last_read_message_id' => $system])->assertOk();
    $this->getJson('/api/v1/conversations')->assertOk()->assertJsonPath('data.0.unread_count', 0);
    $this->actingAs($this->tutor)->patchJson($this->url, ['last_read_message_id' => $own])->assertOk();
    $this->assertDatabaseHas('conversation_members', ['conversation_id' => $this->chat, 'user_id' => $this->customer->id, 'last_read_message_id' => $system]);
});

test('legacy cursor pointing to another Conversation is reported without reset or silent repair', function (): void {
    $target = Fixtures::seed($this->chat, $this->tutor);
    $otherTutor = TestActors::tutor();
    $other = Fixtures::conversation($this->customer, $otherTutor);
    $foreign = Fixtures::seed($other, $otherTutor);
    DB::table('conversation_members')->where('conversation_id', $this->chat)->where('user_id', $this->customer->id)->update(['last_read_message_id' => $foreign]);
    $this->actingAs($this->customer)->patchJson($this->url, ['last_read_message_id' => $target])
        ->assertConflict()->assertJsonPath('code', 'read_cursor_inconsistent');
    $this->assertDatabaseHas('conversation_members', ['conversation_id' => $this->chat, 'user_id' => $this->customer->id, 'last_read_message_id' => $foreign]);
});

test('a null-timestamp target is invalid and a malformed legacy marker requires review', function (): void {
    $bad = Fixtures::seed($this->chat, $this->tutor, ['created_at' => null]);
    $good = Fixtures::seed($this->chat, $this->tutor);
    $this->actingAs($this->customer)->patchJson($this->url, ['last_read_message_id' => $bad])->assertUnprocessable();
    DB::table('conversation_members')->where('conversation_id', $this->chat)->where('user_id', $this->customer->id)->update(['last_read_message_id' => $bad]);
    $this->patchJson($this->url, ['last_read_message_id' => $good])->assertConflict()->assertJsonPath('code', 'read_cursor_inconsistent');
});

test('equal legacy marker keeps a null last_read_at rather than inventing a read time', function (): void {
    $message = Fixtures::seed($this->chat, $this->tutor);
    DB::table('conversation_members')->where('conversation_id', $this->chat)->where('user_id', $this->customer->id)->update(['last_read_message_id' => $message]);
    $this->actingAs($this->customer)->patchJson($this->url, ['last_read_message_id' => $message])->assertOk()->assertJsonPath('data.last_read_at', null);
});

test('a failed pivot write rolls back and a retry may advance normally', function (): void {
    $message = Fixtures::seed($this->chat, $this->tutor);
    $shouldFail = true;
    DB::listen(function (QueryExecuted $query) use (&$shouldFail): void {
        if ($shouldFail && str_starts_with(strtolower($query->sql), 'update "conversation_members"')) {
            $shouldFail = false;
            throw new RuntimeException('Injected read cursor failure.');
        }
    });
    $this->withoutExceptionHandling();
    try {
        $this->actingAs($this->customer)->patchJson($this->url, ['last_read_message_id' => $message]);
        $this->fail('Expected injected failure.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Injected read cursor failure.');
    }
    $this->assertDatabaseHas('conversation_members', ['conversation_id' => $this->chat, 'user_id' => $this->customer->id, 'last_read_message_id' => null]);
    $this->patchJson($this->url, ['last_read_message_id' => $message])->assertOk()->assertJsonPath('data.last_read_message_id', $message);
});
