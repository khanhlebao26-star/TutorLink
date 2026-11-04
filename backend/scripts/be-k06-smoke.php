<?php

use App\Jobs\SendSlackOperationalAlert;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\ProgramApiFixtures;
use Tests\Support\TestActors;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$run = 'be-k06-'.gmdate('Ymd-His').'-'.Str::lower(Str::random(4));
$report = ['run_id' => $run, 'checked_at' => gmdate(DATE_ATOM), 'steps' => [], 'passed' => false];

/** @param array<string, mixed> $payload
 * @param  array<string, string>  $headers
 * @return array<string, mixed>
 */
function smokeRequest(CookieJar $cookies, string $name, string $method, string $path, int $expected, array $payload = [], array $headers = []): array
{
    global $report;
    foreach ($cookies->toArray() as $cookie) {
        if ($cookie['Name'] === 'XSRF-TOKEN') {
            $headers['X-XSRF-TOKEN'] = rawurldecode($cookie['Value']);
        }
    }
    $response = Http::acceptJson()->withHeaders([
        'Origin' => 'http://localhost:3000',
        'Referer' => 'http://localhost:3000/',
        ...$headers,
    ])->withOptions(['cookies' => $cookies])->timeout(20)
        ->send($method, 'http://127.0.0.1:8000'.$path, $payload === [] ? [] : ['json' => $payload]);
    $report['steps'][] = [
        'name' => $name, 'method' => $method, 'path' => $path,
        'expected_status' => $expected, 'actual_status' => $response->status(),
        'code' => $response->json('code'), 'passed' => $response->status() === $expected,
    ];
    if ($response->status() !== $expected) {
        throw new RuntimeException('Unexpected status: '.$name);
    }

    return $response->json() ?? [];
}

function smokeCheck(bool $condition, string $name): void
{
    global $report;
    $report['checks'][$name] = $condition;
    if (! $condition) {
        throw new RuntimeException('Smoke assertion: '.$name);
    }
}

try {
    if (! $app->environment('local') || DB::getDriverName() !== 'pgsql' || DB::selectOne('select current_database() as name')->name !== 'tutorlink') {
        throw new RuntimeException('Smoke requires local PostgreSQL database tutorlink.');
    }
    config(['monitoring.alerts.suppressed' => true]);
    $password = bin2hex(random_bytes(24));
    [$tutor, $customer, $outsider, $programId] = DB::transaction(function () use ($run, $password): array {
        $tutor = ProgramApiFixtures::eligibleTutor();
        $customer = TestActors::customer();
        $outsider = TestActors::customer();
        foreach (['tutor' => $tutor, 'customer' => $customer, 'outsider' => $outsider] as $role => $user) {
            $user->forceFill([
                'email' => $run.'-'.$role.'@tutorlink.test',
                'full_name' => $run.' '.$role,
                'password_hash' => Hash::make($password),
            ])->save();
        }
        $programId = ProgramApiFixtures::draft($tutor, $customer, ['status' => 'active', 'title' => $run]);
        ProgramApiFixtures::addMember($programId, $tutor, 'tutor');
        ProgramApiFixtures::addMember($programId, $customer, 'customer');

        return [$tutor, $customer, $outsider, $programId];
    });
    $report['fixtures'] = [
        'tutor_id' => $tutor->id, 'customer_id' => $customer->id,
        'outsider_id' => $outsider->id, 'program_id' => $programId,
        'preconditions' => 'Synthetic verified actors, approved identity metadata and active Program; not a registration/approval/file-upload smoke.',
    ];
    $clients = [];
    foreach (['tutor' => $tutor, 'customer' => $customer, 'outsider' => $outsider] as $role => $user) {
        $clients[$role] = new CookieJar;
        smokeRequest($clients[$role], $role.' csrf', 'GET', '/sanctum/csrf-cookie', 204);
        $login = smokeRequest($clients[$role], $role.' login', 'POST', '/api/v1/auth/login', 200, ['email' => $user->email, 'password' => $password]);
        smokeCheck(($login['data']['id'] ?? null) === $user->id, $role.' authenticated identity');
        smokeRequest($clients[$role], $role.' me', 'GET', '/api/v1/me', 200);
    }
    unset($password);
    $chat = smokeRequest($clients['customer'], 'open chat', 'POST', '/api/v1/conversations', 201, ['peer_user_id' => $tutor->id]);
    $conversationId = $chat['data']['id'];
    $report['fixtures']['conversation_id'] = $conversationId;
    $message = ['client_message_id' => (string) Str::uuid(), 'message_type' => 'text', 'body' => $run, 'file_ids' => []];
    $sent = smokeRequest($clients['customer'], 'send text', 'POST', "/api/v1/conversations/{$conversationId}/messages", 201, $message);
    $replay = smokeRequest($clients['customer'], 'retry same text', 'POST', "/api/v1/conversations/{$conversationId}/messages", 200, $message);
    smokeCheck($sent['data']['id'] === $replay['data']['id'], 'chat replay returns same message');
    smokeRequest($clients['customer'], 'reject changed text with same key', 'POST', "/api/v1/conversations/{$conversationId}/messages", 409, array_replace($message, ['body' => $run.' changed']));
    smokeRequest($clients['tutor'], 'peer reads chat', 'GET', "/api/v1/conversations/{$conversationId}/messages", 200);
    smokeRequest($clients['outsider'], 'outsider cannot read chat', 'GET', "/api/v1/conversations/{$conversationId}/messages", 404);
    $starts = now('UTC')->addDay()->startOfHour();
    $session = smokeRequest($clients['tutor'], 'create Session', 'POST', "/api/v1/programs/{$programId}/sessions", 201, [
        'title' => $run, 'planned_starts_at' => $starts->toIso8601String(),
        'planned_ends_at' => $starts->copy()->addHour()->toIso8601String(), 'delivery_mode' => 'online',
    ], ['Idempotency-Key' => (string) Str::uuid()]);
    $sessionId = $session['data']['id'];
    $report['fixtures']['session_id'] = $sessionId;
    smokeRequest($clients['customer'], 'member reads Session', 'GET', "/api/v1/sessions/{$sessionId}", 200);
    smokeRequest($clients['outsider'], 'outsider cannot read Session', 'GET', "/api/v1/sessions/{$sessionId}", 404);
    smokeRequest($clients['customer'], 'customer cannot complete Session', 'POST', "/api/v1/sessions/{$sessionId}/complete", 403, ['expected_version' => 1], ['Idempotency-Key' => (string) Str::uuid()]);
    smokeRequest($clients['tutor'], 'stale Session version', 'POST', "/api/v1/sessions/{$sessionId}/complete", 409, ['expected_version' => 2], ['Idempotency-Key' => (string) Str::uuid()]);
    $key = (string) Str::uuid();
    $completed = smokeRequest($clients['tutor'], 'complete Session', 'POST', "/api/v1/sessions/{$sessionId}/complete", 200, ['expected_version' => 1], ['Idempotency-Key' => $key]);
    $completedReplay = smokeRequest($clients['tutor'], 'retry complete Session', 'POST', "/api/v1/sessions/{$sessionId}/complete", 200, ['expected_version' => 1], ['Idempotency-Key' => $key]);
    // PostgreSQL JSONB may reorder object keys in the stored replay response.
    smokeCheck($completed['data'] == $completedReplay['data'], 'Session replay keeps result');
    smokeCheck(DB::table('lesson_sessions')->where('program_session_id', $sessionId)->count() === 1, 'one actual Session');
    smokeCheck(DB::table('messages')->where('conversation_id', $conversationId)->count() === 1, 'one chat message');
    smokeCheck(DB::table('notification_outbox')->where('resource_type', 'session')->where('resource_id', $sessionId)->count() === 0, 'Session emits no unsupported notification');
    smokeRequest($clients['tutor'], 'pause Program creates notification event', 'POST', "/api/v1/programs/{$programId}/pause", 200);
    $events = DB::table('notification_outbox')->where('resource_type', 'program')->where('resource_id', $programId)->where('type', 'program_paused')->get();
    smokeCheck($events->count() > 0, 'Program pause wrote outbox');
    $deadline = microtime(true) + 15;
    do {
        $pending = DB::table('notification_outbox')->whereIn('id', $events->pluck('id'))->whereNull('processed_at')->count();
        if ($pending === 0) {
            break;
        }
        usleep(250000);
    } while (microtime(true) < $deadline);
    smokeCheck($pending === 0, 'running outbox-worker processed demo events');
    $report['outbox'] = [];
    foreach ($events as $event) {
        $notifications = DB::table('notifications')->where('user_id', $event->user_id)->where('type', $event->type)->where('event_key', $event->event_key)->count();
        smokeCheck($notifications === 1, 'one notification for '.$event->id);
        $report['outbox'][] = ['id' => $event->id, 'type' => $event->type, 'event_key' => $event->event_key, 'notification_count' => $notifications];
    }
    smokeRequest($clients['customer'], 'logout', 'POST', '/api/v1/auth/logout', 204);
    smokeRequest($clients['customer'], 'logged-out chat denied', 'GET', "/api/v1/conversations/{$conversationId}/messages", 401);

    if (in_array('--slack', $argv, true)) {
        $queue = 'k06-smoke-'.Str::lower(Str::random(8));
        $alertId = (string) Str::uuid();
        config(['app.env' => 'TEST_BE-K06']);
        Http::globalResponseMiddleware(function (ResponseInterface $response) use (&$report): ResponseInterface {
            $body = json_decode((string) $response->getBody(), true);
            $report['slack']['receipts'][] = [
                'http_status' => $response->getStatusCode(), 'ok' => $body['ok'] ?? null,
                'error' => $body['error'] ?? null, 'channel' => $body['channel'] ?? null,
                'ts' => $body['ts'] ?? null,
                'test_marker_present' => str_contains($body['message']['text'] ?? '', '[TEST_BE-K06]'),
            ];

            return $response;
        });
        $report['slack'] = ['alert_id' => $alertId, 'queue' => $queue, 'receipts' => [], 'kind' => 'synthetic outbox_pending drill', 'transport' => 'real database queue + native Laravel worker + real Slack API; dedicated smoke queue, not regular slack-worker'];
        Bus::dispatch((new SendSlackOperationalAlert($alertId, 'outbox_pending', [
            'pending_count' => 1, 'oldest_pending_seconds' => 301, 'checked_at' => now()->timestamp,
        ]))->onQueue($queue));
        $exit = Artisan::call('queue:work', [
            'connection' => 'database', '--queue' => $queue, '--once' => true,
            '--sleep' => 0, '--tries' => 1, '--timeout' => 20,
        ]);
        $receipt = $report['slack']['receipts'][0] ?? [];
        smokeCheck($exit === 0 && ($receipt['ok'] ?? false) === true && ($receipt['test_marker_present'] ?? false) === true && is_string($receipt['ts'] ?? null), 'real Slack accepts TEST_BE-K06 message');
        smokeCheck(DB::table('jobs')->where('queue', $queue)->count() === 0, 'smoke Slack queue drained');
        smokeCheck(DB::table('failed_jobs')->where('queue', $queue)->count() === 0, 'smoke Slack job not failed');
    }
    $report['passed'] = true;
} catch (Throwable $error) {
    $report['failure_class'] = get_class($error);
    if ($error instanceof Error) {
        $report['diagnostic'] = $error->getMessage();
    }
    $report['failure'] = $error instanceof RuntimeException && str_starts_with($error->getMessage(), 'Smoke ')
        ? $error->getMessage() : ($error instanceof RuntimeException && str_starts_with($error->getMessage(), 'Unexpected status:') ? $error->getMessage() : 'Smoke stopped; inspect local runtime without publishing secrets.');
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), PHP_EOL;
exit($report['passed'] ? 0 : 1);
