<?php

use App\Support\WorkerHeartbeat;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, DatabaseTruncation::class);

it('reports the application alive without using database sessions', function () {
    config([
        'session.table' => 'k05_sessions_must_not_be_used',
    ]);
    $this->get('/up')->assertOk();
    $this->getJson('/health/ready')->assertStatus(503)
        ->assertJsonPath('app', 'ok')->assertJsonPath('db', 'ok')
        ->assertJsonPath('workers.slack-worker', 'missing');
});

it('is ready only when the database and every required worker are healthy', function () {
    foreach (WorkerHeartbeat::PROCESSES as $process) {
        app(WorkerHeartbeat::class)->beat($process);
    }
    $this->getJson('/health/ready')->assertOk()
        ->assertJsonPath('status', 'ready')
        ->assertJsonPath('workers.scheduler', 'ok')
        ->assertHeader('Cache-Control', 'no-store, private');
});

it('reports only the stale worker while the other components remain healthy', function () {
    $this->freezeTime();
    app(WorkerHeartbeat::class)->beat('slack-worker');
    $this->travel(91)->seconds();
    foreach (['outbox-worker', 'broadcast-worker', 'scheduler'] as $process) {
        app(WorkerHeartbeat::class)->beat($process);
    }
    $this->getJson('/health/ready')->assertStatus(503)
        ->assertJsonPath('app', 'ok')->assertJsonPath('db', 'ok')
        ->assertJsonPath('workers.slack-worker', 'stale')
        ->assertJsonPath('workers.outbox-worker', 'ok');
});

it('reports database failure without exposing connection details or failing liveness', function () {
    $original = config('database.default');

    config([
        'database.connections.health-unavailable' => array_replace(
            config("database.connections.{$original}"),
            [
                'host' => '127.0.0.1',
                'port' => 1,
                'url' => null,
            ],
        ),
        'database.default' => 'health-unavailable',
    ]);

    try {
        $this->getJson('/up')->assertOk();

        $response = $this->getJson('/health/ready')
            ->assertStatus(503)
            ->assertJsonPath('app', 'ok')
            ->assertJsonPath('db', 'down')
            ->assertJsonPath(
                'workers.scheduler',
                'unknown',
            );

        expect($response->getContent())
            ->not->toContain(
                'health-unavailable',
                'password',
                'SQLSTATE',
                'stack',
            );
    } finally {
        config(['database.default' => $original]);
        DB::purge('health-unavailable');
    }
});

it('reports worker storage failure separately from a healthy database', function () {
    DB::statement('ALTER TABLE cache RENAME TO k05_health_cache_unavailable');
    try {
        $this->getJson('/health/ready')->assertStatus(503)
            ->assertJsonPath('db', 'ok')->assertJsonPath('workers.outbox-worker', 'unknown');
    } finally {
        DB::statement('ALTER TABLE k05_health_cache_unavailable RENAME TO cache');
    }
});
