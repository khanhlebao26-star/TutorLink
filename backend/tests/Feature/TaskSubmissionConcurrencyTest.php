<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\ProgramApiFixtures;
use Tests\Support\TestActors;
use Tests\TestCase;

class TaskSubmissionConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Task submission concurrency tests require PostgreSQL.');
        }
    }

    public function test_submission_retry_and_review_races_leave_consistent_versions(): void
    {
        $tutor = ProgramApiFixtures::eligibleTutor();
        $customer = TestActors::customer();

        $differentPayloadTask = $this->createTask($tutor, $customer);
        $differentPayload = $this->runBehindProgramLock((int) DB::table('tasks')->where('id', $differentPayloadTask)->value('program_id'), [
            $this->submitAction($differentPayloadTask, $customer->id, (string) Str::uuid(), 'Payload A'),
            $this->submitAction($differentPayloadTask, $customer->id, (string) Str::uuid(), 'Payload B'),
        ]);
        $this->assertSame([201, 409], $this->sortedStatuses($differentPayload));
        $this->assertSame(1, DB::table('task_submissions')->where('task_id', $differentPayloadTask)->count());
        $this->assertSame(1, (int) DB::table('task_submissions')
            ->where('id', DB::table('tasks')->where('id', $differentPayloadTask)->value('current_submission_id'))
            ->value('submission_version'));

        $samePayloadTask = $this->createTask($tutor, $customer);
        $sameKey = (string) Str::uuid();
        $samePayload = $this->runBehindProgramLock((int) DB::table('tasks')->where('id', $samePayloadTask)->value('program_id'), [
            $this->submitAction($samePayloadTask, $customer->id, $sameKey, 'Identical payload'),
            $this->submitAction($samePayloadTask, $customer->id, $sameKey, 'Identical payload'),
        ]);
        $this->assertSame([200, 201], $this->sortedStatuses($samePayload));
        $this->assertCount(1, array_unique(array_column($samePayload, 'submission_id')));
        $this->assertSame(1, DB::table('task_submissions')->where('task_id', $samePayloadTask)->count());

        $reviewRaceTask = $this->createTask($tutor, $customer);
        $this->actingAs($customer, 'web')->putJson("/api/v1/tasks/{$reviewRaceTask}/submission", [
            'expected_submission_version' => 0,
            'content' => 'Initial submission',
        ], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
        $reviewRaceProgram = (int) DB::table('tasks')->where('id', $reviewRaceTask)->value('program_id');
        $raceResults = $this->runBehindProgramLock($reviewRaceProgram, [
            $this->submitAction($reviewRaceTask, $customer->id, (string) Str::uuid(), 'Updated submission', 1),
            [
                'operation' => 'review',
                'actor_id' => $tutor->id,
                'task_id' => $reviewRaceTask,
                'expected_submission_version' => 1,
                'feedback' => 'Accepted concurrently',
                'key' => (string) Str::uuid(),
            ],
        ]);
        $this->assertContains($this->sortedStatuses($raceResults), [[201, 409], [200, 409]]);

        $task = DB::table('tasks')->find($reviewRaceTask);
        $current = DB::table('task_submissions')->find($task->current_submission_id);
        if ($task->status === 'completed') {
            $this->assertSame([200, 409], $this->sortedStatuses($raceResults));
            $this->assertSame('accepted', $current->status);
            $this->assertSame(1, (int) $current->submission_version);
            $this->assertSame(1, DB::table('task_submissions')->where('task_id', $reviewRaceTask)->count());
        } else {
            $this->assertSame([201, 409], $this->sortedStatuses($raceResults));
            $this->assertSame('submitted', $task->status);
            $this->assertSame('submitted', $current->status);
            $this->assertSame(2, (int) $current->submission_version);
            $this->assertSame(2, DB::table('task_submissions')->where('task_id', $reviewRaceTask)->count());
        }
    }

    private function createTask(User $tutor, User $customer): int
    {
        $program = ProgramApiFixtures::draft($tutor, $customer, ['status' => 'active']);
        ProgramApiFixtures::addMember($program, $customer, 'customer');

        return (int) $this->actingAs($tutor, 'web')->postJson("/api/v1/programs/{$program}/tasks", [
            'title' => 'Concurrent submission',
            'assigned_to' => $customer->id,
        ])->assertCreated()->json('data.id');
    }

    private function submitAction(int $task, int $customer, string $key, string $content, int $version = 0): array
    {
        return [
            'operation' => 'submit',
            'actor_id' => $customer,
            'task_id' => $task,
            'expected_submission_version' => $version,
            'content' => $content,
            'key' => $key,
        ];
    }

    /** @param list<array<string, mixed>> $actions
     * @return list<array{status: int, submission_id: int|null}>
     */
    private function runBehindProgramLock(int $program, array $actions): array
    {
        $database = DB::connection()->getDatabaseName();
        $processes = [];
        DB::beginTransaction();

        try {
            DB::table('programs')->where('id', $program)->lockForUpdate()->first();
            foreach ($actions as $action) {
                $processes[] = $this->startActionProcess($database, $action);
            }
            $this->waitUntilBlockedOnProgramLock($processes);
            DB::commit();

            foreach ($processes as $process) {
                $process->wait();
            }
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(0, 9);
                }
            }
        }

        return array_map(function (Process $process): array {
            $output = $process->getOutput();
            $this->assertSame(0, $process->getExitCode(), $output.$process->getErrorOutput());
            $this->assertSame(1, preg_match('/RESULT:(\d+):(\d*)/', $output, $match), $output.$process->getErrorOutput());

            return [
                'status' => (int) $match[1],
                'submission_id' => $match[2] === '' ? null : (int) $match[2],
            ];
        }, $processes);
    }

    private function startActionProcess(string $database, array $action): Process
    {
        $script = <<<'PHP'
            require 'vendor/autoload.php';
            $app = require 'bootstrap/app.php';
            $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
            \Tests\Support\TestDatabaseGuard::assertSafe($app['config']);
            $connection = \Illuminate\Support\Facades\DB::connection();
            $action = json_decode(base64_decode($argv[2]), true, 512, JSON_THROW_ON_ERROR);
            if ($connection->getDriverName() !== 'pgsql' || $connection->getDatabaseName() !== $argv[1]) {
                exit(2);
            }
            $pid = $connection->selectOne('SELECT pg_backend_pid() AS pid')->pid;
            fwrite(STDOUT, "READY:{$pid}\n");
            fflush(STDOUT);
            try {
                $actor = \App\Models\User::findOrFail((int) $action['actor_id']);
                $task = (int) $action['task_id'];
                $path = $action['operation'] === 'submit'
                    ? "/api/v1/tasks/{$task}/submission"
                    : "/api/v1/tasks/{$task}/submission/review";
                $payload = ['expected_submission_version' => $action['expected_submission_version']];
                if (array_key_exists('content', $action)) {
                    $payload['content'] = $action['content'];
                }
                if (array_key_exists('feedback', $action)) {
                    $payload['feedback'] = $action['feedback'];
                }
                $request = \Illuminate\Http\Request::create(
                    $path,
                    $action['operation'] === 'submit' ? 'PUT' : 'POST',
                    $payload,
                    [],
                    [],
                    ['HTTP_ACCEPT' => 'application/json', 'HTTP_IDEMPOTENCY_KEY' => $action['key']],
                );
                $request->setUserResolver(fn () => $actor);
                $controller = $app->make(\App\Http\Controllers\Api\V1\TaskController::class);
                $response = $action['operation'] === 'submit'
                    ? $controller->submit($request, $task)
                    : $controller->review($request, $task);
                $body = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
                fwrite(STDOUT, 'RESULT:'.$response->getStatusCode().':'.($body['data']['id'] ?? '')."\n");
            } catch (\Throwable $exception) {
                fwrite(STDERR, (string) $exception);
                exit(4);
            }
            PHP;

        $process = new Process(
            [PHP_BINARY, '-r', $script, $database, base64_encode(json_encode($action, JSON_THROW_ON_ERROR))],
            base_path(),
            ['APP_ENV' => 'testing'],
        );
        $process->setTimeout(20);
        $process->start();

        return $process;
    }

    /** @param list<Process> $processes */
    private function waitUntilBlockedOnProgramLock(array $processes): void
    {
        $deadline = microtime(true) + 10;
        do {
            $pids = [];
            foreach ($processes as $process) {
                if (preg_match('/READY:(\d+)/', $process->getOutput(), $match)) {
                    $pids[] = (int) $match[1];
                }
            }
            if (count($pids) === count($processes)) {
                $blocked = array_filter($pids, function (int $pid): bool {
                    return (int) DB::selectOne(
                        'SELECT cardinality(pg_blocking_pids(?)) AS blockers',
                        [$pid],
                    )->blockers > 0;
                });
                if (count($blocked) === count($processes)) {
                    return;
                }
            }
            usleep(50_000);
        } while (microtime(true) < $deadline);

        $output = implode("\n---\n", array_map(
            fn (Process $process): string => $process->getOutput().$process->getErrorOutput(),
            $processes,
        ));
        $this->fail('Task requests did not both wait on the held Program row. '.$output);
    }

    /** @param list<array{status: int, submission_id: int|null}> $results
     * @return list<int>
     */
    private function sortedStatuses(array $results): array
    {
        $statuses = array_column($results, 'status');
        sort($statuses);

        return $statuses;
    }
}
