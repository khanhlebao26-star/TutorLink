<?php

use App\Actions\Auth\RevokeUserSessions;
use App\Models\User;
use App\Support\AdminPermissions;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Laravel\Pulse\Facades\Pulse;
use Laravel\Pulse\Livewire\Exceptions;
use Laravel\Pulse\Livewire\SlowQueries;
use Laravel\Pulse\Livewire\SlowRequests;
use Laravel\Pulse\Recorders\SlowRequests as SlowRequestsRecorder;
use Laravel\Pulse\Support\CacheStoreResolver;
use Laravel\Telescope\Contracts\EntriesRepository;
use Laravel\Telescope\Telescope;
use Livewire\Livewire;
use Tests\Support\InteractsWithRealtimeSessions;
use Tests\Support\MonitoringTestCase;
use Tests\Support\TestActors;

uses(MonitoringTestCase::class, RefreshDatabase::class, InteractsWithRealtimeSessions::class);

it('returns 401 to guests even in local environment', function (string $path) {
    config(['app.env' => 'local']);

    $this->getJson($path)->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
})->with(['/telescope', '/telescope/telescope-api/requests', '/pulse']);

it('enforces the complete monitoring permission matrix', function (string $role, string $status, bool $permission, bool $deleted, bool $allowed) {
    $actor = TestActors::user([
        'role' => $role,
        'status' => $status,
        'permissions' => $permission ? [AdminPermissions::VIEW_MONITORING] : [],
    ]);
    if ($deleted) {
        $actor->delete();
    }
    config(['app.env' => 'local']);

    expect([
        Gate::forUser($actor)->allows('viewMonitoring'),
        Gate::forUser($actor)->allows('viewTelescope'),
        Gate::forUser($actor)->allows('viewPulse'),
    ])->toBe([$allowed, $allowed, $allowed]);
})->with([
    'active admin with permission' => ['admin', 'active', true, false, true],
    'admin without permission' => ['admin', 'active', false, false, false],
    'suspended admin' => ['admin', 'suspended', true, false, false],
    'deleted admin' => ['admin', 'active', true, true, false],
    'customer with forged permission' => ['customer', 'active', true, false, false],
    'tutor with forged permission' => ['tutor', 'active', true, false, false],
]);

it('returns 403 to authenticated customers at both dashboards', function (string $path) {
    $this->configureRealtimeTests();
    $cookies = $this->loginBrowser(TestActors::customer());

    $this->browserRequest($cookies, 'GET', $path)->assertForbidden();
})->with(['/telescope', '/telescope/telescope-api/requests', '/pulse']);

it('renders both dashboards for an active permitted admin', function (string $path) {
    $this->configureRealtimeTests();
    $cookies = $this->loginBrowser(TestActors::admin([AdminPermissions::VIEW_MONITORING]));

    $this->browserRequest($cookies, 'GET', $path)->assertOk();
})->with(['/telescope', '/telescope/telescope-api/requests', '/pulse']);

it('rejects a revoked browser session at both dashboards', function (string $path) {
    $this->configureRealtimeTests();
    $admin = TestActors::admin([AdminPermissions::VIEW_MONITORING]);
    $cookies = $this->loginBrowser($admin);
    app(RevokeUserSessions::class)->handle($admin);

    $this->browserRequest($cookies, 'GET', $path)->assertUnauthorized();
})->with(['/telescope', '/telescope/telescope-api/requests', '/pulse']);

it('rechecks authorization on an existing Pulse Livewire snapshot', function (Closure $change, int $status) {
    $this->configureRealtimeTests();
    Livewire::withoutLazyLoading();
    $admin = TestActors::admin([AdminPermissions::VIEW_MONITORING]);
    $cookies = $this->loginBrowser($admin);
    $page = $this->browserRequest($cookies, 'GET', '/pulse')->assertOk();
    preg_match_all('/wire:snapshot="([^"]+)"/', $page->getContent(), $matches);
    $snapshot = collect($matches[1])->map(
        fn (string $value): string => html_entity_decode($value, ENT_QUOTES | ENT_HTML5),
    )->first(fn (string $value): bool => json_decode($value, true)['memo']['name'] === 'pulse.slow-queries');
    expect($snapshot)->toBeString();
    $route = collect(Route::getRoutes()->getRoutes())->first(
        fn ($route): bool => in_array('POST', $route->methods(), true)
            && str_contains($route->uri(), 'livewire')
            && str_ends_with($route->uri(), '/update'),
    );
    expect($route)->not->toBeNull();
    $payload = ['components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => []]]];
    $this->withHeader('X-Livewire', 'true');
    $this->browserRequest($cookies, 'POST', '/'.$route->uri(), $payload)->assertOk();

    $change($admin);
    Livewire::flushState();

    $this->browserRequest($cookies, 'POST', '/'.$route->uri(), $payload)->assertStatus($status);
})->with([
    'permission removed' => [fn (User $user) => $user->forceFill(['permissions' => []])->save(), 403],
    'account suspended' => [fn (User $user) => $user->forceFill(['status' => 'suspended'])->save(), 403],
    'session revoked' => [fn (User $user) => app(RevokeUserSessions::class)->handle($user), 401],
]);

it('persists sanitized request query and exception metrics without canary data', function () {
    $this->freezeTime();
    $canary = 'MONITORING_PRIVATE_CANARY';
    $this->actingAs(TestActors::admin());
    Route::post('/_test/monitoring/{id}', function () use ($canary) {
        event(new QueryExecuted('select ?::text as private_value', [$canary], 618, DB::connection()));
        report(new RuntimeException($canary));
        $response = response()->json(['private' => ['token' => $canary]])
            ->header('X-Private-Response', $canary);
        app(SlowRequestsRecorder::class)->record(Carbon::now()->subMilliseconds(1394), request(), $response);

        return $response;
    })->middleware('web');

    $this->withCookie('private_cookie', $canary)->postJson(
        '/_test/monitoring/private-id?email='.$canary,
        ['profile' => ['email' => $canary, 'password' => $canary]],
        ['Authorization' => 'Bearer '.$canary, 'X-Private-Header' => $canary],
    )->assertOk();
    Telescope::store(app(EntriesRepository::class));
    Pulse::ingest();

    foreach (['request', 'query', 'exception'] as $type) {
        $this->assertDatabaseHas('telescope_entries', ['type' => $type]);
    }
    foreach (['slow_request', 'slow_query', 'exception'] as $type) {
        $this->assertDatabaseHas('pulse_entries', ['type' => $type]);
    }
    $request = json_decode(DB::table('telescope_entries')->where('type', 'request')->latest('sequence')->value('content'), true);
    expect($request['uri'])->toBe('/_test/monitoring/{id}');
    expect($request)->not->toHaveKey('user');
    foreach (['headers', 'payload', 'session', 'response_headers'] as $field) {
        expect($request[$field])->toBe([]);
    }
    expect($request['response'])->toBe('[REDACTED]');
    $query = json_decode(DB::table('telescope_entries')->where('type', 'query')->latest('sequence')->value('content'), true);
    expect($query['sql'])->toStartWith('query:');
    expect($query['bindings'])->toBe([]);
    $exception = json_decode(DB::table('telescope_entries')->where('type', 'exception')->latest('sequence')->value('content'), true);
    expect($exception['message'])->toBe('[REDACTED]');
    foreach (['context', 'trace', 'line_preview'] as $field) {
        expect($exception[$field])->toBe([]);
    }
    foreach ([
        'telescope_entries' => ['content'],
        'telescope_entries_tags' => ['tag'],
        'pulse_entries' => ['key'],
        'pulse_aggregates' => ['key'],
        'pulse_values' => ['key', 'value'],
    ] as $table => $columns) {
        foreach ($columns as $column) {
            expect(DB::table($table)->pluck($column)->implode(' '))->not->toContain($canary);
        }
    }
    expect(DB::table('telescope_entries_tags')->where('tag', 'like', 'Auth:%')->exists())->toBeFalse();
});

it('samples ordinary HTTP requests at the configured zero and one boundaries', function (float $rate, int $expected) {
    config(['monitoring.request_sample_rate' => $rate]);
    Route::get('/_test/sampling', fn () => response()->json(['ok' => true]));

    $this->getJson('/_test/sampling')->assertOk();
    Telescope::store(app(EntriesRepository::class));

    expect(DB::table('telescope_entries')->where('type', 'request')->count())->toBe($expected);
})->with([
    'disabled ordinary recording' => [0.0, 0],
    'all ordinary requests' => [1.0, 1],
]);

it('retains failed requests exceptions and slow queries when ordinary sampling is zero', function () {
    config(['monitoring.request_sample_rate' => 0]);
    Route::get('/_test/critical', function () {
        event(new QueryExecuted('select ?::text', ['MONITORING_PRIVATE_CANARY'], 618, DB::connection()));
        report(new RuntimeException('MONITORING_PRIVATE_CANARY'));

        return response()->json(['message' => 'synthetic failure'], 500);
    });

    $this->getJson('/_test/critical')->assertServerError();
    Telescope::store(app(EntriesRepository::class));
    Pulse::ingest();

    foreach (['request', 'exception', 'query'] as $type) {
        $this->assertDatabaseHas('telescope_entries', ['type' => $type]);
    }
    foreach (['slow_query', 'exception'] as $type) {
        $this->assertDatabaseHas('pulse_entries', ['type' => $type]);
    }
});

it('renders all Pulse cards repeatedly through the database cache', function () {
    $this->freezeTime();
    config(['pulse.cache' => 'database']);
    $this->actingAs(TestActors::admin([AdminPermissions::VIEW_MONITORING]));
    Pulse::record('slow_query', json_encode(['select ?::text', null]), 618)->max()->count();
    Pulse::record('slow_request', json_encode(['GET', '/example', null]), 1394)->max()->count();
    Pulse::record('exception', json_encode([RuntimeException::class, null]), now()->timestamp)->max()->count();
    Pulse::ingest();

    foreach ([SlowQueries::class, SlowRequests::class, Exceptions::class] as $component) {
        Livewire::test($component, ['lazy' => false])->assertOk();
        Livewire::test($component, ['lazy' => false])->assertOk();
    }

    $cached = app(CacheStoreResolver::class)->store()->get('laravel:pulse:'.Exceptions::class.':count:1_hour');
    expect($cached[0])->toBeInstanceOf(Collection::class);
    expect($cached[0]->first())->toBeInstanceOf(stdClass::class);
    expect($cached[0]->first()->latest)->toBeInstanceOf(CarbonImmutable::class);
});

it('prunes expired monitoring data and keeps recent records with an hourly schedule', function () {
    $this->freezeTime();
    $oldUuid = (string) Str::uuid();
    $newUuid = (string) Str::uuid();
    $batch = (string) Str::uuid();
    DB::table('telescope_entries')->insert([
        ['uuid' => $oldUuid, 'batch_id' => $batch, 'type' => 'request', 'content' => '{}', 'created_at' => now()->subHours(25)],
        ['uuid' => $newUuid, 'batch_id' => $batch, 'type' => 'request', 'content' => '{}', 'created_at' => now()->subHours(23)],
    ]);
    foreach (['expired' => now()->subDays(8)->timestamp, 'recent' => now()->subDays(6)->timestamp] as $key => $timestamp) {
        $record = ['type' => 'retention_probe', 'key' => $key, 'value' => 1];
        DB::table('pulse_entries')->insert([...$record, 'timestamp' => $timestamp]);
        DB::table('pulse_values')->insert([...$record, 'timestamp' => $timestamp]);
        DB::table('pulse_aggregates')->insert([...$record, 'bucket' => $timestamp, 'period' => 10080, 'aggregate' => 'count', 'count' => 1]);
    }

    $this->artisan('monitoring:prune')->assertExitCode(0)->run();

    $this->assertDatabaseMissing('telescope_entries', ['uuid' => $oldUuid]);
    $this->assertDatabaseHas('telescope_entries', ['uuid' => $newUuid]);
    foreach (['pulse_entries', 'pulse_values', 'pulse_aggregates'] as $table) {
        $this->assertDatabaseMissing($table, ['type' => 'retention_probe', 'key' => 'expired']);
        $this->assertDatabaseHas($table, ['type' => 'retention_probe', 'key' => 'recent']);
    }
    $event = collect(app(Schedule::class)->events())->first(
        fn ($event): bool => str_contains($event->command ?? '', 'monitoring:prune'),
    );
    expect($event)->not->toBeNull();
    expect($event->expression)->toBe('0 * * * *');
    expect($event->withoutOverlapping)->toBeTrue();
});
