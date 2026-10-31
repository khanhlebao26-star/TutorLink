<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\MessageSendFixtures;
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

    public function test_accept_and_atomic_profile_suspension_preserve_a_consistent_outcome(): void
    {
        $customer = TestActors::customer();
        $tutor = ProgramApiFixtures::eligibleTutor();
        $admin = TestActors::admin();
        $profileId = $tutor->tutorProfile->id;
        $programId = ProgramApiFixtures::draft($tutor, $customer);
        $invitationId = ProgramApiFixtures::invitation($programId, $tutor, $customer, now('UTC')->addHours(72)->format('Y-m-d H:i:s.uP'));
        $processes = $this->runBehindUserLocks([$customer->id, $tutor->id], [
            ['operation' => 'profile_suspend', 'actor_id' => $admin->id, 'program_id' => $programId, 'invitation_id' => $invitationId, 'target_id' => $profileId],
            ['operation' => 'accept', 'actor_id' => $customer->id, 'program_id' => $programId, 'invitation_id' => $invitationId],
        ]);
        $statuses = array_map(fn (Process $process): int => $this->resultStatus($process), $processes);
        sort($statuses);
        $this->assertContains($statuses, [[200, 200], [200, 409]]);
        $this->assertDatabaseHas('users', ['id' => $tutor->id, 'status' => 'suspended']);
        $this->assertDatabaseHas('tutor_profiles', ['id' => $profileId, 'status' => 'suspended', 'status_before_suspension' => 'approved']);
        $accepted = DB::table('program_invitations')->where('id', $invitationId)->value('status') === 'accepted';
        $this->assertDatabaseCount('program_members', $accepted ? 2 : 0);
        $this->assertDatabaseCount('audit_logs', $accepted ? 3 : 2);
        $this->assertDatabaseCount('notification_outbox', $accepted ? 2 : 1);
        $this->assertDatabaseHas('programs', ['id' => $programId, 'status' => $accepted ? 'active' : 'draft']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account_suspended', 'resource_id' => $tutor->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'tutor_profile_suspended', 'resource_id' => $profileId]);
    }

    public function test_concurrent_assignment_changes_cannot_overwrite_the_same_version(): void
    {
        $tutor = ProgramApiFixtures::eligibleTutor();
        $admin = TestActors::admin();
        $reviewer = TestActors::admin();
        $profileId = $tutor->tutorProfile->id;
        $processes = $this->runBehindUserLocks([$admin->id, $reviewer->id, $tutor->id], [
            ['operation' => 'assign', 'actor_id' => $admin->id, 'program_id' => $reviewer->id, 'invitation_id' => 0, 'target_id' => $profileId],
            ['operation' => 'assign', 'actor_id' => $admin->id, 'program_id' => $reviewer->id, 'invitation_id' => 0, 'target_id' => $profileId],
        ]);
        $statuses = array_map(fn (Process $process): int => $this->resultStatus($process), $processes);
        sort($statuses);
        $this->assertSame([200, 409], $statuses);
        $this->assertDatabaseHas('tutor_profiles', ['id' => $profileId, 'assigned_admin_id' => $reviewer->id, 'assignment_version' => 1]);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'tutor_profile.assignment', 'resource_id' => $profileId]);
        $this->assertDatabaseCount('notification_outbox', 0);
    }

    public function test_two_completions_close_the_chat_only_after_both_programs_end(): void
    {
        $customer = TestActors::customer();
        $tutor = ProgramApiFixtures::eligibleTutor();

        $chat = MessageSendFixtures::conversation(
            $customer,
            $tutor,
        );

        $messageId = MessageSendFixtures::seed(
            $chat,
            $customer,
        );

        $programs = [];

        for ($index = 0; $index < 2; $index++) {
            $programId = ProgramApiFixtures::draft(
                $tutor,
                $customer,
                ['status' => 'active'],
            );

            ProgramApiFixtures::addMember(
                $programId,
                $tutor,
                'tutor',
            );

            ProgramApiFixtures::addMember(
                $programId,
                $customer,
                'customer',
            );

            $programs[] = $programId;
        }

        $actions = array_map(
            fn (int $programId): array => [
                'operation' => 'complete',
                'actor_id' => $tutor->id,
                'program_id' => $programId,
                'invitation_id' => 0,
            ],
            $programs,
        );

        $processes = $this->runBehindUserLocks(
            [$customer->id, $tutor->id],
            $actions,
        );

        foreach ($processes as $process) {
            $this->assertSame(200, $this->resultStatus($process));
        }

        foreach ($programs as $programId) {
            $this->assertDatabaseHas('programs', [
                'id' => $programId,
                'status' => 'completed',
            ]);
        }

        $this->assertDatabaseHas('conversations', [
            'id' => $chat,
            'status' => 'cancelled',
        ]);

        $this->assertDatabaseCount('conversation_members', 2);
        $this->assertDatabaseCount('messages', 1);
        $this->assertDatabaseHas('messages', [
            'id' => $messageId,
            'body' => 'Existing text',
        ]);
    }

    public function test_completion_and_invitation_send_follow_serialized_order(): void
    {
        $observedOrders = [];

        foreach ([true, false] as $sendFirst) {
            $customer = TestActors::customer();
            $tutor = ProgramApiFixtures::eligibleTutor();
            $chat = MessageSendFixtures::conversation($customer, $tutor);
            $messageId = MessageSendFixtures::seed($chat, $customer);
            $membersBefore = DB::table('conversation_members')->where('conversation_id', $chat)
                ->orderBy('user_id')->get()->toJson();
            $activeProgram = ProgramApiFixtures::draft($tutor, $customer, ['status' => 'active']);
            ProgramApiFixtures::addMember($activeProgram, $tutor, 'tutor');
            ProgramApiFixtures::addMember($activeProgram, $customer, 'customer');
            $draft = ProgramApiFixtures::draft($tutor, $customer);

            $complete = [
                'operation' => 'complete', 'actor_id' => $tutor->id,
                'program_id' => $activeProgram, 'invitation_id' => 0,
            ];
            $send = [
                'operation' => 'send', 'actor_id' => $tutor->id,
                'program_id' => $draft, 'invitation_id' => 0,
            ];

            $processes = $this->runBehindUserLocks(
                [$customer->id, $tutor->id],
                $sendFirst ? [$send, $complete] : [$complete, $send],
            );

            foreach ($processes as $process) {
                $this->assertSame(200, $this->resultStatus($process));
            }

            $invitation = DB::table('program_invitations')->where('program_id', $draft)
                ->where('status', 'pending')->first();
            $this->assertNotNull($invitation);
            $completeAudit = DB::table('audit_logs')->where('action', 'program.complete')
                ->where('resource_type', 'program')->where('resource_id', $activeProgram)->value('id');
            $sendAudit = DB::table('audit_logs')->where('action', 'invitation.send')
                ->where('resource_type', 'program_invitation')->where('resource_id', $invitation->id)->value('id');
            $this->assertNotNull($completeAudit);
            $this->assertNotNull($sendAudit);

            $sentBeforeCompletion = (int) $sendAudit < (int) $completeAudit;
            $observedOrders[] = $sentBeforeCompletion;
            $this->assertDatabaseHas('programs', ['id' => $activeProgram, 'status' => 'completed']);
            $this->assertDatabaseHas('conversations', [
                'id' => $chat, 'status' => $sentBeforeCompletion ? 'active' : 'cancelled',
            ]);
            $conversation = DB::table('conversations')->where('id', $chat)->first();
            $this->assertSame(! $sentBeforeCompletion, $conversation->ended_at !== null);
            $this->assertSame($membersBefore, DB::table('conversation_members')
                ->where('conversation_id', $chat)->orderBy('user_id')->get()->toJson());
            $this->assertSame(1, DB::table('messages')->where('conversation_id', $chat)->count());
            $this->assertDatabaseHas('messages', ['id' => $messageId, 'body' => 'Existing text']);
        }

        $this->assertContains(true, $observedOrders);
        $this->assertContains(false, $observedOrders);
    }

    public function test_accept_and_invitation_decisions_commit_only_one_outcome(): void
    {
        foreach (['reject', 'cancel'] as $decision) {
            foreach ([true, false] as $acceptFirst) {
                $customer = TestActors::customer();
                $tutor = ProgramApiFixtures::eligibleTutor();
                $program = ProgramApiFixtures::draft($tutor, $customer);
                $invitation = ProgramApiFixtures::invitation($program, $tutor, $customer, now('UTC')->addHours(72)->toDateTimeString());
                $accept = ['operation' => 'accept', 'actor_id' => $customer->id, 'program_id' => $program, 'invitation_id' => $invitation];
                $resolve = ['operation' => 'decision_'.$decision, 'actor_id' => $decision === 'reject' ? $customer->id : $tutor->id,
                    'program_id' => $program, 'invitation_id' => $invitation];

                $processes = $this->runBehindUserLocks([$customer->id, $tutor->id], $acceptFirst ? [$accept, $resolve] : [$resolve, $accept]);
                $statuses = array_map(fn (Process $process): int => $this->resultStatus($process), $processes);
                sort($statuses);

                $this->assertSame([200, 409], $statuses);
                $accepted = DB::table('program_invitations')->where('id', $invitation)->value('status') === 'accepted';
                $this->assertSame($acceptFirst, $accepted);
                $this->assertDatabaseHas('programs', ['id' => $program, 'status' => $accepted ? 'active' : 'draft']);
                $this->assertSame($accepted ? 2 : 0, DB::table('program_members')->where('program_id', $program)->count());
                $this->assertSame(1, DB::table('audit_logs')->where('resource_type', 'program_invitation')->where('resource_id', $invitation)->count());
                $this->assertSame(1, DB::table('notification_outbox')->where('resource_type', 'program_invitation')->where('resource_id', $invitation)->count());
                if (! $accepted) {
                    $this->assertDatabaseHas('program_invitations', ['id' => $invitation, 'status' => $decision === 'reject' ? 'rejected' : 'cancelled']);
                }
            }
        }
    }

    public function test_expiry_worker_competes_with_accept_and_another_worker_without_duplicate_events(): void
    {
        foreach ([true, false] as $overdue) {
            $customer = TestActors::customer();
            $tutor = ProgramApiFixtures::eligibleTutor();
            $program = ProgramApiFixtures::draft($tutor, $customer);
            $invitation = ProgramApiFixtures::invitation($program, $tutor, $customer,
                ($overdue ? now('UTC')->subMinute() : now('UTC')->addHour())->toDateTimeString());
            $processes = $this->runBehindUserLocks([$customer->id, $tutor->id], [
                ['operation' => 'worker_expire', 'actor_id' => $tutor->id, 'program_id' => $program, 'invitation_id' => $invitation],
                ['operation' => 'accept', 'actor_id' => $customer->id, 'program_id' => $program, 'invitation_id' => $invitation],
                ['operation' => 'worker_expire', 'actor_id' => $tutor->id, 'program_id' => $program, 'invitation_id' => $invitation],
            ]);
            $statuses = array_map(fn (Process $process): int => $this->resultStatus($process), $processes);

            $this->assertSame([200, $overdue ? 409 : 200, 200], $statuses);
            $this->assertDatabaseHas('program_invitations', ['id' => $invitation, 'status' => $overdue ? 'expired' : 'accepted']);
            $this->assertDatabaseHas('programs', ['id' => $program, 'status' => $overdue ? 'draft' : 'active']);
            $this->assertSame($overdue ? 0 : 2, DB::table('program_members')->where('program_id', $program)->count());
            $this->assertSame(1, DB::table('audit_logs')->where('resource_type', 'program_invitation')->where('resource_id', $invitation)->count());
            $this->assertSame(1, DB::table('notification_outbox')->where('resource_type', 'program_invitation')->where('resource_id', $invitation)->count());
        }
    }

    public function test_expiry_and_invitation_cancellation_keep_the_expired_outcome(): void
    {
        $customer = TestActors::customer();
        $tutor = ProgramApiFixtures::eligibleTutor();
        $program = ProgramApiFixtures::draft($tutor, $customer);
        $invitation = ProgramApiFixtures::invitation($program, $tutor, $customer, now('UTC')->subMinute()->toDateTimeString());

        $processes = $this->runBehindUserLocks([$customer->id, $tutor->id], [
            ['operation' => 'decision_cancel', 'actor_id' => $tutor->id, 'program_id' => $program, 'invitation_id' => $invitation],
            ['operation' => 'worker_expire', 'actor_id' => $tutor->id, 'program_id' => $program, 'invitation_id' => $invitation],
        ]);

        $this->assertSame([409, 200], array_map(fn (Process $process): int => $this->resultStatus($process), $processes));
        $this->assertDatabaseHas('program_invitations', ['id' => $invitation, 'status' => 'expired']);
        $this->assertDatabaseCount('program_members', 0);
        $this->assertDatabaseCount('audit_logs', 1);
        $this->assertDatabaseCount('notification_outbox', 1);
    }

    public function test_program_completion_and_task_creation_serialize_on_the_program_lock(): void
    {
        foreach ([true, false] as $completeFirst) {
            $customer = TestActors::customer();
            $tutor = ProgramApiFixtures::eligibleTutor();
            $program = ProgramApiFixtures::draft($tutor, $customer, ['status' => 'active']);
            ProgramApiFixtures::addMember($program, $tutor, 'tutor');
            ProgramApiFixtures::addMember($program, $customer, 'customer');
            $complete = ['operation' => 'complete', 'actor_id' => $tutor->id, 'program_id' => $program, 'invitation_id' => 0];
            $createTask = ['operation' => 'create_task', 'actor_id' => $tutor->id, 'program_id' => $program, 'invitation_id' => $customer->id];
            $processes = [];

            DB::beginTransaction();
            try {
                DB::table('programs')->where('id', $program)->lockForUpdate()->first();
                foreach ($completeFirst ? [$complete, $createTask] : [$createTask, $complete] as $action) {
                    $process = $this->startActionProcess(DB::connection()->getDatabaseName(), $action);
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

            $this->assertSame($completeFirst ? [200, 409] : [201, 409], array_map(fn (Process $process): int => $this->resultStatus($process), $processes));
            $this->assertDatabaseHas('programs', ['id' => $program, 'status' => $completeFirst ? 'completed' : 'active']);
            $this->assertSame($completeFirst ? 0 : 1, DB::table('tasks')->where('program_id', $program)->where('status', 'todo')->count());
            $this->assertSame($completeFirst ? 1 : 0, DB::table('audit_logs')->where('resource_type', 'program')->where('resource_id', $program)->count());
            $this->assertSame($completeFirst ? 1 : 0, DB::table('notification_outbox')->where('resource_type', 'program')->where('resource_id', $program)->count());
            $this->assertSame(2, DB::table('program_members')->where('program_id', $program)->count());
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
                $resultStatus = 200;
                if ($argv[2] === 'accept') {
                    app(\App\Actions\Invitations\AcceptInvitation::class)->execute(
                        $actor,
                        (int) $argv[5],
                        $argv[6],
                        $argv[7],
                    );
                } elseif (in_array($argv[2], ['cancel', 'complete'], true)) {
                    app(\App\Actions\Programs\TransitionProgram::class)->execute(
                        $actor,
                        (int) $argv[4],
                        $argv[2],
                        $argv[7],
                    );
                } elseif ($argv[2] === 'create_task') {
                    $request = \Illuminate\Http\Request::create('/api/v1/programs/'.$argv[4].'/tasks', 'POST', [
                        'title' => 'Concurrent task', 'assigned_to' => (int) $argv[5],
                    ]);
                    $request->setUserResolver(fn () => $actor);
                    $resultStatus = app(\App\Http\Controllers\Api\V1\TaskController::class)->store($request, (int) $argv[4])->getStatusCode();
                } elseif ($argv[2] === 'worker_expire') {
                    app(\App\Actions\Invitations\ResolveInvitation::class)->expire((int) $argv[5], $argv[7]);
                } elseif (str_starts_with($argv[2], 'decision_')) {
                    app(\App\Actions\Invitations\ResolveInvitation::class)->execute(
                        $actor, (int) $argv[5], substr($argv[2], 9), null, $argv[7],
                    );
                } elseif ($argv[2] === 'send') {
                    app(\App\Actions\Invitations\SendInvitation::class)->execute(
                        $actor,
                        (int) $argv[4],
                        ['expected_version' => 1],
                        $argv[6],
                        $argv[7],
                    );
                } elseif ($argv[2] === 'expire') {
                    app(\App\Actions\Programs\UpdateProgramDraft::class)->execute(
                        $actor,
                        (int) $argv[4],
                        ['expected_version' => 1, 'title' => 'Edited after expiry'],
                        $argv[7],
                    );
                } elseif ($argv[2] === 'profile_suspend') {
                    app(\App\Actions\TutorProfiles\SuspendTutorProfile::class)->execute($actor, (int) $argv[8], true, 'H02 race suspension');
                } elseif ($argv[2] === 'assign') {
                    app(\App\Actions\TutorProfiles\AssignTutorProfile::class)->execute($actor, (int) $argv[8], [
                        'assigned_admin_id' => (int) $argv[4], 'assignment_version' => 0, 'reason' => 'H02 concurrent assignment',
                    ]);
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
                fwrite(STDOUT, "RESULT:{$resultStatus}\n");
            } catch (\Illuminate\Http\Exceptions\HttpResponseException $exception) {
                fwrite(STDOUT, "RESULT:{$exception->getResponse()->getStatusCode()}\n");
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
