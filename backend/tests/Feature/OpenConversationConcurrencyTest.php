<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\ProgramApiFixtures;
use Tests\Support\TestActors;
use Tests\TestCase;

class OpenConversationConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    public function test_concurrent_opposite_direction_opens_create_one_conversation_and_keep_the_first_context(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped(
                'Conversation race test requires PostgreSQL.',
            );
        }

        $this->assertSame(
            'read committed',
            DB::selectOne('SHOW transaction_isolation')->transaction_isolation,
        );

        $customer = TestActors::customer();
        $tutor = ProgramApiFixtures::eligibleTutor();

        $listingIds = [
            ProgramApiFixtures::listing($tutor),
            ProgramApiFixtures::listing($tutor),
        ];

        DB::table('service_listings')
            ->whereIn('id', $listingIds)
            ->update(['status' => 'active']);

        $processes = [];

        DB::beginTransaction();

        try {
            DB::table('users')
                ->whereIn('id', [$customer->id, $tutor->id])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $requests = [
                [$customer->id, $tutor->id, $listingIds[0]],
                [$tutor->id, $customer->id, $listingIds[1]],
            ];

            foreach ($requests as [$actorId, $peerId, $listingId]) {
                $process = $this->startOpen(
                    $actorId,
                    $peerId,
                    $listingId,
                );

                $processes[] = $process;
                $this->waitForUserLock($process);
            }

            DB::commit();

            foreach ($processes as $process) {
                $process->wait();

                $this->assertSame(
                    0,
                    $process->getExitCode(),
                    $process->getOutput().$process->getErrorOutput(),
                );
            }

            $results = array_map(function (Process $process): array {
                $this->assertSame(
                    1,
                    preg_match(
                        '/RESULT:(\{[^\r\n]+\})/',
                        $process->getOutput(),
                        $match,
                    ),
                );

                return json_decode(
                    $match[1],
                    true,
                    512,
                    JSON_THROW_ON_ERROR,
                );
            }, $processes);

            $created = array_values(array_filter(
                $results,
                fn (array $result): bool => $result['created'],
            ));

            $this->assertCount(1, $created);
            $this->assertSame($results[0]['id'], $results[1]['id']);

            $this->assertSame(
                $created[0]['context_listing_id'],
                $results[0]['context_listing_id'],
            );

            $this->assertSame(
                $results[0]['context_listing_id'],
                $results[1]['context_listing_id'],
            );

            $this->assertDatabaseCount('conversations', 1);
            $this->assertDatabaseCount('conversation_members', 2);

            $this->assertEqualsCanonicalizing(
                [$customer->id, $tutor->id],
                DB::table('conversation_members')->pluck('user_id')->all(),
            );
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

    private function startOpen(
        int $actorId,
        int $peerId,
        int $listingId,
    ): Process {
        $script = <<<'PHP'
            require 'vendor/autoload.php';

            $app = require 'bootstrap/app.php';
            $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

            \Tests\Support\TestDatabaseGuard::assertSafe($app['config']);

            $connection = \Illuminate\Support\Facades\DB::connection();

            if (
                $connection->getDriverName() !== 'pgsql'
                || $connection->getDatabaseName() !== $argv[1]
            ) {
                exit(2);
            }

            $actor = \App\Models\User::findOrFail((int) $argv[2]);
            $pid = $connection->selectOne('SELECT pg_backend_pid() AS pid')->pid;

            fwrite(STDOUT, "READY:{$pid}\n");
            fflush(STDOUT);

            try {
                $result = app(\App\Actions\Messaging\OpenConversation::class)->execute(
                    $actor,
                    (int) $argv[3],
                    (int) $argv[4],
                    $argv[5],
                );

                fwrite(STDOUT, 'RESULT:'.json_encode([
                    'id' => $result['conversation']->id,
                    'created' => $result['created'],
                    'context_listing_id' => $result['conversation']->context_listing_id,
                ], JSON_THROW_ON_ERROR)."\n");
            } catch (\Throwable $exception) {
                fwrite(
                    STDERR,
                    get_class($exception).': '.$exception->getMessage(),
                );

                exit(3);
            }
            PHP;

        $process = new Process(
            [
                PHP_BINARY,
                '-r',
                $script,
                DB::connection()->getDatabaseName(),
                (string) $actorId,
                (string) $peerId,
                (string) $listingId,
                (string) Str::uuid(),
            ],
            base_path(),
            ['APP_ENV' => 'testing'],
        );

        $process->setTimeout(20);
        $process->start();

        return $process;
    }

    private function waitForUserLock(Process $process): void
    {
        $deadline = microtime(true) + 10;

        do {
            if (
                preg_match(
                    '/READY:(\d+)/',
                    $process->getOutput(),
                    $match,
                )
            ) {
                $row = DB::selectOne(
                    'SELECT cardinality(pg_blocking_pids(?)) AS blockers',
                    [(int) $match[1]],
                );

                if ((int) $row->blockers > 0) {
                    return;
                }
            }

            if (! $process->isRunning()) {
                $this->fail(
                    'Open process exited before waiting for the User lock. '
                    .$process->getErrorOutput(),
                );
            }

            usleep(50_000);
        } while (microtime(true) < $deadline);

        $this->fail(
            'Open process did not wait for the held User lock. '
            .$process->getOutput()
            .$process->getErrorOutput(),
        );
    }
}
