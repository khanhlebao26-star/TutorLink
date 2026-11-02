<?php

use App\Http\Middleware\EnsureActiveApiActor;
use App\Http\Requests\CancelSessionRequest;
use App\Http\Requests\CompleteSessionRequest;
use App\Http\Requests\CreateSessionRequest;
use App\Http\Requests\UpdateSessionRequest;
use App\Models\Program;
use App\Models\ProgramSession;
use App\Policies\ProgramSessionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\Support\ProgramApiFixtures;
use Tests\Support\TestActors;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->tutor = TestActors::tutor();
    $this->customer = TestActors::customer();

    $programId = ProgramApiFixtures::draft(
        $this->tutor,
        $this->customer,
        ['status' => 'active'],
    );

    ProgramApiFixtures::addMember(
        $programId,
        $this->customer,
        'customer',
    );

    $this->program = Program::query()->findOrFail($programId);

    $this->planned = ProgramSession::query()->forceCreate([
        'program_id' => $programId,
        'program_version' => 1,
        'sequence_number' => 1,
        'title' => 'English lesson',
        'planned_starts_at' => '2026-11-01 02:00:00+00:00',
        'planned_ends_at' => '2026-11-01 03:00:00+00:00',
        'timezone' => 'Asia/Ho_Chi_Minh',
        'delivery_mode' => 'online',
        'status' => 'scheduled',
        'version' => 1,
    ]);

    $this->policy = new ProgramSessionPolicy;

    $this->headers = [
        'Idempotency-Key' => (string) Str::uuid(),
    ];

    $this->payload = [
        'title' => 'English lesson',
        'planned_starts_at' => '2026-11-01T09:00:00+07:00',
        'planned_ends_at' => '2026-11-01T10:00:00+07:00',
        'delivery_mode' => 'online',
        'meeting_url' => 'https://example.com/meeting',
    ];

    $this->createUrl =
        "/api/_k04/programs/{$programId}/sessions";

    $this->sessionUrl =
        "/api/_k04/sessions/{$this->planned->id}";

    Route::middleware(EnsureActiveApiActor::class)
        ->group(function (): void {
            Route::post(
                '/api/_k04/programs/{program}/sessions',
                fn (CreateSessionRequest $request) => response()->json([
                    'data' => $request->validated(),
                ]),
            )->whereNumber('program');

            Route::patch(
                '/api/_k04/sessions/{session}',
                fn (UpdateSessionRequest $request) => response()->json([
                    'data' => $request->validated(),
                ]),
            )->whereNumber('session');

            Route::post(
                '/api/_k04/sessions/{session}/complete',
                fn (CompleteSessionRequest $request) => response()->json([
                    'data' => $request->validated(),
                ]),
            )->whereNumber('session');

            Route::post(
                '/api/_k04/sessions/{session}/cancel',
                fn (CancelSessionRequest $request) => response()->json([
                    'data' => $request->validated(),
                ]),
            )->whereNumber('session');
        });
});

it('allows reading only for the owner and current member', function () {
    $outsider = TestActors::tutor();

    expect($this->policy->view($this->tutor, $this->planned))
        ->toBeTrue()
        ->and($this->policy->view($this->customer, $this->planned))
        ->toBeTrue()
        ->and($this->policy->view($outsider, $this->planned))
        ->toBeFalse();

    DB::table('program_members')
        ->where('program_id', $this->program->id)
        ->where('user_id', $this->customer->id)
        ->delete();

    expect($this->policy->view($this->customer, $this->planned))
        ->toBeFalse();
});

it('applies the Program state matrix', function (string $status, bool $canCreateOrEdit, bool $canCompleteOrCancel) {
    $this->program->status = $status;
    $this->planned->setRelation('program', $this->program);

    expect($this->policy->create($this->tutor, $this->program))
        ->toBe($canCreateOrEdit)
        ->and($this->policy->update($this->tutor, $this->planned))
        ->toBe($canCreateOrEdit)
        ->and($this->policy->complete($this->tutor, $this->planned))
        ->toBe($canCompleteOrCancel)
        ->and($this->policy->cancel($this->tutor, $this->planned))
        ->toBe($canCompleteOrCancel)
        ->and($this->policy->mutate($this->tutor, $this->program))
        ->toBeTrue();
})->with([
    'active' => ['active', true, true],
    'paused' => ['paused', false, true],
    'completed' => ['completed', false, false],
    'cancelled' => ['cancelled', false, false],
    'draft' => ['draft', false, false],
]);

it('blocks new mutations on terminal Sessions', function (string $status) {
    $this->planned->status = $status;

    expect($this->policy->update($this->tutor, $this->planned))
        ->toBeFalse()
        ->and($this->policy->complete($this->tutor, $this->planned))
        ->toBeFalse()
        ->and($this->policy->cancel($this->tutor, $this->planned))
        ->toBeFalse();
})->with(['completed', 'cancelled']);

it('rejects unauthenticated, outsider and member mutations', function () {
    $this->postJson(
        $this->createUrl,
        $this->payload,
        $this->headers,
    )->assertUnauthorized();

    $this->actingAs(TestActors::tutor(), 'web')
        ->postJson(
            $this->createUrl,
            $this->payload,
            $this->headers,
        )->assertNotFound();

    $this->actingAs($this->customer, 'web')
        ->postJson(
            $this->createUrl,
            $this->payload,
            $this->headers,
        )->assertForbidden();
});

it('rejects inactive actors', function () {
    $this->tutor->status = 'suspended';
    $this->tutor->save();

    $this->actingAs($this->tutor, 'web')
        ->postJson(
            $this->createUrl,
            $this->payload,
            $this->headers,
        )->assertForbidden();
});

it('accepts a valid schedule and requires an idempotency header', function () {
    $this->actingAs($this->tutor, 'web')
        ->postJson(
            $this->createUrl,
            $this->payload,
            $this->headers,
        )->assertOk();

    $this->postJson(
        $this->createUrl,
        $this->payload,
    )->assertUnprocessable()
        ->assertJsonValidationErrors('Idempotency-Key');
});

it('rejects invalid schedule data', function (array $changes) {
    $this->actingAs($this->tutor, 'web')
        ->postJson(
            $this->createUrl,
            array_replace($this->payload, $changes),
            $this->headers,
        )->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed');
})->with([
    'blank title' => [['title' => '   ']],
    'missing offset' => [
        [
            'planned_starts_at' => '2026-11-01T09:00:00',
        ],
    ],
    'invalid date' => [
        [
            'planned_starts_at' => '2026-02-30T09:00:00+07:00',
        ],
    ],
    'equal times' => [
        [
            'planned_ends_at' => '2026-11-01T09:00:00+07:00',
        ],
    ],
    'end before start' => [
        [
            'planned_ends_at' => '2026-11-01T08:00:00+07:00',
        ],
    ],
    'http URL' => [
        [
            'meeting_url' => 'http://example.com/meeting',
        ],
    ],
    'URL credentials' => [
        [
            'meeting_url' => 'https://user:password@example.com/meeting',
        ],
    ],
    'long URL' => [
        [
            'meeting_url' => 'https://example.com/'.str_repeat('a', 2048),
        ],
    ],
    'server-owned status' => [['status' => 'completed']],
    'client timezone' => [['timezone' => 'UTC']],
]);

it('requires an integer expected version for edit and actions', function () {
    $this->actingAs($this->tutor, 'web');

    foreach ([null, 0, -1, '1', true, 1.5] as $version) {
        $this->patchJson(
            $this->sessionUrl,
            [
                'expected_version' => $version,
                'title' => 'Updated lesson',
            ],
            $this->headers,
        )->assertUnprocessable()
            ->assertJsonValidationErrors('expected_version');

        foreach (['complete', 'cancel'] as $action) {
            $this->postJson(
                "{$this->sessionUrl}/{$action}",
                ['expected_version' => $version],
                $this->headers,
            )->assertUnprocessable()
                ->assertJsonValidationErrors('expected_version');
        }
    }
});

it('accepts a partial edit and rejects an edit without content fields', function () {
    $this->actingAs($this->tutor, 'web')
        ->patchJson(
            $this->sessionUrl,
            [
                'expected_version' => 1,
                'planned_ends_at' => '2026-11-01T10:30:00+07:00',
            ],
            $this->headers,
        )->assertOk();

    $this->patchJson(
        $this->sessionUrl,
        ['expected_version' => 1],
        $this->headers,
    )->assertUnprocessable()
        ->assertJsonValidationErrors('session');
});

it('allows only the contracted complete and cancel payloads', function () {
    $this->actingAs($this->tutor, 'web')
        ->postJson(
            "{$this->sessionUrl}/complete",
            ['expected_version' => 1],
            $this->headers,
        )->assertOk();

    $this->postJson(
        "{$this->sessionUrl}/complete",
        [
            'expected_version' => 1,
            'started_at' => '2026-11-01T09:00:00+07:00',
        ],
        $this->headers,
    )->assertUnprocessable()
        ->assertJsonValidationErrors('started_at');

    $this->postJson(
        "{$this->sessionUrl}/cancel",
        [
            'expected_version' => 1,
            'reason' => null,
        ],
        $this->headers,
    )->assertOk();

    $this->postJson(
        "{$this->sessionUrl}/cancel",
        [
            'expected_version' => 1,
            'reason' => str_repeat('a', 2001),
        ],
        $this->headers,
    )->assertUnprocessable()
        ->assertJsonValidationErrors('reason');
});
