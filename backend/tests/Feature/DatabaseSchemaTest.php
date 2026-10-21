<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_suite_uses_the_configured_database(): void
    {
        $this->assertContains(DB::getDriverName(), ['sqlite', 'pgsql']);

        if (DB::getDriverName() === 'sqlite') {
            $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        }
    }

    public function test_core_business_tables_are_created(): void
    {
        $tables = [
            'customer_profiles',
            'tutor_profiles',
            'verification_documents',
            'categories',
            'subcategories',
            'specializations',
            'service_listings',
            'conversations',
            'messages',
            'programs',
            'program_invitations',
            'program_members',
            'program_sessions',
            'lesson_sessions',
            'tasks',
            'task_submissions',
            'progress_records',
            'announcements',
            'fee_records',
            'reviews',
            'files',
            'file_links',
            'notifications',
            'reports',
            'audit_logs',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }
    }

    public function test_users_match_the_tutorlink_identity_contract(): void
    {
        $this->assertTrue(Schema::hasColumns('users', [
            'id',
            'role',
            'status',
            'full_name',
            'email',
            'password_hash',
            'date_of_birth',
            'email_verified_at',
            'terms_version',
            'suspended_at',
            'deleted_at',
        ]));

        $user = User::factory()->create();

        $this->assertSame('password_hash', $user->getAuthPasswordName());
        $this->assertNotEmpty($user->getAuthPassword());
    }

    public function test_postgres_accepts_current_user_statuses(): void
    {
        $this->requirePostgres();

        foreach (['active', 'suspended', 'deactivated'] as $status) {
            $user = User::factory()->create([
                'email' => "status-{$status}@example.test",
                'status' => $status,
            ]);

            $this->assertSame($status, $user->status);
        }
    }

    public function test_postgres_rejects_legacy_inactive_user_status(): void
    {
        $this->requirePostgres();
        $this->expectException(QueryException::class);

        User::factory()->create(['status' => 'inactive']);
    }

    public function test_postgres_rejects_legacy_pending_user_status(): void
    {
        $this->requirePostgres();
        $this->expectException(QueryException::class);

        User::factory()->create(['status' => 'pending']);
    }

    public function test_postgres_accepts_current_tutor_profile_statuses(): void
    {
        $this->requirePostgres();

        foreach ([
            'draft',
            'submitted',
            'changes_requested',
            'approved',
            'rejected',
            'suspended',
        ] as $status) {
            $user = User::factory()->create(['role' => 'tutor']);

            $profile = $user->tutorProfile()->create([
                'headline' => "Profile {$status}",
                'status' => $status,
            ]);

            $this->assertSame($status, $profile->status);
        }
    }

    public function test_postgres_rejects_cancelled_tutor_profile_status(): void
    {
        $this->requirePostgres();
        $this->expectException(QueryException::class);

        $user = User::factory()->create(['role' => 'tutor']);

        $user->tutorProfile()->create([
            'headline' => 'Invalid cancelled profile',
            'status' => 'cancelled',
        ]);
    }

    public function test_postgres_rejects_expired_tutor_profile_status(): void
    {
        $this->requirePostgres();
        $this->expectException(QueryException::class);

        $user = User::factory()->create(['role' => 'tutor']);

        $user->tutorProfile()->create([
            'headline' => 'Invalid expired profile',
            'status' => 'expired',
        ]);
    }

    public function test_laravel_and_learning_sessions_use_distinct_tables(): void
    {
        $this->assertTrue(Schema::hasColumns('sessions', [
            'id',
            'user_id',
            'payload',
            'last_activity',
        ]));

        $this->assertTrue(Schema::hasColumns('program_sessions', [
            'program_id',
            'program_version',
            'sequence_number',
            'planned_starts_at',
            'planned_ends_at',
        ]));

        $this->assertTrue(Schema::hasColumns('lesson_sessions', [
            'program_session_id',
            'status',
            'started_at',
            'ended_at',
        ]));
    }

    public function test_program_and_invitation_store_immutable_offer_snapshots(): void
    {
        $this->assertTrue(Schema::hasColumns('programs', [
            'version',
            'offer_snapshot',
            'snapshot_locked_at',
        ]));

        $this->assertTrue(Schema::hasColumns('program_invitations', [
            'program_version',
            'offer_snapshot',
            'idempotency_key',
            'expires_at',
        ]));
    }

    private function requirePostgres(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL constraint tests require the pgsql test connection.');
        }
    }
}
