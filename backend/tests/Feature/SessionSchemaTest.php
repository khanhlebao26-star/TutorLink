<?php

use App\Models\LessonSession;
use App\Models\Program;
use App\Models\ProgramSession;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\ProgramApiFixtures;
use Tests\Support\TestActors;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/** @param array<string, mixed> $overrides */
function createK04PlannedSession(array $overrides = []): ProgramSession
{
    $tutor = TestActors::tutor();
    $customer = TestActors::customer();

    $programId = ProgramApiFixtures::draft(
        $tutor,
        $customer,
        ['status' => 'active'],
    );

    $startsAt = CarbonImmutable::parse(
        '2026-11-01T09:00:00+07:00',
    )->utc();

    $sessionId = DB::table('program_sessions')->insertGetId(
        array_replace([
            'program_id' => $programId,
            'program_version' => 1,
            'sequence_number' => 1,
            'title' => 'English lesson',
            'planned_starts_at' => $startsAt,
            'planned_ends_at' => $startsAt->addHour(),
            'timezone' => 'Asia/Ho_Chi_Minh',
            'delivery_mode' => 'online',
            'location_text' => null,
            'meeting_url' => null,
            'created_at' => $startsAt,
            'updated_at' => $startsAt,
        ], $overrides),
    );

    return ProgramSession::query()->findOrFail($sessionId);
}

it('defaults a planned Session to scheduled with version one', function () {
    $planned = createK04PlannedSession();

    expect($planned->status)->toBe('scheduled')
        ->and($planned->version)->toBe(1)
        ->and($planned->terminal_replay)->toBeNull()
        ->and($planned->planned_starts_at)
        ->toBeInstanceOf(CarbonImmutable::class)
        ->and($planned->planned_starts_at->utc()->format('H:i:s'))
        ->toBe('02:00:00')
        ->and($planned->actual)->toBeNull();

    $this->assertDatabaseCount('lesson_sessions', 0);
});

it('connects Program, planned Session and actual in both directions', function () {
    $planned = createK04PlannedSession([
        'status' => 'completed',
    ]);

    $actualId = DB::table('lesson_sessions')->insertGetId([
        'program_session_id' => $planned->id,
        'status' => 'completed',
    ]);

    $actual = LessonSession::query()->findOrFail($actualId);
    $program = Program::query()->findOrFail($planned->program_id);

    expect($planned->program->is($program))->toBeTrue()
        ->and($program->sessions()->first()->is($planned))->toBeTrue()
        ->and($planned->actual->is($actual))->toBeTrue()
        ->and($actual->plannedSession->is($planned))->toBeTrue()
        ->and($actual->started_at)->toBeNull()
        ->and($actual->ended_at)->toBeNull();
});

it('rejects a second actual for the same planned Session', function () {
    $planned = createK04PlannedSession([
        'status' => 'completed',
    ]);

    DB::table('lesson_sessions')->insert([
        'program_session_id' => $planned->id,
        'status' => 'completed',
    ]);

    DB::table('lesson_sessions')->insert([
        'program_session_id' => $planned->id,
        'status' => 'completed',
    ]);
})->throws(QueryException::class);

it('rejects an actual referencing a missing planned Session', function () {
    $planned = createK04PlannedSession();

    DB::table('lesson_sessions')->insert([
        'program_session_id' => $planned->id + 1000000,
        'status' => 'completed',
    ]);
})->throws(QueryException::class);

it('rejects invalid planned Session statuses on PostgreSQL', function (string $status) {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped(
            'The domain status constraint is enforced on PostgreSQL.',
        );
    }

    $planned = createK04PlannedSession();

    DB::table('program_sessions')
        ->where('id', $planned->id)
        ->update(['status' => $status]);
})->with([
    'planned',
    'in_progress',
    'no_show',
])->throws(QueryException::class);

it('rejects nonpositive Session versions on PostgreSQL', function (int $version) {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped(
            'The positive version constraint is enforced on PostgreSQL.',
        );
    }

    $planned = createK04PlannedSession();

    DB::table('program_sessions')
        ->where('id', $planned->id)
        ->update(['version' => $version]);
})->with([
    0,
    -1,
])->throws(QueryException::class);

it('stores terminal replay metadata without serializing it', function () {
    $planned = createK04PlannedSession();

    $metadata = [
        'actor_id' => (int) $planned->program->ownerTutorProfile->user_id,
        'action' => 'cancel',
        'payload' => [
            'expected_version' => 1,
            'reason' => null,
        ],
        'response_status' => 200,
        'response_snapshot' => [
            'data' => [
                'id' => $planned->id,
                'status' => 'cancelled',
                'version' => 2,
                'actual' => null,
            ],
        ],
    ];

    $planned->forceFill([
        'status' => 'cancelled',
        'version' => 2,
        'terminal_replay' => $metadata,
    ])->save();

    $reloaded = $planned->fresh();

    expect($reloaded->terminal_replay)->toEqual($metadata)
        ->and($reloaded->toArray())
        ->not->toHaveKey('terminal_replay');
});

it('does not allow mass assignment of server-managed Session fields', function () {
    $planned = new ProgramSession;

    foreach ([
        'id',
        'program_id',
        'program_version',
        'sequence_number',
        'timezone',
        'status',
        'version',
        'terminal_replay',
    ] as $field) {
        expect($planned->isFillable($field))->toBeFalse();
    }

    $actual = new LessonSession;

    expect($actual->isFillable('program_session_id'))->toBeFalse()
        ->and($actual->isFillable('status'))->toBeFalse();
});

it('hides legacy attendance fields on actual serialization', function () {
    $actual = new LessonSession;

    $actual->forceFill([
        'tutor_joined_at' => '2026-11-01 02:00:00+00:00',
        'customer_joined_at' => '2026-11-01 02:01:00+00:00',
    ]);

    expect($actual->toArray())
        ->not->toHaveKey('tutor_joined_at')
        ->not->toHaveKey('customer_joined_at');
});
