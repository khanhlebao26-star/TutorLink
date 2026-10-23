<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->validateLegacyData();

        Schema::table('programs', function (Blueprint $table): void {
            $table->foreignId('customer_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
        });

        $this->backfillProgramCustomers();

        if (DB::getDriverName() === 'pgsql') {
            $this->dropLegacyPostgresConstraints();
        }

        DB::table('programs')->where('status', 'pending')->update(['status' => 'draft']);
        DB::table('program_invitations')->where('status', 'active')->update(['status' => 'accepted']);
        DB::table('program_sessions')->where('status', 'planned')->update(['status' => 'scheduled']);
        DB::table('task_submissions')->where('status', 'reviewed')->update(['status' => 'accepted']);

        if (DB::getDriverName() === 'pgsql') {
            $this->addPostgresConstraints();
        }
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Automatic rollback is disabled. '
            .'Restore the backup or use a reviewed reverse migration.'
        );
    }

    private function validateLegacyData(): void
    {
        $this->assertAllowedValues('programs', 'status', [
            'pending', 'draft', 'active', 'paused', 'completed', 'cancelled',
        ]);
        $this->assertAllowedValues('program_invitations', 'status', [
            'pending', 'active', 'accepted', 'rejected', 'cancelled', 'expired',
        ]);
        $this->assertAllowedValues('program_members', 'member_role', ['customer', 'tutor']);
        $this->assertAllowedValues('program_members', 'status', ['active', 'cancelled', 'expired']);
        $this->assertAllowedValues('program_sessions', 'status', [
            'planned', 'scheduled', 'completed', 'cancelled',
        ]);
        $this->assertAllowedValues('lesson_sessions', 'status', [
            'scheduled', 'completed', 'cancelled',
        ]);
        $this->assertAllowedValues('task_submissions', 'status', [
            'submitted', 'reviewed', 'accepted', 'revision_requested',
        ]);

        if (DB::table('program_invitations')
            ->select('program_id')
            ->where('status', 'pending')
            ->groupBy('program_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists()) {
            throw new RuntimeException(
                'Multiple pending invitations exist for a Program; resolve the legacy mapping first.'
            );
        }

        if (DB::table('program_members')
            ->select('program_id')
            ->where('member_role', 'customer')
            ->groupBy('program_id')
            ->havingRaw('COUNT(DISTINCT user_id) > 1')
            ->exists()) {
            throw new RuntimeException(
                'Multiple Customer members exist for a Program; resolve the legacy mapping first.'
            );
        }
    }

    private function assertAllowedValues(string $table, string $column, array $allowed): void
    {
        $invalid = DB::table($table)
            ->whereNull($column)
            ->orWhereNotIn($column, $allowed)
            ->limit(10)
            ->pluck('id');

        if ($invalid->isNotEmpty()) {
            throw new RuntimeException(
                "Unmapped {$table}.{$column} values on rows: ".$invalid->implode(', ')
            );
        }
    }

    private function backfillProgramCustomers(): void
    {
        foreach (DB::table('programs')->select('id')->orderBy('id')->get() as $program) {
            $members = DB::table('program_members')
                ->where('program_id', $program->id)
                ->where('member_role', 'customer')
                ->pluck('user_id');

            $invitees = DB::table('program_invitations')
                ->where('program_id', $program->id)
                ->whereIn('status', ['pending', 'active', 'accepted'])
                ->pluck('invitee_user_id');

            $customerIds = $members->merge($invitees)->unique()->values();

            if ($customerIds->count() !== 1) {
                throw new RuntimeException(
                    "Program {$program->id} has no unique legacy Customer mapping."
                );
            }

            $isCustomer = DB::table('users')
                ->where('id', $customerIds[0])
                ->where('role', 'customer')
                ->exists();

            if (! $isCustomer) {
                throw new RuntimeException(
                    "Program {$program->id} maps to a missing or non-Customer user."
                );
            }

            DB::table('programs')
                ->where('id', $program->id)
                ->update(['customer_id' => $customerIds[0]]);
        }
    }

    private function addPostgresConstraints(): void
    {
        DB::statement("ALTER TABLE programs ALTER COLUMN status SET DEFAULT 'draft'");
        DB::statement('ALTER TABLE programs ALTER COLUMN customer_id SET NOT NULL');
        DB::statement("ALTER TABLE programs ADD CONSTRAINT programs_status_check CHECK (status IN ('draft', 'active', 'paused', 'completed', 'cancelled'))");

        DB::statement("ALTER TABLE program_invitations ADD CONSTRAINT program_invitations_status_check CHECK (status IN ('pending', 'accepted', 'rejected', 'cancelled', 'expired'))");
        DB::statement("CREATE UNIQUE INDEX program_invitations_one_pending_per_program ON program_invitations (program_id) WHERE status = 'pending'");

        DB::statement("ALTER TABLE program_members ADD CONSTRAINT program_members_role_check CHECK (member_role IN ('customer', 'tutor'))");
        DB::statement("ALTER TABLE program_members ADD CONSTRAINT program_members_status_check CHECK (status IN ('active', 'cancelled', 'expired'))");
        DB::statement("CREATE UNIQUE INDEX program_members_one_customer ON program_members (program_id) WHERE member_role = 'customer'");

        DB::statement("ALTER TABLE program_sessions ADD CONSTRAINT program_sessions_status_check CHECK (status IN ('scheduled', 'completed', 'cancelled'))");
        DB::statement("ALTER TABLE lesson_sessions ADD CONSTRAINT lesson_sessions_status_check CHECK (status IN ('scheduled', 'completed', 'cancelled'))");

        DB::statement("ALTER TABLE task_submissions ADD CONSTRAINT task_submissions_status_check CHECK (status IN ('submitted', 'accepted', 'revision_requested'))");
    }

    private function dropLegacyPostgresConstraints(): void
    {
        DB::statement('ALTER TABLE programs DROP CONSTRAINT IF EXISTS programs_status_check');
        DB::statement('ALTER TABLE program_invitations DROP CONSTRAINT IF EXISTS program_invitations_status_check');
        DB::statement('ALTER TABLE program_members DROP CONSTRAINT IF EXISTS program_members_role_check');
        DB::statement('ALTER TABLE program_members DROP CONSTRAINT IF EXISTS program_members_status_check');
        DB::statement('ALTER TABLE program_sessions DROP CONSTRAINT IF EXISTS program_sessions_status_check');
        DB::statement('ALTER TABLE lesson_sessions DROP CONSTRAINT IF EXISTS lesson_sessions_status_check');
        DB::statement('ALTER TABLE task_submissions DROP CONSTRAINT IF EXISTS task_submissions_status_check');
        DB::statement('DROP INDEX IF EXISTS program_invitations_pending_unique');
        DB::statement('DROP INDEX IF EXISTS program_members_one_active_customer');
    }
};
