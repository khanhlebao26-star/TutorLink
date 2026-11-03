<?php

use App\Models\User;
use App\Support\NotificationOutboxWriter;
use App\Support\WorkerHeartbeat;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, DatabaseTruncation::class);

function k05Runtime(): array
{
    $exit = Artisan::call('monitoring:runtime');

    return [$exit, json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR)];
}

function k05HealthyWorkers(): void
{
    foreach (WorkerHeartbeat::PROCESSES as $process) {
        app(WorkerHeartbeat::class)->beat($process);
    }
}

it('reports absent workers and recovers after heartbeats arrive', function () {
    [$exit, $state] = k05Runtime();
    expect($exit)->toBe(1);
    expect($state['workers']['outbox-worker']['status'])->toBe('missing');
    k05HealthyWorkers();
    [$exit, $state] = k05Runtime();
    expect($exit)->toBe(0);
    expect($state['status'])->toBe('ok');
});

it('marks a worker stale only after ninety seconds and recovers on progress', function () {
    $this->freezeTime();
    k05HealthyWorkers();
    $this->travel(90)->seconds();
    [$exit] = k05Runtime();
    expect($exit)->toBe(0);
    $this->travel(1)->seconds();
    [$exit, $state] = k05Runtime();
    expect($exit)->toBe(1);
    expect($state['workers']['slack-worker']['status'])->toBe('stale');
    expect($state['workers']['slack-worker']['age_seconds'])->toBe(91);
    k05HealthyWorkers();
    [$exit] = k05Runtime();
    expect($exit)->toBe(0);
});

it('writes idle queue heartbeats without requiring a job', function (string $queue, string $process) {
    event(new Looping('database', $queue));

    expect(app(WorkerHeartbeat::class)->snapshot()[$process]['status'])->toBe('ok');
    $this->assertDatabaseCount('jobs', 0);
})->with([['slack', 'slack-worker'], ['broadcasts', 'broadcast-worker']]);

it('writes an outbox heartbeat when the worker has no work', function () {
    $this->artisan('outbox:work', ['--once' => true])->assertExitCode(0)->run();

    expect(app(WorkerHeartbeat::class)->snapshot()['outbox-worker']['status'])->toBe('ok');
});

it('writes scheduler heartbeats through its own scheduled callback', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => $event->description === 'monitoring:heartbeat:scheduler');
    expect($event)->not->toBeNull();
    $event->run(app());

    expect(app(WorkerHeartbeat::class)->snapshot()['scheduler']['status'])->toBe('ok');
});

it('limits heartbeat writes to thirty seconds and detects a future clock', function () {
    $this->freezeTime();
    $heartbeat = app(WorkerHeartbeat::class);
    $heartbeat->beat('slack-worker');
    $this->travel(29)->seconds();
    $heartbeat->beat('slack-worker');
    expect($heartbeat->snapshot()['slack-worker']['age_seconds'])->toBe(29);
    $this->travel(1)->seconds();
    $heartbeat->beat('slack-worker');
    expect($heartbeat->snapshot()['slack-worker']['age_seconds'])->toBe(0);
    $this->travel(-1)->seconds();
    expect($heartbeat->snapshot()['slack-worker']['status'])->toBe('clock_skew');
});

it('does not accept an unrecognized heartbeat identity', function () {
    expect(app(WorkerHeartbeat::class)->beat('PRIVATE_WORKER_NAME'))->toBeFalse();
    $this->assertDatabaseCount('cache', 0);
});

it('detects pending outbox lag and distinguishes failed and replayable records', function () {
    $this->freezeTime();
    k05HealthyWorkers();
    $recipient = User::factory()->create();
    DB::transaction(fn () => app(NotificationOutboxWriter::class)->record(
        'tutor_profile_approved',
        'tutor_profile',
        17,
        'k05-runtime-test',
        [$recipient->id],
        ['deep_link' => '/profile'],
    ));
    DB::table('notification_outbox')->update(['created_at' => now()->subSeconds(300)]);
    [$exit] = k05Runtime();
    expect($exit)->toBe(0);
    DB::table('notification_outbox')->update(['created_at' => now()->subSeconds(301)]);
    [$exit, $state] = k05Runtime();
    expect($exit)->toBe(1);
    expect($state['outbox']['oldest_pending_seconds'])->toBe(301);
    DB::table('notification_outbox')->update(['failed_at' => now()]);
    [, $state] = k05Runtime();
    expect($state['outbox'])->toBe(['pending' => 0, 'oldest_pending_seconds' => 0, 'failed_unresolved' => 1]);
    DB::table('notification_outbox')->update(['replay_requested_at' => now()->addSecond()]);
    [, $state] = k05Runtime();
    expect($state['outbox']['pending'])->toBe(1);
    expect($state['outbox']['failed_unresolved'])->toBe(0);
});

it('reports queue counts without exposing payloads or generating Slack alerts', function () {
    Http::preventStrayRequests();
    k05HealthyWorkers();
    foreach (['slack', 'broadcasts'] as $queue) {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => $queue,
            'payload' => 'PRIVATE_PAYLOAD_CANARY',
            'exception' => 'PRIVATE_TOKEN_CANARY',
            'failed_at' => now(),
        ]);
    }
    DB::table('jobs')->insert([
        'queue' => 'slack',
        'payload' => 'PRIVATE_QUEUE_CANARY',
        'attempts' => 0,
        'reserved_at' => null,
        'available_at' => now()->timestamp,
        'created_at' => now()->timestamp,
    ]);
    [$exit, $state] = k05Runtime();
    expect($exit)->toBe(1);
    expect($state['queue'])->toBe(['waiting_or_reserved' => ['slack' => 1], 'failed_total' => 2, 'failed_slack' => 1]);
    expect(json_encode($state))->not->toContain('CANARY');
    $this->assertDatabaseCount('jobs', 1);
    Http::assertNothingSent();
});

it('reports unavailable metrics safely and lets workers survive heartbeat storage failure', function () {
    DB::statement('ALTER TABLE cache RENAME TO k05_unavailable_cache');
    try {
        expect(app(WorkerHeartbeat::class)->beat('slack-worker'))->toBeFalse();
        [$exit, $state] = k05Runtime();
        expect($exit)->toBe(2);
        expect($state)->toBe(['status' => 'unknown', 'error_code' => 'runtime_metrics_unavailable']);
    } finally {
        DB::statement('ALTER TABLE k05_unavailable_cache RENAME TO cache');
    }
});
