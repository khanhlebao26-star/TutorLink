<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\ProgramApiFixtures;
use Tests\Support\TestActors;
use Tests\TestCase;

class TrustWorkflowConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL row lock races.');
        }
    }

    public function test_duplicate_reviews_commit_once_and_same_key_replays(): void
    {
        foreach ([false, true] as $sameKey) {
            $customer = TestActors::customer();
            $tutor = ProgramApiFixtures::eligibleTutor();
            $program = ProgramApiFixtures::draft($tutor, $customer, ['status' => 'completed']);
            ProgramApiFixtures::addMember($program, $customer, 'customer');
            $action = ['actor' => $customer->id, 'controller' => 'ReviewController', 'operation' => 'store',
                'path' => "/api/v1/programs/{$program}/review", 'id' => $program, 'payload' => ['rating' => 5], 'key' => (string) Str::uuid()];
            $other = [...$action, 'key' => $sameKey ? $action['key'] : (string) Str::uuid()];
            $results = $this->race('users', $customer->id, [$action, $other]);
            $statuses = array_column($results, 'status');
            sort($statuses);
            $this->assertSame($sameKey ? [200, 201] : [201, 409], $statuses);
            $this->assertSame(1, DB::table('reviews')->where('program_id', $program)->count());
            $this->assertSame(1, DB::table('audit_logs')->where('action', 'review.create')->where('resource_id', DB::table('reviews')->where('program_id', $program)->value('id'))->count());
        }
    }

    public function test_concurrent_report_assignment_rejects_the_stale_writer(): void
    {
        $admin = TestActors::admin();
        $target = TestActors::admin();
        $customer = TestActors::customer();
        $report = DB::table('reports')->insertGetId(['reporter_id' => $customer->id, 'resource_type' => 'program', 'resource_id' => 1, 'reason' => 'spam', 'status' => 'open', 'created_at' => now(), 'updated_at' => now()]);
        $action = ['actor' => $admin->id, 'controller' => 'ReportController', 'operation' => 'assign', 'method' => 'PUT',
            'path' => "/api/v1/admin/reports/{$report}/assignment", 'id' => $report,
            'payload' => ['assigned_admin_id' => $target->id, 'assignment_version' => 0, 'reason' => 'Assignment'], 'key' => (string) Str::uuid()];
        $results = $this->race('users', $admin->id, [$action, $action]);
        $statuses = array_column($results, 'status');
        sort($statuses);
        $this->assertSame([200, 409], $statuses);
        $this->assertDatabaseHas('reports', ['id' => $report, 'assigned_admin_id' => $target->id, 'assignment_version' => 1]);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_evidence_and_reassignment_serialize_and_revoke_the_previous_reader(): void
    {
        $coordinator = TestActors::admin();
        $reader = TestActors::admin();
        $next = TestActors::admin();
        $customer = TestActors::customer();
        $tutor = ProgramApiFixtures::eligibleTutor();
        $program = ProgramApiFixtures::draft($tutor, $customer);
        $report = DB::table('reports')->insertGetId(['reporter_id' => $customer->id, 'resource_type' => 'program', 'resource_id' => $program, 'reason' => 'spam', 'status' => 'open', 'assigned_admin_id' => $reader->id, 'created_at' => now(), 'updated_at' => now()]);
        $evidence = ['actor' => $reader->id, 'controller' => 'ReportController', 'operation' => 'evidence', 'method' => 'GET', 'path' => "/api/v1/admin/reports/{$report}/evidence", 'id' => $report, 'payload' => [], 'key' => (string) Str::uuid()];
        $assign = ['actor' => $coordinator->id, 'controller' => 'ReportController', 'operation' => 'assign', 'method' => 'PUT', 'path' => "/api/v1/admin/reports/{$report}/assignment", 'id' => $report, 'payload' => ['assigned_admin_id' => $next->id, 'assignment_version' => 0, 'reason' => 'Transfer'], 'key' => (string) Str::uuid()];
        $results = $this->race('reports', $report, [$evidence, $assign]);
        $this->assertContains(array_column($results, 'status'), [[200, 200], [404, 200]]);
        $this->assertDatabaseHas('reports', ['id' => $report, 'assigned_admin_id' => $next->id, 'assignment_version' => 1]);
        $this->actingAs($reader, 'web')->getJson("/api/v1/admin/reports/{$report}/evidence")->assertNotFound();
        $this->actingAs($next, 'web')->getJson("/api/v1/admin/reports/{$report}/evidence")->assertOk();
    }

    /** @param list<array<string, mixed>> $actions
     * @return list<array{status: int, submission_id: int|null}>
     */
    private function race(string $table, int $program, array $actions): array
    {
        $database = DB::connection()->getDatabaseName();
        $processes = [];
        DB::beginTransaction();

        try {
            DB::table($table)->where('id', $program)->lockForUpdate()->first();
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
            $a = json_decode(base64_decode($argv[2]), true, 512, JSON_THROW_ON_ERROR);
            if ($connection->getDriverName() !== 'pgsql' || $connection->getDatabaseName() !== $argv[1]) { exit(2); }
            $actor = \App\Models\User::findOrFail($a['actor']);
            $request = \Illuminate\Http\Request::create($a['path'], $a['method'] ?? 'POST', $a['payload'], [], [], ['HTTP_ACCEPT'=>'application/json','HTTP_IDEMPOTENCY_KEY'=>$a['key']]);
            $request->setUserResolver(fn () => $actor);
            $request->attributes->set('request_id', (string) \Illuminate\Support\Str::uuid());
            $pid = $connection->selectOne('SELECT pg_backend_pid() AS pid')->pid;
            fwrite(STDOUT, "READY:{$pid}\n"); fflush(STDOUT);
            try {
                $controller = $app->make('App\\Http\\Controllers\\Api\\V1\\'.$a['controller']);
                $response = $controller->{$a['operation']}($request, $a['id'], app(\App\Support\TrustWorkflow::class));
                fwrite(STDOUT, 'RESULT:'.$response->getStatusCode().":\n");
            } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
                fwrite(STDOUT, 'RESULT:'.$e->getResponse()->getStatusCode().":\n");
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                fwrite(STDOUT, 'RESULT:'.$e->getStatusCode().":\n");
            } catch (\Throwable $e) { fwrite(STDERR, (string) $e); exit(4); }
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
}
