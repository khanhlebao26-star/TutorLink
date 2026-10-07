<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

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
}
