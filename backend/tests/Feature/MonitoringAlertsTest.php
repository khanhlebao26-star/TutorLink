<?php

use App\Jobs\SendSlackOperationalAlert;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Http\Client\Request;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class, DatabaseTruncation::class);

beforeEach(function () {
    config([
        'monitoring.alerts.enabled' => true,
        'monitoring.alerts.suppressed' => false,
        'monitoring.alerts.cooldown_seconds' => 600,
        'monitoring.alerts.slow_query_count' => 5,
        'monitoring.alerts.slow_query_window_seconds' => 300,
        'monitoring.slow_query_ms' => 500,
        'services.slack.notifications.bot_user_oauth_token' => 'xoxb-k05-private-token',
        'services.slack.notifications.channel' => 'C12345678',
    ]);
    Http::preventStrayRequests();
});

function k05Query(float $milliseconds = 618, string $sql = 'select ?::text as monitoring_probe'): void
{
    event(new QueryExecuted($sql, ['MONITORING_QUERY_CANARY'], $milliseconds, DB::connection()));
}

function k05Alert(string $kind): SendSlackOperationalAlert
{
    return new SendSlackOperationalAlert((string) Str::uuid(), $kind, [
        'fingerprint' => str_repeat('a', 64),
        'count' => 5,
        'max_ms' => 618,
        'observed_at' => now()->timestamp,
        'request_id' => null,
    ]);
}

function k05Worker(TestCase $test): void
{
    $test->artisan('queue:work', [
        'connection' => 'database', '--queue' => 'slack', '--once' => true, '--sleep' => 0,
    ])->assertExitCode(0)->run();
}

it('detects severe logs and reported exceptions through registered listeners', function (string $level, bool $withException) {
    Log::log($level, 'MONITORING_PRIVATE_CANARY', $withException
        ? ['exception' => new RuntimeException('PRIVATE_EXCEPTION')]
        : []);

    $this->assertDatabaseCount('jobs', 1);
})->with([
    ['critical', false], ['alert', false], ['emergency', false], ['error', true],
]);

it('ignores ordinary logs and its own diagnostic logs', function (string $level, array $context) {
    Log::log($level, 'PRIVATE_LOG', $context);

    $this->assertDatabaseCount('jobs', 0);
})->with([
    ['info', []], ['warning', []], ['error', []], ['critical', ['monitoring_internal' => true]],
]);

it('deduplicates a critical fingerprint until the ten minute cooldown expires', function () {
    $this->freezeTime();
    $exception = new RuntimeException('MONITORING_PRIVATE_CANARY');
    $emit = fn () => Log::critical('PRIVATE_LOG', ['exception' => $exception]);
    $emit();
    $emit();
    $emit();
    $this->assertDatabaseCount('jobs', 1);
    $this->travel(599)->seconds();
    $emit();
    $this->assertDatabaseCount('jobs', 1);
    $this->travel(2)->seconds();
    $emit();
    $this->assertDatabaseCount('jobs', 2);
});

it('separates cooldown markers by environment', function () {
    Log::critical('test');
    config(['app.env' => 'another-test-environment']);
    Log::critical('test');

    $this->assertDatabaseCount('jobs', 2);
});

it('enqueues only one alert when separate processes report the same critical event', function () {
    $code = <<<'PHP'
require 'vendor/autoload.php';
foreach (['TELESCOPE_ENABLED', 'PULSE_ENABLED', 'MONITORING_ALERTS_ENABLED'] as $key) {
    putenv($key.'=false');
    $_ENV[$key] = $_SERVER[$key] = 'false';
}
$app = require 'bootstrap/app.php';
$app->afterBootstrapping(Illuminate\Foundation\Bootstrap\LoadConfiguration::class, function ($app): void {
    Tests\Support\TestDatabaseGuard::assertSafe($app['config']);
});
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Tests\Support\TestDatabaseGuard::assertConnectedIdentity($app['config']);
config(['monitoring.alerts.enabled' => true, 'monitoring.alerts.suppressed' => false]);
Illuminate\Support\Facades\Log::critical('CONCURRENT_PRIVATE_CANARY');
PHP;
    $processes = [];
    try {
        for ($i = 0; $i < 4; $i++) {
            $process = new Process([PHP_BINARY, '-r', $code], base_path());
            $process->start();
            $processes[] = $process;
        }
        foreach ($processes as $process) {
            expect($process->wait())->toBe(0, $process->getErrorOutput());
        }
    } finally {
        foreach ($processes as $process) {
            $process->stop();
        }
    }
    $this->assertDatabaseCount('jobs', 1);
});

it('alerts at the fifth slow query and suppresses repeats during cooldown', function () {
    for ($i = 0; $i < 4; $i++) {
        k05Query();
    }
    $this->assertDatabaseCount('jobs', 0);
    k05Query();
    $this->assertDatabaseCount('jobs', 1);
    for ($i = 0; $i < 3; $i++) {
        k05Query();
    }
    $this->assertDatabaseCount('jobs', 1);

    $payload = DB::table('jobs')->value('payload');
    expect($payload)->not->toContain('MONITORING_QUERY_CANARY', 'select ?', 'xoxb-');
    $job = unserialize(json_decode($payload, true)['data']['command']);
    expect($job->kind)->toBe('slow_query')
        ->and($job->context['count'])->toBe(5)
        ->and($job->context['max_ms'])->toBe(618)
        ->and($job->context['fingerprint'])->toMatch('/^[a-f0-9]{64}$/');
});

it('uses an inclusive 500ms query threshold', function () {
    for ($i = 0; $i < 5; $i++) {
        k05Query(499);
    }
    $this->assertDatabaseCount('jobs', 0);
    for ($i = 0; $i < 5; $i++) {
        k05Query(500);
    }
    $this->assertDatabaseCount('jobs', 1);
});

it('counts only matching queries in a rolling five minute window', function () {
    $this->freezeTime();
    for ($i = 0; $i < 3; $i++) {
        k05Query();
    }
    $this->travel(299)->seconds();
    k05Query();
    $this->travel(2)->seconds();
    for ($i = 0; $i < 3; $i++) {
        k05Query();
    }
    $this->assertDatabaseCount('jobs', 0);
    k05Query();
    $this->assertDatabaseCount('jobs', 1);
    $this->travel(601)->seconds();
    for ($i = 0; $i < 5; $i++) {
        k05Query();
    }
    $this->assertDatabaseCount('jobs', 2);
});

it('does not combine different query fingerprints or monitor its own storage', function () {
    for ($i = 0; $i < 4; $i++) {
        k05Query();
        k05Query(700, 'select ?::text as another_probe');
    }
    foreach (['jobs', 'failed_jobs', 'cache', 'cache_locks', 'pulse_entries', 'telescope_entries'] as $table) {
        for ($i = 0; $i < 5; $i++) {
            k05Query(900, 'select * from '.$table);
        }
    }
    $this->assertDatabaseCount('jobs', 0);
});

it('disables both collectors when alerts are disabled or suppressed', function (string $setting) {
    config([$setting => $setting === 'monitoring.alerts.suppressed']);
    Log::critical('test');
    for ($i = 0; $i < 5; $i++) {
        k05Query();
    }
    $this->assertDatabaseCount('jobs', 0);
})->with(['monitoring.alerts.enabled', 'monitoring.alerts.suppressed']);

it('rolls back the cooldown marker if enqueue fails so the next event can retry', function () {
    $exception = new RuntimeException('PRIVATE_EXCEPTION');
    DB::statement("ALTER TABLE jobs ADD CONSTRAINT k05_block_slack CHECK (queue <> 'slack')");
    try {
        Log::critical('test', ['exception' => $exception]);
        $this->assertDatabaseCount('jobs', 0);
    } finally {
        DB::statement('ALTER TABLE jobs DROP CONSTRAINT k05_block_slack');
    }
    Log::critical('test', ['exception' => $exception]);
    $this->assertDatabaseCount('jobs', 1);
});

it('retains only validated request identifiers in queued critical alerts', function (bool $valid) {
    $requestId = $valid ? (string) Str::uuid() : 'PRIVATE_REQUEST_CANARY';
    request()->attributes->set('request_id', $requestId);
    Log::critical('MONITORING_PRIVATE_CANARY', [
        'email' => 'private@example.test', 'token' => 'PRIVATE_TOKEN_CANARY',
        'exception' => new RuntimeException('PRIVATE_EXCEPTION'),
    ]);
    $payload = DB::table('jobs')->value('payload');
    expect($payload)->not->toContain('MONITORING_PRIVATE_CANARY', 'PRIVATE_EXCEPTION', 'private@example.test', 'PRIVATE_TOKEN_CANARY', 'PRIVATE_REQUEST_CANARY');
    $job = unserialize(json_decode($payload, true)['data']['command']);
    expect($job->context['request_id'])->toBe($valid ? $requestId : null);
})->with([true, false]);

it('sends a safe summary for each new monitoring alert kind', function (string $kind) {
    Http::fake(['https://slack.com/api/chat.postMessage' => Http::response(['ok' => true])]);
    $job = k05Alert($kind)->withFakeQueueInteractions();
    $job->handle();
    $job->assertNotFailed()->assertNotReleased();
    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://slack.com/api/chat.postMessage'
        && str_contains($request['text'], $kind)
        && str_contains($request['text'], 'count=5')
        && str_contains($request['text'], 'fingerprint=')
        && ! str_contains($request['text'], 'xoxb-')
        && $request['mrkdwn'] === false
        && $request['unfurl_links'] === false);
})->with(['critical', 'slow_query']);

it('rejects private or malformed fields before a monitoring job can be serialized', function (array $override) {
    $context = array_replace(k05Alert('critical')->context, $override);
    expect(fn () => new SendSlackOperationalAlert((string) Str::uuid(), 'critical', $context))
        ->toThrow(InvalidArgumentException::class);
})->with([
    [['email' => 'private@example.test']], [['fingerprint' => 'PRIVATE_SQL']],
    [['count' => 0]], [['max_ms' => -1]], [['request_id' => 'PRIVATE_TOKEN']],
]);

it('releases temporary failures but stops at attempt ten', function (bool $lastAttempt) {
    Http::fake(['https://slack.com/api/chat.postMessage' => Http::response([], 503)]);
    $job = k05Alert('critical')->withFakeQueueInteractions();
    $job->job->attempts = $lastAttempt ? 10 : 1;
    $job->handle();
    if ($lastAttempt) {
        $job->assertFailedWith(new RuntimeException('slack_http_temporary_error'))->assertNotReleased();
    } else {
        $job->assertReleased()->assertNotFailed();
        expect($job->job->releaseDelay)->toBeGreaterThanOrEqual(5)->toBeLessThanOrEqual(10);
    }
    Http::assertSentCount(1);
})->with([false, true]);

it('prevents recursive critical and slow-query alerts during an actual worker retry', function () {
    Http::fake(['https://slack.com/api/chat.postMessage' => function () {
        Log::critical('PRIVATE_SLACK_FAILURE', ['exception' => new RuntimeException('PRIVATE_SLACK_CANARY')]);
        for ($i = 0; $i < 5; $i++) {
            k05Query();
        }

        return Http::response([], 503);
    }]);
    Log::critical('test');
    k05Worker($this);

    Http::assertSentCount(1);
    $this->assertDatabaseCount('jobs', 1);
    $this->assertDatabaseHas('jobs', ['queue' => 'slack', 'attempts' => 1]);
    $this->assertDatabaseCount('failed_jobs', 0);
});

it('prevents recursive alerts when the actual Slack worker permanently fails', function () {
    Http::fake(['https://slack.com/api/chat.postMessage' => Http::response([
        'ok' => false, 'error' => 'invalid_auth', 'private' => 'PRIVATE_RESPONSE_CANARY',
    ])]);
    Event::listen(JobFailed::class, function (): void {
        Log::critical('PRIVATE_JOB_FAILURE', ['exception' => new RuntimeException('PRIVATE_SLACK_CANARY')]);
    });
    Log::critical('test');
    k05Worker($this);

    Http::assertSentCount(1);
    $this->assertDatabaseCount('jobs', 0);
    $this->assertDatabaseCount('failed_jobs', 1);
    $failure = DB::table('failed_jobs')->first();
    expect($failure->exception)->toContain('slack_invalid_auth')
        ->not->toContain('xoxb-', 'PRIVATE_RESPONSE_CANARY', 'PRIVATE_SLACK_CANARY');
    expect($failure->payload)->not->toContain('xoxb-', 'PRIVATE_RESPONSE_CANARY', 'PRIVATE_SLACK_CANARY');
});

it('resets the Slack guard before the worker starts its next loop', function () {
    $job = Mockery::mock(Job::class);
    $job->shouldReceive('getQueue')->once()->andReturn('slack');
    $job->shouldReceive('payload')->andReturn([]);
    event(new JobProcessing('database', $job));
    Log::critical('test');
    $this->assertDatabaseCount('jobs', 0);

    event(new Looping('database', 'default'));
    Log::critical('test');
    $this->assertDatabaseCount('jobs', 1);
});

it('respects Slack rate limiting for monitoring alerts', function () {
    Http::fake(['https://slack.com/api/chat.postMessage' => Http::response([], 429, ['Retry-After' => '420'])]);
    $job = k05Alert('slow_query')->withFakeQueueInteractions();
    $job->handle();
    $job->assertReleased(420)->assertNotFailed();
    Http::assertSentCount(1);
});
