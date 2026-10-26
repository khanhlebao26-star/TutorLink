<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\ProgramApiFixtures;
use Tests\Support\TestActors;
use Tests\TestCase;

class InvitationAcceptConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Invitation race tests require PostgreSQL.');
        }
    }

    public function test_only_one_concurrent_accept_creates_membership_audit_and_outbox(): void
    {
        $customer = TestActors::customer();
        $tutor = ProgramApiFixtures::eligibleTutor();
        $programId = ProgramApiFixtures::draft($tutor, $customer);
        $invitationId = ProgramApiFixtures::invitation(
            $programId,
            $tutor,
            $customer,
            now('UTC')->addHours(72)->format('Y-m-d H:i:s.uP'),
        );
        $processes = $this->runBehindUserLocks(
            [$customer->id, $tutor->id],
            [
                ['operation' => 'accept', 'actor_id' => $customer->id, 'program_id' => $programId, 'invitation_id' => $invitationId],
                ['operation' => 'accept', 'actor_id' => $customer->id, 'program_id' => $programId, 'invitation_id' => $invitationId],
            ],
        );

        $statuses = array_map(fn (Process $process): int => $this->resultStatus($process), $processes);
        sort($statuses);

        $this->assertSame([200, 409], $statuses);
        $this->assertDatabaseHas('programs', ['id' => $programId, 'status' => 'active']);
        $this->assertDatabaseHas('program_invitations', ['id' => $invitationId, 'status' => 'accepted']);
        $this->assertDatabaseCount('program_members', 2);
        $this->assertDatabaseCount('idempotency_receipts', 1);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'invitation.accept',
            'resource_id' => $invitationId,
        ]);
        $this->assertDatabaseCount('notification_outbox', 1);
        $this->assertDatabaseHas('notification_outbox', [
            'type' => 'invitation_accepted',
            'resource_id' => $invitationId,
            'event_key' => "invitation:{$invitationId}:accepted",
        ]);
    }

    public function test_accept_and_lazy_expiry_race_leaves_no_membership_or_partial_writes(): void
    {
        $customer = TestActors::customer();
        $tutor = ProgramApiFixtures::eligibleTutor();
        $programId = ProgramApiFixtures::draft($tutor, $customer);
        $invitationId = ProgramApiFixtures::invitation(
            $programId,
            $tutor,
            $customer,
            now('UTC')->subMinute()->format('Y-m-d H:i:s.uP'),
        );

        $processes = $this->runBehindUserLocks(
            [$customer->id, $tutor->id],
            [
                ['operation' => 'accept', 'actor_id' => $customer->id, 'program_id' => $programId, 'invitation_id' => $invitationId],
                ['operation' => 'expire', 'actor_id' => $tutor->id, 'program_id' => $programId, 'invitation_id' => $invitationId],
            ],
        );

        $statuses = array_map(fn (Process $process): int => $this->resultStatus($process), $processes);
        sort($statuses);

        $this->assertSame([200, 409], $statuses);
        $this->assertDatabaseHas('programs', ['id' => $programId, 'status' => 'draft', 'version' => 2]);
        $this->assertDatabaseHas('program_invitations', ['id' => $invitationId, 'status' => 'expired']);
        $this->assertDatabaseCount('program_members', 0);
        $this->assertDatabaseCount('idempotency_receipts', 0);
        $this->assertDatabaseCount('audit_logs', 2);
        $this->assertDatabaseHas('audit_logs', ['action' => 'invitation.expire', 'resource_id' => $invitationId]);
        $this->assertDatabaseCount('notification_outbox', 1);
        $this->assertDatabaseHas('notification_outbox', [
            'type' => 'invitation_expired',
            'resource_id' => $invitationId,
        ]);
    }

    public function test_accept_and_draft_cancel_race_commit_a_consistent_serial_order(): void
    {
        $customer = TestActors::customer();
        $tutor = ProgramApiFixtures::eligibleTutor();
        $programId = ProgramApiFixtures::draft($tutor, $customer);
        $invitationId = ProgramApiFixtures::invitation(
            $programId,
            $tutor,
            $customer,
            now('UTC')->addHours(72)->format('Y-m-d H:i:s.uP'),
        );

        $processes = $this->runBehindUserLocks(
            [$customer->id, $tutor->id],
            [
                ['operation' => 'cancel', 'actor_id' => $tutor->id, 'program_id' => $programId, 'invitation_id' => $invitationId],
                ['operation' => 'accept', 'actor_id' => $customer->id, 'program_id' => $programId, 'invitation_id' => $invitationId],
            ],
        );

        $statuses = array_map(fn (Process $process): int => $this->resultStatus($process), $processes);
        sort($statuses);

        $this->assertContains($statuses, [[200, 200], [200, 409]]);
        $this->assertDatabaseHas('programs', ['id' => $programId, 'status' => 'cancelled']);
        $invitationStatus = DB::table('program_invitations')->where('id', $invitationId)->value('status');

        if ($invitationStatus === 'accepted') {
            $this->assertSame([200, 200], $statuses);
            $this->assertDatabaseCount('program_members', 2);
            $this->assertDatabaseHas('audit_logs', ['action' => 'invitation.accept', 'resource_id' => $invitationId]);
            $this->assertDatabaseHas('notification_outbox', ['type' => 'invitation_accepted', 'resource_id' => $invitationId]);
        } else {
            $this->assertSame('cancelled', $invitationStatus);
            $this->assertSame([200, 409], $statuses);
            $this->assertDatabaseCount('program_members', 0);
            $this->assertDatabaseHas('audit_logs', ['action' => 'invitation.cancel', 'resource_id' => $invitationId]);
            $this->assertDatabaseMissing('audit_logs', ['action' => 'invitation.accept', 'resource_id' => $invitationId]);
            $this->assertDatabaseHas('notification_outbox', ['type' => 'invitation_cancelled', 'resource_id' => $invitationId]);
            $this->assertDatabaseMissing('notification_outbox', ['type' => 'invitation_accepted', 'resource_id' => $invitationId]);
        }
    }

    public function test_accept_and_account_suspension_race_respect_the_user_lock(): void
    {
        $customer = TestActors::customer();
        $tutor = ProgramApiFixtures::eligibleTutor();
        $admin = TestActors::admin();
        $programId = ProgramApiFixtures::draft($tutor, $customer);
        $invitationId = ProgramApiFixtures::invitation(
            $programId,
            $tutor,
            $customer,
            now('UTC')->addHours(72)->format('Y-m-d H:i:s.uP'),
        );

        $processes = $this->runBehindUserLocks(
            [$customer->id, $tutor->id],
            [
                ['operation' => 'suspend', 'actor_id' => $admin->id, 'program_id' => $programId, 'invitation_id' => $invitationId, 'target_id' => $tutor->id],
                ['operation' => 'accept', 'actor_id' => $customer->id, 'program_id' => $programId, 'invitation_id' => $invitationId],
            ],
        );

        $statuses = array_map(fn (Process $process): int => $this->resultStatus($process), $processes);
        sort($statuses);

        $this->assertContains($statuses, [[200, 200], [200, 409]]);
        $this->assertDatabaseHas('users', ['id' => $tutor->id, 'status' => 'suspended']);
        $invitationStatus = DB::table('program_invitations')->where('id', $invitationId)->value('status');

        if ($invitationStatus === 'accepted') {
            $this->assertSame([200, 200], $statuses);
            $this->assertDatabaseHas('programs', ['id' => $programId, 'status' => 'active']);
            $this->assertDatabaseCount('program_members', 2);
            $this->assertDatabaseCount('audit_logs', 2);
            $this->assertDatabaseHas('audit_logs', ['action' => 'invitation.accept', 'resource_id' => $invitationId]);
            $this->assertDatabaseHas('notification_outbox', ['type' => 'invitation_accepted', 'resource_id' => $invitationId]);
        } else {
            $this->assertSame('pending', $invitationStatus);
            $this->assertSame([200, 409], $statuses);
            $this->assertDatabaseHas('programs', ['id' => $programId, 'status' => 'draft']);
            $this->assertDatabaseCount('program_members', 0);
            $this->assertDatabaseCount('audit_logs', 1);
            $this->assertDatabaseHas('audit_logs', ['action' => 'account_suspended', 'resource_id' => $tutor->id]);
            $this->assertDatabaseMissing('audit_logs', ['action' => 'invitation.accept', 'resource_id' => $invitationId]);
            $this->assertDatabaseCount('notification_outbox', 0);
        }
    }

    /** @param list<int> $userIds
     * @param  list<array{operation: string, actor_id: int, program_id: int, invitation_id: int, target_id?: int}>  $actions
     * @return list<Process>
     */
    private function runBehindUserLocks(array $userIds, array $actions): array
    {
        $database = DB::connection()->getDatabaseName();
        $processes = [];

        DB::beginTransaction();

        try {
            DB::table('users')->whereIn('id', $userIds)->orderBy('id')->lockForUpdate()->get();

            foreach ($actions as $action) {
                $process = $this->startActionProcess($database, $action);
                $processes[] = $process;
                $this->waitUntilBlockedOnUserLock([$process]);
            }

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

        return $processes;
    }

    /** @param array{operation: string, actor_id: int, program_id: int, invitation_id: int, target_id?: int} $action */
    private function startActionProcess(
        string $database,
        array $action,
    ): Process {
        $script = <<<'PHP'
            require 'vendor/autoload.php';

            $app = require 'bootstrap/app.php';
            $app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
            \Tests\Support\TestDatabaseGuard::assertSafe($app['config']);

            $connection = \Illuminate\Support\Facades\DB::connection();

            if ($connection->getDriverName() !== 'pgsql' || $connection->getDatabaseName() !== $argv[1]) {
                exit(2);
            }

            $actor = \App\Models\User::findOrFail((int) $argv[3]);
            $pid = $connection->selectOne('SELECT pg_backend_pid() AS pid')->pid;
            fwrite(STDOUT, "READY:{$pid}\n");
            fflush(STDOUT);

            try {
                if ($argv[2] === 'accept') {
                    app(\App\Actions\Invitations\AcceptInvitation::class)->execute(
                        $actor,
                        (int) $argv[5],
                        $argv[6],
                        $argv[7],
                    );
                } elseif ($argv[2] === 'cancel') {
                    app(\App\Actions\Programs\TransitionProgram::class)->execute(
                        $actor,
                        (int) $argv[4],
                        'cancel',
                        $argv[7],
                    );
                } elseif ($argv[2] === 'expire') {
                    app(\App\Actions\Programs\UpdateProgramDraft::class)->execute(
                        $actor,
                        (int) $argv[4],
                        ['expected_version' => 1, 'title' => 'Edited after expiry'],
                        $argv[7],
                    );
                } elseif ($argv[2] === 'suspend') {
                    $request = \Illuminate\Http\Request::create(
                        '/api/v1/admin/users/'.$argv[8].'/suspend',
                        'POST',
                        ['reason' => 'B3 concurrent suspension test.'],
                    );
                    $request->setUserResolver(fn (): \App\Models\User => $actor);
                    $app->instance('request', $request);
                    $decision = $app->make(\App\Http\Requests\AdminDecisionRequest::class);
                    $decision->setUserResolver(fn (): \App\Models\User => $actor);

                    app(\App\Http\Controllers\Api\V1\AdminController::class)->suspendAccount(
                        $decision,
                        \App\Models\User::findOrFail((int) $argv[8]),
                    );
                } else {
                    exit(3);
                }
                fwrite(STDOUT, "RESULT:200\n");
            } catch (\App\Exceptions\InvitationAcceptException $exception) {
                fwrite(STDOUT, "RESULT:{$exception->httpStatus}\n");
            } catch (\App\Exceptions\ProgramActionException $exception) {
                fwrite(STDOUT, "RESULT:{$exception->httpStatus}\n");
            }
            PHP;

        $process = new Process(
            [
                PHP_BINARY,
                '-r',
                $script,
                $database,
                $action['operation'],
                (string) $action['actor_id'],
                (string) $action['program_id'],
                (string) $action['invitation_id'],
                (string) Str::uuid(),
                (string) Str::uuid(),
                (string) ($action['target_id'] ?? 0),
            ],
            base_path(),
            ['APP_ENV' => 'testing'],
        );
        $process->setTimeout(15);
        $process->start();

        return $process;
    }

    /** @param list<Process> $processes */
    private function waitUntilBlockedOnUserLock(array $processes): void
    {
        $deadline = microtime(true) + 10;

        do {
            $pids = [];

            foreach ($processes as $process) {
                if (! preg_match('/READY:(\d+)/', $process->getOutput(), $match)) {
                    continue;
                }

                $pids[] = (int) $match[1];
            }

            if (count($pids) === count($processes)) {
                $blocked = array_filter($pids, function (int $pid): bool {
                    $activity = DB::selectOne(
                        'SELECT cardinality(pg_blocking_pids(?)) AS blockers',
                        [$pid],
                    );

                    return (int) $activity->blockers > 0;
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

        $this->fail('Concurrent actions did not both wait on the held User row. '.$output);
    }

    private function resultStatus(Process $process): int
    {
        $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
        $this->assertMatchesRegularExpression('/RESULT:(\d+)/', $process->getOutput());
        preg_match('/RESULT:(\d+)/', $process->getOutput(), $match);

        return (int) $match[1];
    }
}
