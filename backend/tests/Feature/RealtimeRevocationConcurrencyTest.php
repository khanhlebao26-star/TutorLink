<?php

namespace Tests\Feature;

use App\Actions\Auth\RevokeUserSessions;
use App\Jobs\BroadcastConversationChanged;
use App\Support\RealtimeSessionChannels;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Mockery;
use Symfony\Component\Process\Process;
use Tests\Support\InteractsWithRealtimeSessions;
use Tests\Support\MessageSendFixtures;
use Tests\Support\ProgramApiFixtures;
use Tests\Support\TestActors;
use Tests\TestCase;

class RealtimeRevocationConcurrencyTest extends TestCase
{
    use DatabaseTruncation;
    use InteractsWithRealtimeSessions;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Realtime race tests require PostgreSQL.');
        }

        config([
            'session.driver' => 'database',
            'session.connection' => config('database.default'),
        ]);
    }

    public function test_revocation_committing_first_prevents_publishing(): void
    {
        $customer = TestActors::customer();
        $tutor = ProgramApiFixtures::eligibleTutor();
        $chat = MessageSendFixtures::conversation($customer, $tutor);
        $this->realtimeFixture($customer);

        $worker = null;
        DB::beginTransaction();

        try {
            DB::table('users')->where('id', $customer->id)
                ->lockForUpdate()->first();

            app(RevokeUserSessions::class)->handle($customer);

            $worker = $this->startWorker('publish', $customer->id, $chat);
            $this->waitUntilBlocked($worker);

            DB::commit();

            $worker->wait();

            $this->assertSame(
                0,
                $worker->getExitCode(),
                $worker->getErrorOutput(),
            );

            $this->assertStringContainsString(
                'RESULT:0',
                $worker->getOutput(),
            );

            $this->assertDatabaseMissing('sessions', [
                'user_id' => $customer->id,
            ]);
        } finally {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            if ($worker?->isRunning()) {
                $worker->stop(1);
            }
        }
    }

    public function test_publishing_first_holds_the_lock_until_revocation_can_commit(): void
    {
        $customer = TestActors::customer();
        $tutor = ProgramApiFixtures::eligibleTutor();
        $chat = MessageSendFixtures::conversation($customer, $tutor);
        $this->realtimeFixture($customer);

        $published = 0;

        $driver = Mockery::mock(Broadcaster::class);
        $driver->shouldReceive('broadcast')
            ->once()
            ->andReturnUsing(function () use (&$published): void {
                $published++;
            });

        Broadcast::shouldReceive('connection')->once()->andReturn($driver);

        $worker = null;
        DB::beginTransaction();

        try {
            (new BroadcastConversationChanged($chat))
                ->handle(app(RealtimeSessionChannels::class));

            $this->assertSame(1, $published);

            $worker = $this->startWorker('revoke', $customer->id, $chat);
            $this->waitUntilBlocked($worker);

            DB::commit();

            $worker->wait();

            $this->assertSame(
                0,
                $worker->getExitCode(),
                $worker->getErrorOutput(),
            );

            $this->assertStringContainsString(
                'RESULT:0',
                $worker->getOutput(),
            );

            /*
             * After revocation, another job must not call the broadcaster.
             */
            (new BroadcastConversationChanged($chat))
                ->handle(app(RealtimeSessionChannels::class));

            $this->assertSame(1, $published);

            $this->assertSame(
                1,
                DB::table('realtime_sessions')
                    ->where('user_id', $customer->id)
                    ->whereNotNull('revoked_at')
                    ->count(),
            );
        } finally {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            if ($worker?->isRunning()) {
                $worker->stop(1);
            }
        }
    }

    private function startWorker(
        string $operation,
        int $userId,
        int $chat,
    ): Process {
        $script = <<<'PHP'
            require getcwd().'/vendor/autoload.php';

            $app = require getcwd().'/bootstrap/app.php';
            $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

            $db = \Illuminate\Support\Facades\DB::connection();

            if (
                ! $app->environment('testing')
                || $db->getDriverName() !== 'pgsql'
                || $db->getDatabaseName() !== $argv[1]
                || ! str_ends_with($db->getDatabaseName(), '_test')
            ) {
                fwrite(STDERR, "Unsafe test database.\n");
                exit(2);
            }

            config([
                'session.driver' => 'database',
                'session.connection' => config('database.default'),
            ]);

            $published = 0;
            $driver = \Mockery::mock(
                \Illuminate\Contracts\Broadcasting\Broadcaster::class,
            );

            $driver->shouldReceive('broadcast')
                ->andReturnUsing(function () use (&$published): void {
                    $published++;
                });

            \Illuminate\Support\Facades\Broadcast::shouldReceive('connection')
                ->andReturn($driver);

            $pid = $db->selectOne('SELECT pg_backend_pid() AS pid')->pid;
            fwrite(STDOUT, "READY:{$pid}\n");
            fflush(STDOUT);

            if ($argv[2] === 'publish') {
                (new \App\Jobs\BroadcastConversationChanged((int) $argv[4]))
                    ->handle(app(\App\Support\RealtimeSessionChannels::class));
            } elseif ($argv[2] === 'revoke') {
                app(\App\Actions\Auth\RevokeUserSessions::class)->handle(
                    \App\Models\User::findOrFail((int) $argv[3]),
                );
            } else {
                exit(3);
            }

            fwrite(STDOUT, "RESULT:{$published}\n");
            PHP;

        $process = new Process([
            PHP_BINARY,
            '-r',
            $script,
            DB::connection()->getDatabaseName(),
            $operation,
            (string) $userId,
            (string) $chat,
        ], base_path(), [
            'APP_ENV' => 'testing',
        ]);

        $process->setTimeout(20);
        $process->start();

        return $process;
    }

    private function waitUntilBlocked(Process $process): void
    {
        $ownerPid = (int) DB::selectOne(
            'SELECT pg_backend_pid() AS pid',
        )->pid;

        $deadline = microtime(true) + 10;

        do {
            if (preg_match('/READY:(\d+)/', $process->getOutput(), $match)) {
                $state = DB::selectOne(
                    'SELECT CASE
                        WHEN CAST(? AS integer) = ANY(pg_blocking_pids(?))
                        THEN 1 ELSE 0
                     END AS blocked',
                    [$ownerPid, (int) $match[1]],
                );

                if ((int) $state->blocked === 1) {
                    return;
                }
            }

            if (! $process->isRunning()) {
                break;
            }

            usleep(20_000);
        } while (microtime(true) < $deadline);

        $this->fail(
            'Worker did not block on the parent User lock. '
            .$process->getOutput()
            .$process->getErrorOutput(),
        );
    }
}
