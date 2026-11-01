<?php

namespace Tests\Feature;

use App\Actions\Sessions\SaveSession;
use App\Models\ProgramSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\ProgramApiFixtures;
use Tests\Support\TestActors;
use Tests\TestCase;

class SessionConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped(
                'Session race tests require PostgreSQL.',
            );
        }
    }

    #[DataProvider('completionKeyCases')]
    public function test_concurrent_complete_has_one_actual_and_one_audit(
        bool $sameKey,
    ): void {
        $fixture = $this->fixture();
        $sessionId = $this->plannedSession($fixture);

        $first = $this->command(
            $fixture,
            'complete',
            $sessionId,
            ['expected_version' => 1],
        );

        $second = $this->command(
            $fixture,
            'complete',
            $sessionId,
            ['expected_version' => 1],
        );

        if ($sameKey) {
            $second['key'] = $first['key'];
        }

        $results = $this->runPair(
            $fixture['program_id'],
            [$first, $second],
        );

        $this->assertSame([200, 200], array_column($results, 'status'));
        $this->assertEquals($results[0]['body'], $results[1]['body']);

        $this->assertTerminalSession(
            $sessionId,
            'complete',
            $fixture['actor_id'],
        );

        $this->assertSame(
            $sameKey ? 1 : 2,
            DB::table('idempotency_receipts')
                ->where('actor_id', $fixture['actor_id'])
                ->whereIn(
                    'idempotency_key',
                    array_unique([$first['key'], $second['key']]),
                )
                ->count(),
        );
    }

    #[DataProvider('competingTransitionCases')]
    public function test_complete_and_cancel_have_one_winner(
        string $firstAction,
        string $secondAction,
    ): void {
        $fixture = $this->fixture();
        $sessionId = $this->plannedSession($fixture);

        $first = $this->command(
            $fixture,
            $firstAction,
            $sessionId,
            ['expected_version' => 1],
        );

        $second = $this->command(
            $fixture,
            $secondAction,
            $sessionId,
            ['expected_version' => 1],
        );

        $results = $this->runPair(
            $fixture['program_id'],
            [$first, $second],
        );

        $this->assertSame([200, 409], array_column($results, 'status'));
        $this->assertSame(
            'session_transition_conflict',
            $results[1]['code'],
        );

        $this->assertTerminalSession(
            $sessionId,
            $firstAction,
            $fixture['actor_id'],
        );

        $this->assertSame(
            0,
            $this->auditCount(
                'session',
                $sessionId,
                "session.{$secondAction}",
            ),
        );

        $this->assertSame(
            1,
            DB::table('idempotency_receipts')
                ->where('actor_id', $fixture['actor_id'])
                ->whereIn(
                    'idempotency_key',
                    [$first['key'], $second['key']],
                )
                ->count(),
        );
    }

    #[DataProvider('creationAndProgramCases')]
    public function test_create_and_program_terminal_have_a_consistent_order(
        string $programAction,
        bool $createFirst,
    ): void {
        $fixture = $this->fixture();

        $create = $this->command(
            $fixture,
            'create',
            null,
            $this->scheduleData(),
        );

        $finish = $this->command(
            $fixture,
            "program_{$programAction}",
        );

        $commands = $createFirst
            ? [$create, $finish]
            : [$finish, $create];

        $results = $this->runPair(
            $fixture['program_id'],
            $commands,
        );

        $this->assertSame(
            $createFirst ? [201, 409] : [200, 409],
            array_column($results, 'status'),
        );

        $terminalStatus = $programAction === 'complete'
            ? 'completed'
            : 'cancelled';

        $this->assertDatabaseHas('programs', [
            'id' => $fixture['program_id'],
            'status' => $createFirst ? 'active' : $terminalStatus,
        ]);

        $sessionIds = DB::table('program_sessions')
            ->where('program_id', $fixture['program_id'])
            ->pluck('id');

        $this->assertCount($createFirst ? 1 : 0, $sessionIds);

        $this->assertSame(
            0,
            DB::table('lesson_sessions')
                ->whereIn('program_session_id', $sessionIds)
                ->count(),
        );

        $this->assertSame(
            $createFirst ? 0 : 1,
            $this->auditCount(
                'program',
                $fixture['program_id'],
                "program.{$programAction}",
            ),
        );

        $this->assertSame(
            $createFirst ? 0 : 1,
            DB::table('notification_outbox')
                ->where('resource_type', 'program')
                ->where('resource_id', $fixture['program_id'])
                ->count(),
        );

        $this->assertSame(
            $createFirst ? 1 : 0,
            DB::table('idempotency_receipts')
                ->where('actor_id', $fixture['actor_id'])
                ->where('idempotency_key', $create['key'])
                ->count(),
        );

        if ($createFirst) {
            $this->assertDatabaseHas('program_sessions', [
                'id' => $sessionIds->first(),
                'status' => 'scheduled',
                'version' => 1,
                'sequence_number' => 1,
            ]);
        }

        $this->assertSame(
            2,
            DB::table('program_members')
                ->where('program_id', $fixture['program_id'])
                ->count(),
        );
    }

    #[DataProvider('sessionAndProgramCases')]
    public function test_session_and_program_terminal_respect_blockers(
        string $sessionAction,
        string $programAction,
        bool $sessionFirst,
    ): void {
        $fixture = $this->fixture();
        $sessionId = $this->plannedSession($fixture);

        $sessionCommand = $this->command(
            $fixture,
            $sessionAction,
            $sessionId,
            ['expected_version' => 1],
        );

        $programCommand = $this->command(
            $fixture,
            "program_{$programAction}",
        );

        $commands = $sessionFirst
            ? [$sessionCommand, $programCommand]
            : [$programCommand, $sessionCommand];

        $results = $this->runPair(
            $fixture['program_id'],
            $commands,
        );

        $this->assertSame(
            $sessionFirst ? [200, 200] : [409, 200],
            array_column($results, 'status'),
        );

        if (! $sessionFirst) {
            $this->assertSame(
                $programAction === 'complete'
                ? 'program_completion_blocked'
                : 'program_cancellation_blocked',
                $results[0]['code'],
            );
        }

        $terminalStatus = $programAction === 'complete'
            ? 'completed'
            : 'cancelled';

        $this->assertDatabaseHas('programs', [
            'id' => $fixture['program_id'],
            'status' => $sessionFirst ? $terminalStatus : 'active',
        ]);

        $this->assertTerminalSession(
            $sessionId,
            $sessionAction,
            $fixture['actor_id'],
        );

        $this->assertSame(
            $sessionFirst ? 1 : 0,
            $this->auditCount(
                'program',
                $fixture['program_id'],
                "program.{$programAction}",
            ),
        );

        $this->assertSame(
            $sessionFirst ? 1 : 0,
            DB::table('notification_outbox')
                ->where('resource_type', 'program')
                ->where('resource_id', $fixture['program_id'])
                ->count(),
        );

        $this->assertSame(
            2,
            DB::table('program_members')
                ->where('program_id', $fixture['program_id'])
                ->count(),
        );
    }

    public function test_concurrent_edits_cannot_overwrite_the_same_version(): void
    {
        $fixture = $this->fixture();
        $sessionId = $this->plannedSession($fixture);

        $first = $this->command(
            $fixture,
            'edit',
            $sessionId,
            [
                'expected_version' => 1,
                'title' => 'First concurrent edit',
            ],
        );

        $second = $this->command(
            $fixture,
            'edit',
            $sessionId,
            [
                'expected_version' => 1,
                'title' => 'Second concurrent edit',
            ],
        );

        $results = $this->runPair(
            $fixture['program_id'],
            [$first, $second],
        );

        $this->assertSame([200, 409], array_column($results, 'status'));
        $this->assertSame(
            'stale_session_version',
            $results[1]['code'],
        );

        $this->assertDatabaseHas('program_sessions', [
            'id' => $sessionId,
            'title' => 'First concurrent edit',
            'status' => 'scheduled',
            'version' => 2,
            'terminal_replay' => null,
        ]);

        $this->assertSame(
            1,
            $this->auditCount('session', $sessionId, 'session.update'),
        );

        $this->assertSame(
            0,
            DB::table('lesson_sessions')
                ->where('program_session_id', $sessionId)
                ->count(),
        );

        $this->assertSame(
            1,
            DB::table('idempotency_receipts')
                ->where('actor_id', $fixture['actor_id'])
                ->whereIn(
                    'idempotency_key',
                    [$first['key'], $second['key']],
                )
                ->count(),
        );
    }

    public static function completionKeyCases(): array
    {
        return [
            'same key' => [true],
            'different keys' => [false],
        ];
    }

    public static function competingTransitionCases(): array
    {
        return [
            'complete first' => ['complete', 'cancel'],
            'cancel first' => ['cancel', 'complete'],
        ];
    }

    public static function creationAndProgramCases(): array
    {
        return [
            'create before complete Program' => ['complete', true],
            'complete Program before create' => ['complete', false],
            'create before cancel Program' => ['cancel', true],
            'cancel Program before create' => ['cancel', false],
        ];
    }

    public static function sessionAndProgramCases(): array
    {
        $cases = [];

        foreach (['complete', 'cancel'] as $sessionAction) {
            foreach (['complete', 'cancel'] as $programAction) {
                foreach ([true, false] as $sessionFirst) {
                    $name = "{$sessionAction} Session / {$programAction} Program"
                        .($sessionFirst ? ' / Session first' : ' / Program first');

                    $cases[$name] = [
                        $sessionAction,
                        $programAction,
                        $sessionFirst,
                    ];
                }
            }
        }

        return $cases;
    }

    /** @return array{actor_id: int, program_id: int} */
    private function fixture(): array
    {
        $tutor = ProgramApiFixtures::eligibleTutor();
        $customer = TestActors::customer();

        $programId = ProgramApiFixtures::draft(
            $tutor,
            $customer,
            ['status' => 'active'],
        );

        ProgramApiFixtures::addMember($programId, $tutor, 'tutor');
        ProgramApiFixtures::addMember($programId, $customer, 'customer');

        return [
            'actor_id' => (int) $tutor->id,
            'program_id' => $programId,
        ];
    }

    /** @return array<string, mixed> */
    private function scheduleData(): array
    {
        $startsAt = CarbonImmutable::now('UTC')->addHour();

        return [
            'title' => 'Session race',
            'planned_starts_at' => $startsAt
                ->format('Y-m-d\TH:i:s.u\Z'),
            'planned_ends_at' => $startsAt->addHour()
                ->format('Y-m-d\TH:i:s.u\Z'),
            'delivery_mode' => 'online',
        ];
    }

    /** @param array{actor_id: int, program_id: int} $fixture */
    private function plannedSession(array $fixture): int
    {
        $result = app(SaveSession::class)->execute(
            User::query()->findOrFail($fixture['actor_id']),
            $fixture['program_id'],
            null,
            $this->scheduleData(),
            (string) Str::uuid(),
            (string) Str::uuid(),
        );

        return (int) $result['body']['data']['id'];
    }

    /**
     * @param  array{actor_id: int, program_id: int}  $fixture
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function command(
        array $fixture,
        string $operation,
        ?int $sessionId = null,
        array $data = [],
    ): array {
        return [
            ...$fixture,
            'operation' => $operation,
            'session_id' => $sessionId,
            'data' => $data,
            'key' => (string) Str::uuid(),
            'request_id' => (string) Str::uuid(),
        ];
    }

    private function assertTerminalSession(
        int $sessionId,
        string $action,
        int $actorId,
    ): void {
        $session = ProgramSession::query()->findOrFail($sessionId);

        $this->assertSame(
            $action === 'complete' ? 'completed' : 'cancelled',
            $session->status,
        );

        $this->assertSame(2, $session->version);

        $actuals = DB::table('lesson_sessions')
            ->where('program_session_id', $sessionId)
            ->get();

        $this->assertCount($action === 'complete' ? 1 : 0, $actuals);

        if ($action === 'complete') {
            $this->assertSame('completed', $actuals->first()->status);
            $this->assertNull($actuals->first()->started_at);
            $this->assertNull($actuals->first()->ended_at);
        }

        $metadata = $session->terminal_replay;

        $this->assertIsArray($metadata);
        $this->assertSame($actorId, $metadata['actor_id']);
        $this->assertSame($action, $metadata['action']);
        $this->assertSame(1, $metadata['payload']['expected_version']);

        $this->assertSame(
            1,
            DB::table('audit_logs')
                ->where('resource_type', 'session')
                ->where('resource_id', $sessionId)
                ->whereIn('action', ['session.complete', 'session.cancel'])
                ->count(),
        );

        $this->assertSame(
            0,
            DB::table('notification_outbox')
                ->where('resource_type', 'session')
                ->where('resource_id', $sessionId)
                ->count(),
        );
    }

    private function auditCount(
        string $type,
        int $id,
        string $action,
    ): int {
        return DB::table('audit_logs')
            ->where('resource_type', $type)
            ->where('resource_id', $id)
            ->where('action', $action)
            ->count();
    }

    /**
     * @param  list<array<string, mixed>>  $commands
     * @return list<array<string, mixed>>
     */
    private function runPair(int $programId, array $commands): array
    {
        $this->assertCount(2, $commands);

        $this->assertSame(
            0,
            DB::transactionLevel(),
            'Fixtures must be committed before starting race workers.',
        );

        $parentPid = (int) DB::selectOne(
            'SELECT pg_backend_pid() AS pid',
        )->pid;

        $processes = [];

        DB::beginTransaction();

        try {
            DB::table('programs')
                ->where('id', $programId)
                ->lockForUpdate()
                ->first();

            $first = $this->startWorker($commands[0]);
            $processes[] = $first;

            $firstPid = $this->waitForBlock($first, $parentPid);

            $second = $this->startWorker($commands[1]);
            $processes[] = $second;

            $secondPid = $this->waitForBlock($second, $firstPid);

            DB::commit();

            $results = array_map(
                fn (Process $process): array => $this->workerResult($process),
                $processes,
            );

            fwrite(STDOUT, 'K04_RACE:'.json_encode([
                'program_id' => $programId,
                'operations' => array_column($commands, 'operation'),
                'block_chain' => [$secondPid, $firstPid, $parentPid],
                'statuses' => array_column($results, 'status'),
            ], JSON_THROW_ON_ERROR).PHP_EOL);

            return $results;
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
    }

    /** @param array<string, mixed> $command */
    private function startWorker(array $command): Process
    {
        $script = <<<'PHP'
            require 'vendor/autoload.php';

            $app = require 'bootstrap/app.php';

            $app->afterBootstrapping(
                \Illuminate\Foundation\Bootstrap\LoadConfiguration::class,
                function ($app): void {
                    \Tests\Support\TestDatabaseGuard::assertSafe(
                        $app['config'],
                    );
                },
            );

            $app->make(
                \Illuminate\Contracts\Console\Kernel::class,
            )->bootstrap();

            $connection = \Illuminate\Support\Facades\DB::connection();

            if (
                $connection->getDriverName() !== 'pgsql'
                || $connection->getDatabaseName() !== $argv[1]
            ) {
                fwrite(STDERR, 'Unexpected race-test database.');
                exit(2);
            }

            $command = json_decode(
                base64_decode($argv[2]),
                true,
                512,
                JSON_THROW_ON_ERROR,
            );

            $connection->statement("SET lock_timeout = '25s'");
            $connection->statement("SET statement_timeout = '30s'");

            $pid = $connection->selectOne(
                'SELECT pg_backend_pid() AS pid',
            )->pid;

            fwrite(STDOUT, "READY:{$pid}\n");
            fflush(STDOUT);

            try {
                $actor = \App\Models\User::query()
                    ->findOrFail($command['actor_id']);

                $operation = $command['operation'];

                if (in_array($operation, ['complete', 'cancel'], true)) {
                    $result = app(
                        \App\Actions\Sessions\TransitionSession::class,
                    )->execute(
                        $actor,
                        (int) $command['session_id'],
                        $operation,
                        $command['data'],
                        $command['key'],
                        $command['request_id'],
                    );
                } elseif (in_array($operation, ['create', 'edit'], true)) {
                    $result = app(
                        \App\Actions\Sessions\SaveSession::class,
                    )->execute(
                        $actor,
                        (int) $command['program_id'],
                        $operation === 'create'
                            ? null
                            : (int) $command['session_id'],
                        $command['data'],
                        $command['key'],
                        $command['request_id'],
                    );
                } elseif (
                    in_array(
                        $operation,
                        ['program_complete', 'program_cancel'],
                        true,
                    )
                ) {
                    $program = app(
                        \App\Actions\Programs\TransitionProgram::class,
                    )->execute(
                        $actor,
                        (int) $command['program_id'],
                        substr($operation, 8),
                        $command['request_id'],
                    );

                    $result = [
                        'status' => 200,
                        'body' => [
                            'data' => [
                                'id' => (int) $program->id,
                                'status' => $program->status,
                            ],
                        ],
                    ];
                } else {
                    throw new \RuntimeException('Unknown race operation.');
                }
            } catch (
                \App\Exceptions\SessionActionException
                | \App\Exceptions\ProgramActionException $exception
            ) {
                $result = [
                    'status' => $exception->httpStatus,
                    'code' => $exception->errorCode,
                    'errors' => $exception->errors,
                ];
            } catch (\Throwable $exception) {
                fwrite(STDERR, (string) $exception);
                exit(3);
            }

            fwrite(
                STDOUT,
                'RESULT:'.json_encode($result, JSON_THROW_ON_ERROR)."\n",
            );
            PHP;

        $connection = config(
            'database.connections.'.config('database.default'),
        );

        $environment = [
            'APP_ENV' => 'testing',
            'APP_DEBUG' => 'false',
            'APP_KEY' => (string) config('app.key'),
            'APP_CONFIG_CACHE' => sys_get_temp_dir()
                .'/k046-session-'.Str::uuid().'.php',
            'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => (string) $connection['host'],
            'DB_PORT' => (string) $connection['port'],
            'DB_DATABASE' => (string) $connection['database'],
            'DB_USERNAME' => (string) $connection['username'],
            'DB_PASSWORD' => (string) $connection['password'],
            'DB_URL' => '',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'MAIL_MAILER' => 'array',
            'BROADCAST_CONNECTION' => 'null',
        ];

        $process = new Process(
            [
                PHP_BINARY,
                '-r',
                $script,
                DB::connection()->getDatabaseName(),
                base64_encode(json_encode(
                    $command,
                    JSON_THROW_ON_ERROR,
                )),
            ],
            base_path(),
            $environment,
        );

        $process->setTimeout(40);
        $process->start();

        return $process;
    }

    private function waitForBlock(
        Process $process,
        int $expectedBlocker,
    ): int {
        $deadline = microtime(true) + 10;

        do {
            if (
                preg_match(
                    '/READY:(\d+)/',
                    $process->getOutput(),
                    $match,
                )
            ) {
                $pid = (int) $match[1];

                $row = DB::selectOne(
                    'SELECT CASE WHEN CAST(? AS integer) = ANY('
                    .'pg_blocking_pids(CAST(? AS integer))'
                    .') THEN 1 ELSE 0 END AS blocked',
                    [$expectedBlocker, $pid],
                );

                if ((int) $row->blocked === 1) {
                    return $pid;
                }
            }

            if (! $process->isRunning()) {
                $this->fail(
                    'Worker exited before blocking: '
                    .$process->getOutput()
                    .$process->getErrorOutput(),
                );
            }

            usleep(50_000);
        } while (microtime(true) < $deadline);

        $this->fail(
            'Worker did not wait on the expected PostgreSQL process: '
            .$process->getOutput()
            .$process->getErrorOutput(),
        );
    }

    /** @return array<string, mixed> */
    private function workerResult(Process $process): array
    {
        $process->wait();

        $this->assertSame(
            0,
            $process->getExitCode(),
            $process->getOutput().$process->getErrorOutput(),
        );

        $this->assertSame(
            1,
            preg_match(
                '/^RESULT:(.+)$/m',
                $process->getOutput(),
                $match,
            ),
            $process->getOutput(),
        );

        return json_decode(
            $match[1],
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }
}
