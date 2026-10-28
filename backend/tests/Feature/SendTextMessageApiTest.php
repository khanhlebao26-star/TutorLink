<?php

use App\Models\Conversation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Support\MessageSendFixtures as Fixtures;
use Tests\Support\ProgramApiFixtures;
use Tests\Support\TestActors;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-28T07:00:00.123456Z'));
    $this->customer = TestActors::customer();
    $this->tutor = ProgramApiFixtures::eligibleTutor();
    $this->chat = Fixtures::conversation($this->customer, $this->tutor);
    $this->url = '/api/v1/conversations/'.$this->chat.'/messages';
});

afterEach(function (): void {
    $this->travelBack();
});

test('text send preserves spaces and only normalizes newlines with the session sender', function (): void {
    $payload = Fixtures::payload("  Chào\r\nthầy\r\n  ");
    $response = $this->actingAs($this->customer)->postJson($this->url, $payload)->assertCreated()
        ->assertJsonPath('data.body', "  Chào\nthầy\n  ")
        ->assertJsonPath('data.sender_id', $this->customer->id)
        ->assertJsonPath('data.created_at', '2026-10-28T07:00:00.123456Z');
    $this->assertDatabaseCount('messages', 1);
    expect(strlen(DB::table('messages')->value('payload_hash')))->toBe(64);
    expect(array_keys($response->json('data')))->toBe([
        'id',
        'conversation_id',
        'sender_id',
        'client_message_id',
        'message_type',
        'body',
        'created_at',
    ]);
});

test('input trimming and empty-string normalization remain enabled outside the message POST', function (): void {
    $request = Request::create('/api/v1/unrelated', 'POST', ['body' => '  hello  ', 'empty' => '']);
    $response = (new TrimStrings)->handle(
        $request,
        fn ($request) => (new ConvertEmptyStringsToNull)->handle(
            $request,
            fn ($request) => response()->json($request->all()),
        ),
    );
    expect($response->getData(true))->toBe(['body' => 'hello', 'empty' => null]);
});

test('same normalized payload replays including an uppercase UUID and omitted file_ids', function (): void {
    $payload = Fixtures::payload("a\r\nb\rc");
    $first = $this->actingAs($this->customer)->postJson($this->url, $payload)->assertCreated();
    $payload['body'] = "a\nb\nc";
    $payload['client_message_id'] = strtoupper($payload['client_message_id']);
    unset($payload['file_ids']);
    $this->postJson($this->url, $payload)->assertOk()->assertJsonPath('data', $first->json('data'));
    $this->assertDatabaseCount('messages', 1);
});

test('changing significant spaces or unicode composition conflicts instead of trimming or normalizing', function (): void {
    $payload = Fixtures::payload('  é  ');
    $this->actingAs($this->customer)->postJson($this->url, $payload)->assertCreated();
    foreach (['é', "  e\u{0301}  "] as $body) {
        $this->postJson($this->url, array_replace($payload, ['body' => $body]))
            ->assertConflict()->assertJsonPath('code', 'client_message_id_conflict');
    }
    $this->assertDatabaseCount('messages', 1);
});

test('same sender key in a different member conversation returns 409', function (): void {
    $otherChat = Fixtures::conversation($this->customer, ProgramApiFixtures::eligibleTutor());
    $payload = Fixtures::payload();
    $this->actingAs($this->customer)->postJson($this->url, $payload)->assertCreated();
    $this->postJson('/api/v1/conversations/'.$otherChat.'/messages', $payload)
        ->assertConflict()->assertJsonPath('code', 'client_message_id_conflict');
    $this->assertDatabaseCount('messages', 1);
});

test('the same UUID is independent for a different sender', function (): void {
    $payload = Fixtures::payload();
    $this->actingAs($this->customer)->postJson($this->url, $payload)->assertCreated();
    $this->actingAs($this->tutor)->postJson($this->url, $payload)->assertCreated()
        ->assertJsonPath('data.sender_id', $this->tutor->id);
    $this->assertDatabaseCount('messages', 2);
});

test('replay is allowed after cancellation and peer suspension without reopening or other writes', function (): void {
    $payload = Fixtures::payload();
    $first = $this->actingAs($this->customer)->postJson($this->url, $payload)->assertCreated();
    Conversation::findOrFail($this->chat)->update(['status' => 'cancelled', 'ended_at' => now()]);
    $this->tutor->update(['status' => 'suspended']);
    $before = DB::table('conversation_members')->where('conversation_id', $this->chat)->get()->toJson();
    $this->postJson($this->url, $payload)->assertOk()->assertJsonPath('data', $first->json('data'));
    $this->assertDatabaseHas('conversations', ['id' => $this->chat, 'status' => 'cancelled']);
    expect(DB::table('conversation_members')->where('conversation_id', $this->chat)->get()->toJson())->toBe($before);
    $this->assertDatabaseCount('messages', 1);
    $this->assertDatabaseCount('file_links', 0);
    $this->assertDatabaseCount('notification_outbox', 0);
});

test('a suspended actor cannot replay a previously persisted message', function (): void {
    $payload = Fixtures::payload();
    $this->actingAs($this->customer)->postJson($this->url, $payload)->assertCreated();
    $this->customer->update(['status' => 'suspended']);
    $this->actingAs($this->customer->fresh())->postJson($this->url, $payload)->assertForbidden();
});

test('replay checks current membership before looking up the sender key', function (): void {
    $payload = Fixtures::payload();
    $this->actingAs($this->customer)->postJson($this->url, $payload)->assertCreated();
    DB::table('conversation_members')->where('conversation_id', $this->chat)->where('user_id', $this->customer->id)->delete();
    $this->postJson($this->url, $payload)->assertNotFound();
    $this->assertDatabaseCount('messages', 1);
});

test('new messages are blocked for cancelled chat or suspended peer without reopening', function (): void {
    Conversation::findOrFail($this->chat)->update(['status' => 'cancelled', 'ended_at' => now()]);
    $this->actingAs($this->customer)->postJson($this->url, Fixtures::payload())->assertConflict();
    Conversation::findOrFail($this->chat)->update(['status' => 'active', 'ended_at' => null]);
    $this->tutor->update(['status' => 'suspended']);
    $this->postJson($this->url, Fixtures::payload())->assertConflict()->assertJsonPath('code', 'peer_unavailable');
    $this->assertDatabaseCount('messages', 0);
});

test('suspended TutorProfile blocks new messages while expired contact documents alone do not', function (): void {
    DB::table('verification_documents')->where('tutor_profile_id', $this->tutor->tutorProfile->id)
        ->update(['expires_at' => now()->subDay()]);
    $this->actingAs($this->customer)->postJson($this->url, Fixtures::payload())->assertCreated();
    $this->tutor->tutorProfile->update(['status' => 'suspended', 'suspended_at' => now()]);
    $this->postJson($this->url, Fixtures::payload())->assertConflict()->assertJsonPath('code', 'conversation_unavailable');
});

test('authentication outsider and Admin cannot send a valid payload', function (): void {
    $payload = Fixtures::payload();
    $this->postJson($this->url, $payload)->assertUnauthorized();
    $this->actingAs(TestActors::customer())->postJson($this->url, $payload)->assertNotFound();
    $this->actingAs(TestActors::admin())->postJson($this->url, $payload)->assertNotFound();
    $this->assertDatabaseCount('messages', 0);
});

test('validation rejects blank oversized system file and client-controlled fields', function (): void {
    $this->actingAs($this->customer);
    $payload = Fixtures::payload();
    foreach ([
        ['body' => ''],
        ['body' => null],
        ['body' => " \t\r\n\u{00A0}\u{3000}"],
        ['body' => str_repeat('á', 4001)],
        ['body' => ['bad']],
        ['message_type' => 'system'],
        ['message_type' => 'file'],
        ['file_ids' => [1]],
        ['client_message_id' => 'invalid'],
        ['sender_id' => $this->tutor->id],
        ['created_at' => '2020-01-01'],
        ['metadata' => ['x' => 'y']],
    ] as $override) {
        $this->postJson($this->url, array_replace($payload, $override))->assertUnprocessable()->assertJsonPath('code', 'validation_failed');
    }
    $this->assertDatabaseCount('messages', 0);
});

test('text length is checked after newline normalization and accepts exactly 4000 unicode characters', function (): void {
    $this->actingAs($this->customer)->postJson($this->url, Fixtures::payload(str_repeat('á', 4000)))->assertCreated();
    $body = str_repeat("a\r\n", 2000);
    $this->postJson($this->url, Fixtures::payload($body))->assertCreated()->assertJsonPath('data.body', str_repeat("a\n", 2000));
});

test('a sliding 60 second quota is global to the sender and replay never consumes it', function (): void {
    $payload = Fixtures::payload();
    $first = $this->actingAs($this->customer)->postJson($this->url, $payload)->assertCreated();
    for ($i = 0; $i < 29; $i++) {
        $this->postJson($this->url, Fixtures::payload())->assertCreated();
    }
    $this->postJson($this->url, $payload)->assertOk()->assertJsonPath('data.id', $first->json('data.id'));
    $otherChat = Fixtures::conversation($this->customer, ProgramApiFixtures::eligibleTutor());
    $this->postJson('/api/v1/conversations/'.$otherChat.'/messages', Fixtures::payload())
        ->assertTooManyRequests()->assertHeader('Retry-After', '60')->assertJsonPath('code', 'message_rate_limited');
    $this->assertDatabaseCount('messages', 30);
    $this->travel(60)->seconds();
    $this->postJson($this->url, Fixtures::payload())->assertCreated();
});

test('failed and conflicting attempts do not consume the remaining quota slot', function (): void {
    $this->actingAs($this->customer);
    for ($i = 0; $i < 29; $i++) {
        $this->postJson($this->url, Fixtures::payload())->assertCreated();
    }
    $this->postJson($this->url, Fixtures::payload(' '))->assertUnprocessable();
    $existing = DB::table('messages')->first();
    $conflict = Fixtures::payload('different');
    $conflict['client_message_id'] = $existing->client_message_id;
    $this->postJson($this->url, $conflict)->assertConflict();
    $this->postJson($this->url, Fixtures::payload())->assertCreated();
    $this->assertDatabaseCount('messages', 30);
});

test('quota uses the thirtieth newest timestamp even when legacy rows exceed 30 and ignores system messages', function (): void {
    for ($i = 0; $i < 31; $i++) {
        Fixtures::seed($this->chat, $this->customer, ['created_at' => now()->subSeconds($i)->format('Y-m-d H:i:s.uP')]);
    }
    $this->actingAs($this->customer)->postJson($this->url, Fixtures::payload())
        ->assertTooManyRequests()->assertHeader('Retry-After', '31');
    $this->travel(32)->seconds();
    $this->postJson($this->url, Fixtures::payload())->assertCreated();
    for ($i = 0; $i < 35; $i++) {
        Fixtures::seed($this->chat, $this->tutor, ['message_type' => 'system']);
    }
    $this->actingAs($this->tutor)->postJson($this->url, Fixtures::payload())->assertCreated();
});

test('legacy text with a null hash can replay without modifying the stored receipt', function (): void {
    $payload = Fixtures::payload("old\nbody");
    $id = Fixtures::seed($this->chat, $this->customer, ['body' => "old\r\nbody", 'client_message_id' => $payload['client_message_id']]);
    $this->actingAs($this->customer)->postJson($this->url, $payload)->assertOk()->assertJsonPath('data.id', $id);
    $this->assertDatabaseHas('messages', ['id' => $id, 'payload_hash' => null, 'body' => "old\r\nbody"]);
});

test('clock regression cannot place a new message behind an issued polling cursor', function (): void {
    $this->actingAs($this->customer)->postJson($this->url, Fixtures::payload())->assertCreated();
    $cursor = $this->getJson($this->url)->assertOk()->json('meta.polling_cursor');
    $this->travel(-1)->seconds();
    $second = $this->postJson($this->url, Fixtures::payload('second'))->assertCreated();
    $this->getJson($this->url.'?'.http_build_query(['after' => $cursor]))->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $second->json('data.id'));
});

test('a message insert failure rolls back the message and leaves the same UUID reusable', function (): void {
    $payload = Fixtures::payload();
    $shouldFail = true;
    DB::listen(function (QueryExecuted $query) use (&$shouldFail): void {
        if ($shouldFail && str_starts_with(strtolower($query->sql), 'insert into "messages"')) {
            $shouldFail = false;
            throw new RuntimeException('Injected message failure.');
        }
    });
    $this->withoutExceptionHandling();
    try {
        $this->actingAs($this->customer)->postJson($this->url, $payload);
        $this->fail('Expected injected failure.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Injected message failure.');
    }
    $this->assertDatabaseCount('messages', 0);
    $this->postJson($this->url, $payload)->assertCreated();
});

test('message timestamp precision and sender-time index exist without dropping the original unique constraints', function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL schema check.');
    }
    $precision = DB::table('information_schema.columns')->where('table_schema', 'public')->where('table_name', 'messages')
        ->where('column_name', 'created_at')->value('datetime_precision');
    expect((int) $precision)->toBe(6);
    foreach (['messages_sender_created_id_index', 'messages_sender_id_client_message_id_unique', 'messages_conversation_created_id_index'] as $name) {
        expect(DB::table('pg_indexes')->where('schemaname', 'public')->where('indexname', $name)->exists())->toBeTrue();
    }
    $migration = require database_path('migrations/2026_10_28_140000_prepare_message_sending.php');
    $migration->down();
    $migration->up();
    Fixtures::seed($this->chat, $this->customer);
    try {
        $migration->down();
        $this->fail('Expected precision-loss guard.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toContain('preservation plan');
    }
    $this->assertDatabaseCount('messages', 1);
});
