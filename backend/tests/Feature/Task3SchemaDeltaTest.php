<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class Task3SchemaDeltaTest extends TestCase
{
    use RefreshDatabase;

    public function test_task3_schema_columns_exist(): void
    {
        $this->assertTrue(Schema::hasColumns('tutor_profiles', [
            'assigned_admin_id', 'assigned_by', 'assigned_at', 'assignment_version',
        ]));
        $this->assertTrue(Schema::hasColumns('reports', [
            'assigned_admin_id', 'assigned_by', 'assigned_at', 'assignment_version',
        ]));
        $this->assertTrue(Schema::hasColumns('notifications', ['event_key']));
        $this->assertTrue(Schema::hasTable('notification_outbox'));
        $this->assertTrue(Schema::hasColumns('tasks', ['version', 'current_submission_id']));
        $this->assertTrue(Schema::hasColumns('task_submissions', [
            'submission_version', 'idempotency_key', 'request_hash',
        ]));
    }

    public function test_assignment_and_outbox_reject_invalid_references_and_duplicates(): void
    {
        $admin = User::factory()->admin()->create();
        $tutor = User::factory()->create(['role' => 'tutor']);
        $profileId = DB::table('tutor_profiles')->insertGetId([
            'user_id' => $tutor->id,
            'headline' => 'Assignment schema test',
            'status' => 'draft',
            'approval_status' => 'pending',
        ]);

        DB::table('tutor_profiles')->where('id', $profileId)->update([
            'assigned_admin_id' => $admin->id,
            'assigned_by' => $admin->id,
            'assigned_at' => now(),
            'assignment_version' => 1,
        ]);
        $this->assertDatabaseHas('tutor_profiles', [
            'id' => $profileId,
            'assigned_admin_id' => $admin->id,
            'assignment_version' => 1,
        ]);

        $reportId = DB::table('reports')->insertGetId([
            'reporter_id' => $admin->id,
            'resource_type' => 'task',
            'resource_id' => 1,
            'reason' => 'schema_test',
            'assigned_admin_id' => $admin->id,
            'assigned_by' => $admin->id,
            'assigned_at' => now(),
            'assignment_version' => 1,
        ]);
        $this->assertDatabaseHas('reports', [
            'id' => $reportId,
            'assigned_admin_id' => $admin->id,
            'assignment_version' => 1,
        ]);

        $this->assertRejected(fn () => DB::table('tutor_profiles')
            ->where('id', $profileId)
            ->update(['assigned_admin_id' => PHP_INT_MAX]), 'Assignment FK accepted a missing user.');
        $this->assertRejected(fn () => DB::table('reports')
            ->where('id', $reportId)
            ->update(['assigned_by' => PHP_INT_MAX]), 'Report assignment FK accepted a missing user.');

        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $admin->id,
            'type' => 'task_submitted',
            'event_key' => 'task:1:submitted',
            'payload' => json_encode(['task_id' => 1]),
            'created_at' => now(),
        ]);
        $this->assertRejected(fn () => DB::table('notifications')->insert([
            'id' => (string) Str::uuid(),
            'user_id' => $admin->id,
            'type' => 'task_submitted',
            'event_key' => 'task:1:submitted',
            'payload' => json_encode(['task_id' => 1]),
            'created_at' => now(),
        ]), 'Notifications accepted a duplicate event key.');

        $outbox = [
            'id' => (string) Str::uuid(),
            'user_id' => $admin->id,
            'type' => 'task_submitted',
            'resource_type' => 'task',
            'resource_id' => 1,
            'event_key' => 'task:1:submitted',
            'payload' => json_encode(['task_id' => 1]),
            'created_at' => now(),
        ];
        DB::table('notification_outbox')->insert($outbox);
        $this->assertRejected(fn () => DB::table('notification_outbox')->insert([
            ...$outbox,
            'id' => (string) Str::uuid(),
        ]), 'Outbox accepted a duplicate event key.');
        $this->assertRejected(fn () => DB::table('notification_outbox')->insert([
            ...$outbox,
            'id' => (string) Str::uuid(),
            'event_key' => 'task:2:submitted',
            'user_id' => PHP_INT_MAX,
        ]), 'Outbox accepted a missing recipient.');

        if (DB::getDriverName() === 'pgsql') {
            $this->assertRejected(fn () => DB::table('tutor_profiles')
                ->where('id', $profileId)
                ->update(['assignment_version' => -1]), 'Assignment accepted a negative version.');
            $this->assertRejected(fn () => DB::table('reports')
                ->where('id', $reportId)
                ->update(['assignment_version' => -1]), 'Report assignment accepted a negative version.');
            $this->assertRejected(fn () => DB::table('notification_outbox')->insert([
                ...$outbox,
                'id' => (string) Str::uuid(),
                'event_key' => 'task:3:submitted',
                'attempt_count' => -1,
            ]), 'Outbox accepted a negative attempt count.');
        }
    }

    public function test_task_submissions_keep_versions_and_current_pointer_matches_task_and_customer(): void
    {
        $tutor = User::factory()->create(['role' => 'tutor']);
        $profile = $tutor->tutorProfile()->create([
            'headline' => 'Submission schema test',
            'status' => 'approved',
        ]);
        $customer = User::factory()->create(['role' => 'customer']);
        $otherCustomer = User::factory()->create(['role' => 'customer']);
        $programId = DB::table('programs')->insertGetId([
            'owner_tutor_profile_id' => $profile->id,
            'customer_id' => $customer->id,
            'title' => 'Submission schema test program',
            'delivery_mode' => 'online',
            'agreed_fee_minor' => 0,
            'fee_unit' => 'session',
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $taskId = $this->createTask($programId, $customer->id);
        $otherTaskId = $this->createTask($programId, $otherCustomer->id);
        $this->assertSame(1, DB::table('tasks')->where('id', $taskId)->value('version'));

        $firstId = $this->createSubmission($taskId, $customer->id, 1, (string) Str::uuid());
        DB::table('tasks')->where('id', $taskId)->update(['current_submission_id' => $firstId]);
        $secondId = $this->createSubmission($taskId, $customer->id, 2, (string) Str::uuid());
        DB::table('tasks')->where('id', $taskId)->update(['current_submission_id' => $secondId]);

        $this->assertSame(2, DB::table('task_submissions')->where('task_id', $taskId)->count());
        $this->assertSame($secondId, DB::table('tasks')->where('id', $taskId)->value('current_submission_id'));

        $this->assertRejected(fn () => $this->createSubmission(
            $taskId,
            $customer->id,
            2,
            (string) Str::uuid(),
        ), 'Task accepted a duplicate submission version.');
        $this->assertRejected(fn () => $this->createSubmission(
            $taskId,
            $customer->id,
            3,
            DB::table('task_submissions')->where('id', $firstId)->value('idempotency_key'),
        ), 'Task accepted a duplicate idempotency key.');
        $this->assertRejected(fn () => DB::table('tasks')->where('id', $otherTaskId)
            ->update(['current_submission_id' => $secondId]), 'Task accepted another Task/customer submission.');
    }

    private function createTask(int $programId, int $customerId): int
    {
        return DB::table('tasks')->insertGetId([
            'program_id' => $programId,
            'title' => 'Submission schema test task',
            'status' => 'todo',
            'assigned_to' => $customerId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function createSubmission(int $taskId, int $customerId, int $version, string $key): int
    {
        return DB::table('task_submissions')->insertGetId([
            'task_id' => $taskId,
            'customer_id' => $customerId,
            'content' => "Version {$version}",
            'status' => 'submitted',
            'submitted_at' => now(),
            'submission_version' => $version,
            'idempotency_key' => $key,
            'request_hash' => str_repeat(dechex($version), 64),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function assertRejected(callable $operation, string $message): void
    {
        try {
            DB::transaction($operation);
        } catch (QueryException) {
            $this->assertTrue(true);

            return;
        }

        $this->fail($message);
    }
}
