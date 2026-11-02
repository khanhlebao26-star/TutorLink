<?php

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
        $this->customer,
        'customer',
    );

    $this->url =
        "/api/v1/programs/{$this->programId}/sessions";

    $this->payload = [
        'title' => 'English lesson',
        'planned_starts_at' => '2026-11-01T09:00:00.123456+07:00',
        'planned_ends_at' => '2026-11-01T10:00:00.123456+07:00',
        'delivery_mode' => 'online',
        'meeting_url' => 'https://example.com/meeting',
    ];

    $this->actingAs($this->tutor, 'web');
});

it('creates only a planned Session with UTC timestamps', function () {
    $response = $this->postJson(
        $this->url,
        $this->payload,
        ['Idempotency-Key' => (string) Str::uuid()],
    )->assertCreated()
        ->assertJsonPath('data.status', 'scheduled')
        ->assertJsonPath('data.version', 1)
        ->assertJsonPath('data.timezone', 'Asia/Ho_Chi_Minh')
        ->assertJsonPath(
            'data.planned_starts_at',
            '2026-11-01T02:00:00.123456Z',
        )
        ->assertJsonPath('data.actual', null);

    $id = $response->json('data.id');

    $this->assertDatabaseHas('program_sessions', [
        'id' => $id,
        'program_id' => $this->programId,
        'sequence_number' => 1,
        'status' => 'scheduled',
        'version' => 1,
    ]);

    $this->assertDatabaseCount('lesson_sessions', 0);
    $this->assertDatabaseCount('audit_logs', 1);
    $this->assertDatabaseCount('idempotency_receipts', 1);
    $this->assertDatabaseCount('notification_outbox', 0);

    expect($response->json('data'))
        ->not->toHaveKey('terminal_replay');

    $audit = json_decode(
        DB::table('audit_logs')->value('after'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    expect($audit)->not->toHaveKey('meeting_url');
});

it('allows member reads and rejects outsider reads', function () {
    $id = $this->postJson(
        $this->url,
        $this->payload,
        ['Idempotency-Key' => (string) Str::uuid()],
    )->assertCreated()->json('data.id');

    $this->actingAs($this->customer, 'web')
        ->getJson($this->url)
        ->assertOk()
        ->assertJsonPath('data.0.id', $id);

    $this->getJson("/api/v1/sessions/{$id}")
        ->assertOk()
        ->assertJsonPath('data.id', $id);

    $this->actingAs(TestActors::customer(), 'web')
        ->getJson($this->url)
        ->assertNotFound();

    $this->getJson("/api/v1/sessions/{$id}")
        ->assertNotFound();
});

it('increments version once and rejects stale edits', function () {
    $id = $this->postJson(
        $this->url,
        $this->payload,
        ['Idempotency-Key' => (string) Str::uuid()],
    )->assertCreated()->json('data.id');

    $this->patchJson(
        "/api/v1/sessions/{$id}",
        [
            'expected_version' => 1,
            'title' => 'Updated lesson',
        ],
        ['Idempotency-Key' => (string) Str::uuid()],
    )->assertOk()
        ->assertJsonPath('data.version', 2);

    $this->patchJson(
        "/api/v1/sessions/{$id}",
        [
            'expected_version' => 1,
            'title' => 'Stale update',
        ],
        ['Idempotency-Key' => (string) Str::uuid()],
    )->assertConflict()
        ->assertJsonPath('code', 'stale_session_version');

    $this->assertDatabaseHas('program_sessions', [
        'id' => $id,
        'title' => 'Updated lesson',
        'version' => 2,
    ]);

    $this->assertDatabaseCount('audit_logs', 2);
    $this->assertDatabaseCount('idempotency_receipts', 2);
    $this->assertDatabaseCount('lesson_sessions', 0);
});

it('does not increase version or audit for a no-op edit', function () {
    $id = $this->postJson(
        $this->url,
        $this->payload,
        ['Idempotency-Key' => (string) Str::uuid()],
    )->assertCreated()->json('data.id');

    $this->patchJson(
        "/api/v1/sessions/{$id}",
        [
            'expected_version' => 1,
            'title' => $this->payload['title'],
        ],
        ['Idempotency-Key' => (string) Str::uuid()],
    )->assertOk()
        ->assertJsonPath('data.version', 1);

    $this->assertDatabaseCount('audit_logs', 1);
    $this->assertDatabaseCount('idempotency_receipts', 2);
});

it('checks the merged schedule for a partial edit', function () {
    $id = $this->postJson(
        $this->url,
        $this->payload,
        ['Idempotency-Key' => (string) Str::uuid()],
    )->assertCreated()->json('data.id');

    $this->patchJson(
        "/api/v1/sessions/{$id}",
        [
            'expected_version' => 1,
            'planned_ends_at' => '2026-11-01T08:00:00+07:00',
        ],
        ['Idempotency-Key' => (string) Str::uuid()],
    )->assertUnprocessable()
        ->assertJsonValidationErrors('planned_ends_at');

    $this->assertDatabaseHas('program_sessions', [
        'id' => $id,
        'version' => 1,
    ]);

    $this->assertDatabaseCount('audit_logs', 1);
    $this->assertDatabaseCount('idempotency_receipts', 1);
});

it('blocks new writes when the Program is not active', function (string $status) {
    $id = $this->postJson(
        $this->url,
        $this->payload,
        ['Idempotency-Key' => (string) Str::uuid()],
    )->assertCreated()->json('data.id');

    DB::table('programs')
        ->where('id', $this->programId)
        ->update(['status' => $status]);

    $this->postJson(
        $this->url,
        $this->payload,
        ['Idempotency-Key' => (string) Str::uuid()],
    )->assertConflict();

    $this->patchJson(
        "/api/v1/sessions/{$id}",
        [
            'expected_version' => 1,
            'title' => 'Blocked edit',
        ],
        ['Idempotency-Key' => (string) Str::uuid()],
    )->assertConflict();

    $this->assertDatabaseCount('program_sessions', 1);
    $this->assertDatabaseCount('audit_logs', 1);
})->with(['paused', 'completed', 'cancelled', 'draft']);

it('replays create and edit before terminal Program state checks', function () {
    $createKey = (string) Str::uuid();

    $created = $this->postJson(
        $this->url,
        $this->payload,
        ['Idempotency-Key' => $createKey],
    )->assertCreated()->json();

    $id = $created['data']['id'];
    $editKey = (string) Str::uuid();

    $editPayload = [
        'expected_version' => 1,
        'title' => 'Updated lesson',
    ];

    $edited = $this->patchJson(
        "/api/v1/sessions/{$id}",
        $editPayload,
        ['Idempotency-Key' => $editKey],
    )->assertOk()->json();

    DB::table('programs')
        ->where('id', $this->programId)
        ->update(['status' => 'completed']);

    $this->postJson(
        $this->url,
        $this->payload,
        ['Idempotency-Key' => $createKey],
    )->assertCreated()->assertExactJson($created);

    $this->patchJson(
        "/api/v1/sessions/{$id}",
        $editPayload,
        ['Idempotency-Key' => $editKey],
    )->assertOk()->assertExactJson($edited);

    $this->assertDatabaseCount('program_sessions', 1);
    $this->assertDatabaseCount('lesson_sessions', 0);
    $this->assertDatabaseCount('audit_logs', 2);
    $this->assertDatabaseCount('idempotency_receipts', 2);
    $this->assertDatabaseCount('notification_outbox', 0);
});

it('canonicalizes key order and equivalent timestamp offsets', function () {
    $key = (string) Str::uuid();

    $created = $this->postJson(
        $this->url,
        $this->payload,
        ['Idempotency-Key' => $key],
    )->assertCreated()->json();

    $equivalent = array_reverse($this->payload, true);

    $equivalent['planned_starts_at'] =
        '2026-11-01T02:00:00.123456Z';

    $equivalent['planned_ends_at'] =
        '2026-11-01T03:00:00.123456Z';

    $this->postJson(
        $this->url,
        $equivalent,
        ['Idempotency-Key' => $key],
    )->assertCreated()->assertExactJson($created);

    $this->postJson(
        $this->url,
        [
            ...$this->payload,
            'title' => 'Different payload',
        ],
        ['Idempotency-Key' => $key],
    )->assertConflict()
        ->assertJsonPath('code', 'idempotency_key_reused');

    $this->assertDatabaseCount('program_sessions', 1);
    $this->assertDatabaseCount('audit_logs', 1);
});

it('checks current authorization before replay', function () {
    $key = (string) Str::uuid();

    $this->postJson(
        $this->url,
        $this->payload,
        ['Idempotency-Key' => $key],
    )->assertCreated();

    $this->actingAs($this->customer, 'web')
        ->postJson(
            $this->url,
            $this->payload,
            ['Idempotency-Key' => $key],
        )->assertForbidden();

    $this->actingAs(TestActors::tutor(), 'web')
        ->postJson(
            $this->url,
            $this->payload,
            ['Idempotency-Key' => $key],
        )->assertNotFound();
});

it('rolls back the planned Session when audit writing fails', function () {
    DB::listen(static function (QueryExecuted $query): void {
        if (str_contains($query->sql, 'insert into "audit_logs"')) {
            throw new RuntimeException('Simulated audit failure.');
        }
    });

    $this->postJson(
        $this->url,
        $this->payload,
        ['Idempotency-Key' => (string) Str::uuid()],
    )->assertStatus(500);

    $this->assertDatabaseCount('program_sessions', 0);
    $this->assertDatabaseCount('lesson_sessions', 0);
    $this->assertDatabaseCount('audit_logs', 0);
    $this->assertDatabaseCount('idempotency_receipts', 0);
});
