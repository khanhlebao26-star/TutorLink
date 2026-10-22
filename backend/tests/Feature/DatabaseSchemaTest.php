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

    public function test_programs_have_a_customer_id_column(): void
    {
        $this->assertTrue(Schema::hasColumn('programs', 'customer_id'));
    }

    public function test_postgres_accepts_completed_programs_and_requires_a_customer(): void
    {
        $this->requirePostgres();
        [$tutorProfile, $customer] = $this->programActors();

        $programId = $this->insertProgram($tutorProfile->id, $customer->id, 'completed');

        $this->assertDatabaseHas('programs', [
            'id' => $programId,
            'status' => 'completed',
            'customer_id' => $customer->id,
        ]);

        $this->expectException(QueryException::class);
        DB::table('programs')->insert([
            ...$this->programAttributes($tutorProfile->id, null, 'draft'),
        ]);
    }

    public function test_postgres_allows_only_one_customer_and_pending_invitation_per_program(): void
    {
        $this->requirePostgres();
        [$tutorProfile, $customer] = $this->programActors();
        $otherCustomer = User::factory()->create(['role' => 'customer']);
        $programId = $this->insertProgram($tutorProfile->id, $customer->id, 'draft');

        DB::table('program_members')->insert([
            'program_id' => $programId,
            'user_id' => $customer->id,
            'member_role' => 'customer',
            'status' => 'active',
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            DB::transaction(function () use ($programId, $otherCustomer): void {
                DB::table('program_members')->insert([
                    'program_id' => $programId,
                    'user_id' => $otherCustomer->id,
                    'member_role' => 'customer',
                    'status' => 'cancelled',
                    'joined_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
            $this->fail('A Program must not have more than one Customer membership.');
        } catch (QueryException) {
            // The partial unique index includes historical memberships.
        }

        $this->insertPendingInvitation($programId, $tutorProfile->user_id, $customer->id);

        $this->expectException(QueryException::class);
        DB::transaction(function () use ($programId, $tutorProfile, $otherCustomer): void {
            $this->insertPendingInvitation($programId, $tutorProfile->user_id, $otherCustomer->id);
        });
    }

    private function programActors(): array
    {
        $tutor = User::factory()->create(['role' => 'tutor']);
        $tutorProfile = $tutor->tutorProfile()->create([
            'headline' => 'Schema test tutor',
            'status' => 'approved',
        ]);
        $customer = User::factory()->create(['role' => 'customer']);

        return [$tutorProfile, $customer];
    }

    private function insertProgram(int $tutorProfileId, int $customerId, string $status): int
    {
        return DB::table('programs')->insertGetId(
            $this->programAttributes($tutorProfileId, $customerId, $status)
        );
    }

    private function programAttributes(int $tutorProfileId, ?int $customerId, string $status): array
    {
        return [
            'owner_tutor_profile_id' => $tutorProfileId,
            'customer_id' => $customerId,
            'title' => 'Schema test program',
            'delivery_mode' => 'online',
            'agreed_fee_minor' => 0,
            'fee_unit' => 'session',
            'status' => $status,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    private function insertPendingInvitation(int $programId, int $tutorId, int $customerId): void
    {
        DB::table('program_invitations')->insert([
            'program_id' => $programId,
            'program_version' => 1,
            'invitee_user_id' => $customerId,
            'invited_by' => $tutorId,
            'status' => 'pending',
            'offer_snapshot' => json_encode(['title' => 'Schema test program']),
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
            'expires_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function requirePostgres(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PostgreSQL constraint tests require the pgsql test connection.');
        }
    }
}
