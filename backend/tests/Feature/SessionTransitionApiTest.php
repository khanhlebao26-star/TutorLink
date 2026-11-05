<?php

use App\Models\ProgramSession;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\ProgramApiFixtures;
use Tests\Support\TestActors;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->tutor = TestActors::tutor();
    $this->customer = TestActors::customer();

    $this->programId = ProgramApiFixtures::draft(
        $this->tutor,
        $this->customer,
        ['status' => 'active'],
    );

    ProgramApiFixtures::addMember(
        $this->programId,
        $this->tutor,
        'tutor',
    );

    ProgramApiFixtures::addMember(
        $this->programId,
        $this->customer,
        'customer',
    );

    $this->actingAs($this->tutor, 'web');

    $this->sessionId = $this->postJson(
        "/api/v1/programs/{$this->programId}/sessions",
        [
            'title' => 'English lesson',
            'planned_starts_at' => '2026-11-01T09:00:00+07:00',
            'planned_ends_at' => '2026-11-01T10:00:00+07:00',
            'delivery_mode' => 'online',
        ],
        ['Idempotency-Key' => (string) Str::uuid()],
    )->assertCreated()->json('data.id');

    $this->url = "/api/v1/sessions/{$this->sessionId}";
});

it('completes a Session and creates one actual', function () {
    $response = $this->postJson(
        "{$this->url}/complete",
        ['expected_version' => 1],
        ['Idempotency-Key' => (string) Str::uuid()],
    )->assertOk()
        ->assertJsonPath('data.id', $this->sessionId)
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.version', 2)
        ->assertJsonPath('data.actual.status', 'completed')
        ->assertJsonPath('data.actual.started_at', null)
        ->assertJsonPath('data.actual.ended_at', null);

    $this->assertDatabaseCount('lesson_sessions', 1);

    $this->assertDatabaseHas('lesson_sessions', [
        'program_session_id' => $this->sessionId,
        'status' => 'completed',
    ]);

    $this->assertDatabaseCount('audit_logs', 2);
    $this->assertDatabaseCount('idempotency_receipts', 2);
    $this->assertDatabaseCount('notification_outbox', 0);

    $metadata = ProgramSession::query()
        ->findOrFail($this->sessionId)
        ->terminal_replay;

    expect($metadata['actor_id'])->toBe($this->tutor->id)
        ->and($metadata['action'])->toBe('complete')
        ->and($metadata['payload']['expected_version'])->toBe(1)
        ->and($response->json('data'))
        ->not->toHaveKey('terminal_replay');
});

it('cancels a Session without creating an actual', function () {
    $this->postJson(
        "{$this->url}/cancel",
        [
            'expected_version' => 1,
            'reason' => 'Đổi kế hoạch học',
        ],
        ['Idempotency-Key' => (string) Str::uuid()],
    )->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.version', 2)
        ->assertJsonPath('data.actual', null);

    $this->assertDatabaseCount('lesson_sessions', 0);

    $this->assertDatabaseHas('audit_logs', [
        'resource_type' => 'session',
        'resource_id' => $this->sessionId,
        'action' => 'session.cancel',
        'reason' => 'Đổi kế hoạch học',
    ]);
});

it('allows scheduled transitions while the Program is paused', function (string $action, string $status, int $actualCount) {
    DB::table('programs')
        ->where('id', $this->programId)
        ->update(['status' => 'paused']);

    $this->postJson(
        "{$this->url}/{$action}",
        ['expected_version' => 1],
        ['Idempotency-Key' => (string) Str::uuid()],
    )->assertOk()
        ->assertJsonPath('data.status', $status);

    $this->assertDatabaseCount('lesson_sessions', $actualCount);

    $this->assertDatabaseHas('programs', [
        'id' => $this->programId,
        'status' => 'paused',
    ]);
})->with([
    ['complete', 'completed', 1],
    ['cancel', 'cancelled', 0],
]);

it('rejects the opposite transition after a terminal result', function (string $first, string $second) {
    $this->postJson(
        "{$this->url}/{$first}",
        ['expected_version' => 1],
        ['Idempotency-Key' => (string) Str::uuid()],
    )->assertOk();

    $this->postJson(
        "{$this->url}/{$second}",
        ['expected_version' => 1],
        ['Idempotency-Key' => (string) Str::uuid()],
    )->assertConflict();

    $this->assertDatabaseCount('audit_logs', 2);
    $this->assertDatabaseCount('idempotency_receipts', 2);
})->with([
    ['complete', 'cancel'],
    ['cancel', 'complete'],
]);

it('replays the same command with the same or a new key', function (string $action, int $actualCount) {
    $key = (string) Str::uuid();

    $body = $this->postJson(
        "{$this->url}/{$action}",
        ['expected_version' => 1],
        ['Idempotency-Key' => $key],
    )->assertOk()->json();

    $equivalent = $action === 'cancel'
        ? ['reason' => null, 'expected_version' => 1]
        : ['expected_version' => 1];

    $this->postJson(
        "{$this->url}/{$action}",
        $equivalent,
        ['Idempotency-Key' => $key],
    )->assertOk()->assertExactJson($body);

    $this->postJson(
        "{$this->url}/{$action}",
        $equivalent,
        ['Idempotency-Key' => (string) Str::uuid()],
    )->assertOk()->assertExactJson($body);

    $this->assertDatabaseCount('lesson_sessions', $actualCount);
    $this->assertDatabaseCount('audit_logs', 2);
    $this->assertDatabaseCount('idempotency_receipts', 3);
    $this->assertDatabaseCount('notification_outbox', 0);

    $this->assertDatabaseHas('program_sessions', [
        'id' => $this->sessionId,
        'version' => 2,
    ]);
})->with([
    ['complete', 1],
    ['cancel', 0],
]);

it('uses durable metadata after the receipt expires', function (string $action) {
    $key = (string) Str::uuid();

    $body = $this->postJson(
        "{$this->url}/{$action}",
        ['expected_version' => 1],
        ['Idempotency-Key' => $key],
    )->assertOk()->json();

    DB::table('idempotency_receipts')
        ->where('actor_id', $this->tutor->id)
        ->where('idempotency_key', $key)
        ->update([
            'expires_at' => now('UTC')
                ->subMinute()
                ->format('Y-m-d H:i:s.uP'),
        ]);

    $this->postJson(
        "{$this->url}/{$action}",
        ['expected_version' => 1],
        ['Idempotency-Key' => $key],
    )->assertOk()->assertExactJson($body);

    $this->assertDatabaseCount('audit_logs', 2);
    $this->assertDatabaseCount('idempotency_receipts', 2);
})->with(['complete', 'cancel']);

it('replays after the Program completes without repeating business effects', function (string $action, int $actualCount) {
    $body = $this->postJson(
        "{$this->url}/{$action}",
        ['expected_version' => 1],
        ['Idempotency-Key' => (string) Str::uuid()],
    )->assertOk()->json();

    $this->postJson(
        "/api/v1/programs/{$this->programId}/complete",
    )->assertOk();

    $this->postJson(
        "{$this->url}/{$action}",
        ['expected_version' => 1],
        ['Idempotency-Key' => (string) Str::uuid()],
    )->assertOk()->assertExactJson($body);

    $this->postJson(
        "{$this->url}/{$action}",
        ['expected_version' => 2],
        ['Idempotency-Key' => (string) Str::uuid()],
    )->assertConflict();

    $this->assertDatabaseCount('lesson_sessions', $actualCount);
    $this->assertDatabaseCount('audit_logs', 3);
    $this->assertDatabaseCount('notification_outbox', 1);

    $this->assertDatabaseHas('program_sessions', [
        'id' => $this->sessionId,
        'version' => 2,
    ]);
})->with([
    ['complete', 1],
    ['cancel', 0],
]);

it('blocks new transitions on a terminal Program', function (string $programStatus) {
    DB::table('programs')
        ->where('id', $this->programId)
        ->update(['status' => $programStatus]);

    foreach (['complete', 'cancel'] as $action) {
        $this->postJson(
            "{$this->url}/{$action}",
            ['expected_version' => 1],
            ['Idempotency-Key' => (string) Str::uuid()],
        )->assertConflict();
    }

    $this->assertDatabaseHas('program_sessions', [
        'id' => $this->sessionId,
        'status' => 'scheduled',
        'version' => 1,
    ]);

    $this->assertDatabaseCount('lesson_sessions', 0);
    $this->assertDatabaseCount('audit_logs', 1);
})->with(['completed', 'cancelled']);

it('rejects stale versions before creating an actual', function () {
    $this->postJson(
        "{$this->url}/complete",
        ['expected_version' => 2],
        ['Idempotency-Key' => (string) Str::uuid()],
    )->assertConflict()
        ->assertJsonPath('code', 'stale_session_version');

    $this->assertDatabaseCount('lesson_sessions', 0);
    $this->assertDatabaseCount('audit_logs', 1);
    $this->assertDatabaseCount('idempotency_receipts', 1);
});

it('returns 409 without side effects when the Session version is exhausted', function (string $action) {
    DB::table('program_sessions')->where('id', $this->sessionId)->update(['version' => 2147483647]);
    $payload = ['expected_version' => 2147483647];
    if ($action === 'cancel') {
        $payload['reason'] = 'Cancel boundary fixture';
    }

    $this->postJson(
        "{$this->url}/{$action}",
        $payload,
        ['Idempotency-Key' => (string) Str::uuid()],
    )->assertConflict()->assertJsonPath('code', 'session_version_exhausted');

    $this->assertDatabaseHas('program_sessions', [
        'id' => $this->sessionId, 'status' => 'scheduled',
        'version' => 2147483647, 'terminal_replay' => null,
    ]);
    $this->assertDatabaseCount('lesson_sessions', 0);
    $this->assertDatabaseCount('audit_logs', 1);
    $this->assertDatabaseCount('idempotency_receipts', 1);
    $this->assertDatabaseCount('notification_outbox', 0);
})->with(['complete', 'cancel']);

it('returns 409 without overwriting an actual attached to a scheduled Session', function () {
    $actualId = DB::table('lesson_sessions')->insertGetId([
        'program_session_id' => $this->sessionId, 'status' => 'completed',
        'completion_note' => 'Existing actual must be preserved',
    ]);

    $this->postJson(
        "{$this->url}/complete",
        ['expected_version' => 1],
        ['Idempotency-Key' => (string) Str::uuid()],
    )->assertConflict()->assertJsonPath('code', 'session_actual_conflict');

    $this->assertDatabaseHas('program_sessions', [
        'id' => $this->sessionId, 'status' => 'scheduled',
        'version' => 1, 'terminal_replay' => null,
    ]);
    $this->assertDatabaseHas('lesson_sessions', [
        'id' => $actualId, 'completion_note' => 'Existing actual must be preserved',
    ]);
    $this->assertDatabaseCount('lesson_sessions', 1);
    $this->assertDatabaseCount('audit_logs', 1);
    $this->assertDatabaseCount('idempotency_receipts', 1);
    $this->assertDatabaseCount('notification_outbox', 0);
});

it('rejects key reuse with another payload or action', function () {
    $key = (string) Str::uuid();

    $this->postJson(
        "{$this->url}/complete",
        ['expected_version' => 1],
        ['Idempotency-Key' => $key],
    )->assertOk();

    $this->postJson(
        "{$this->url}/complete",
        ['expected_version' => 2],
        ['Idempotency-Key' => $key],
    )->assertConflict()
        ->assertJsonPath('code', 'idempotency_key_reused');

    $this->postJson(
        "{$this->url}/cancel",
        ['expected_version' => 1],
        ['Idempotency-Key' => $key],
    )->assertConflict()
        ->assertJsonPath('code', 'idempotency_key_reused');

    $this->assertDatabaseCount('lesson_sessions', 1);
    $this->assertDatabaseCount('audit_logs', 2);
});

it('rejects a different cancel reason with a new key', function () {
    $this->postJson(
        "{$this->url}/cancel",
        [
            'expected_version' => 1,
            'reason' => 'Không thể tham gia',
        ],
        ['Idempotency-Key' => (string) Str::uuid()],
    )->assertOk();

    $this->postJson(
        "{$this->url}/cancel",
        [
            'expected_version' => 1,
            'reason' => 'Lý do khác',
        ],
        ['Idempotency-Key' => (string) Str::uuid()],
    )->assertConflict();

    $this->assertDatabaseCount('audit_logs', 2);
    $this->assertDatabaseCount('idempotency_receipts', 2);
});

it('checks current authorization before returning replay', function () {
    $key = (string) Str::uuid();

    $this->postJson(
        "{$this->url}/complete",
        ['expected_version' => 1],
        ['Idempotency-Key' => $key],
    )->assertOk();

    $this->actingAs($this->customer, 'web')
        ->postJson(
            "{$this->url}/complete",
            ['expected_version' => 1],
            ['Idempotency-Key' => $key],
        )->assertForbidden();

    $this->actingAs(TestActors::tutor(), 'web')
        ->postJson(
            "{$this->url}/complete",
            ['expected_version' => 1],
            ['Idempotency-Key' => $key],
        )->assertNotFound();

    $this->tutor->status = 'suspended';
    $this->tutor->save();

    $this->actingAs($this->tutor->fresh(), 'web')
        ->postJson(
            "{$this->url}/complete",
            ['expected_version' => 1],
            ['Idempotency-Key' => $key],
        )->assertForbidden();

    $this->assertDatabaseCount('lesson_sessions', 1);
    $this->assertDatabaseCount('audit_logs', 2);
});

it('does not infer replay from a legacy terminal status alone', function () {
    DB::table('program_sessions')
        ->where('id', $this->sessionId)
        ->update([
            'status' => 'completed',
            'version' => 2,
            'terminal_replay' => null,
        ]);

    DB::table('lesson_sessions')->insert([
        'program_session_id' => $this->sessionId,
        'status' => 'completed',
    ]);

    $this->postJson(
        "{$this->url}/complete",
        ['expected_version' => 1],
        ['Idempotency-Key' => (string) Str::uuid()],
    )->assertConflict();

    $this->assertDatabaseCount('lesson_sessions', 1);
    $this->assertDatabaseCount('audit_logs', 1);
});

it('rolls back all transition writes when a required write fails', function (string $action, string $needle) {
    DB::listen(static function (QueryExecuted $query) use ($needle): void {
        if (str_contains($query->sql, $needle)) {
            throw new RuntimeException(
                'Simulated Session transition write failure.',
            );
        }
    });

    $this->postJson(
        "{$this->url}/{$action}",
        ['expected_version' => 1],
        ['Idempotency-Key' => (string) Str::uuid()],
    )->assertStatus(500);

    $this->assertDatabaseHas('program_sessions', [
        'id' => $this->sessionId,
        'status' => 'scheduled',
        'version' => 1,
        'terminal_replay' => null,
    ]);

    $this->assertDatabaseCount('lesson_sessions', 0);
    $this->assertDatabaseCount('audit_logs', 1);
    $this->assertDatabaseCount('idempotency_receipts', 1);
    $this->assertDatabaseCount('notification_outbox', 0);
})->with([
    'complete actual' => [
        'complete',
        'insert into "lesson_sessions"',
    ],
    'complete audit' => [
        'complete',
        'insert into "audit_logs"',
    ],
    'complete metadata' => [
        'complete',
        'update "program_sessions" set "terminal_replay"',
    ],
    'complete receipt' => [
        'complete',
        'insert into "idempotency_receipts"',
    ],
    'cancel audit' => [
        'cancel',
        'insert into "audit_logs"',
    ],
    'cancel metadata' => [
        'cancel',
        'update "program_sessions" set "terminal_replay"',
    ],
    'cancel receipt' => [
        'cancel',
        'insert into "idempotency_receipts"',
    ],
]);
