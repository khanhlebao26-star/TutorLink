<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->upSqlite();

            return;
        }

        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException(
                'Unsupported database driver.'
            );
        }

        DB::transaction(function (): void {
            DB::statement(
                'LOCK TABLE users, tutor_profiles IN ACCESS EXCLUSIVE MODE'
            );

            // Validate existing data before changing schema or values.
            $invalidUsers = DB::table('users')
                ->whereRaw("
                    role IS NULL
                    OR role NOT IN ('customer', 'tutor', 'admin')
                    OR status IS NULL
                    OR status NOT IN (
                        'active', 'inactive', 'cancelled'
                    )
                ")
                ->limit(20)
                ->pluck('id');

            if ($invalidUsers->isNotEmpty()) {
                throw new RuntimeException(
                    'Unmapped users: '.$invalidUsers->implode(', ')
                );
            }

            $invalidProfiles = DB::table('tutor_profiles')
                ->whereRaw("
                    approval_status IS NULL
                    OR NOT (
                        (
                            approval_status = 'pending'
                            AND suspended_at IS NULL
                        )
                        OR (
                            approval_status IN (
                                'changes_requested',
                                'active',
                                'rejected'
                            )
                            AND submitted_at IS NOT NULL
                            AND suspended_at IS NULL
                        )
                        OR (
                            approval_status = 'active'
                            AND submitted_at IS NOT NULL
                            AND suspended_at IS NOT NULL
                        )
                    )
                ")
                ->limit(20)
                ->pluck('id');

            if ($invalidProfiles->isNotEmpty()) {
                throw new RuntimeException(
                    'Unmapped tutor profiles: '
                    .$invalidProfiles->implode(', ')
                );
            }

            // UserRole remains unchanged, but enforce the agreed values.
            DB::statement('
                ALTER TABLE users
                DROP CONSTRAINT IF EXISTS users_role_check
            ');

            DB::statement("
                ALTER TABLE users
                ADD CONSTRAINT users_role_check
                CHECK (role IN ('customer', 'tutor', 'admin'))
            ");

            DB::statement('
                ALTER TABLE users
                DROP CONSTRAINT IF EXISTS users_status_check
            ');

            DB::statement("
                UPDATE users
                SET status = CASE status
                    WHEN 'inactive' THEN 'suspended'
                    WHEN 'cancelled' THEN 'deactivated'
                    ELSE status
                END
            ");

            DB::statement("
                ALTER TABLE users
                ALTER COLUMN status SET DEFAULT 'active'
            ");

            DB::statement("
                ALTER TABLE users
                ADD CONSTRAINT users_status_check
                CHECK (
                    status IN ('active', 'suspended', 'deactivated')
                )
            ");

            // Keep legacy fields temporarily while application code is updated.
            DB::statement('
                ALTER TABLE tutor_profiles
                ADD COLUMN status VARCHAR(20)
            ');

            DB::statement("
                UPDATE tutor_profiles
                SET status = CASE
                    WHEN suspended_at IS NOT NULL
                        THEN 'suspended'

                    WHEN approval_status = 'active'
                        THEN 'approved'

                    WHEN approval_status = 'changes_requested'
                        THEN 'changes_requested'

                    WHEN approval_status = 'rejected'
                        THEN 'rejected'

                    WHEN approval_status = 'pending'
                        AND submitted_at IS NOT NULL
                        THEN 'submitted'

                    WHEN approval_status = 'pending'
                        AND submitted_at IS NULL
                        THEN 'draft'
                END
            ");

            DB::statement("
                ALTER TABLE tutor_profiles
                ALTER COLUMN status SET DEFAULT 'draft',
                ALTER COLUMN status SET NOT NULL
            ");

            DB::statement("
                ALTER TABLE tutor_profiles
                ADD CONSTRAINT tutor_profiles_status_check
                CHECK (
                    status IN (
                        'draft',
                        'submitted',
                        'changes_requested',
                        'approved',
                        'rejected',
                        'suspended'
                    )
                )
            ");

            DB::statement('
                CREATE INDEX tutor_profiles_status_index
                ON tutor_profiles (status)
            ');
        });
    }

    private function upSqlite(): void
    {
        DB::transaction(function (): void {
            $invalidUsers = DB::table('users')
                ->whereRaw("
                role IS NULL
                OR role NOT IN ('customer', 'tutor', 'admin')
                OR status IS NULL
                OR status NOT IN ('active', 'inactive', 'cancelled')
            ")
                ->exists();

            if ($invalidUsers) {
                throw new RuntimeException('Unmapped users in SQLite.');
            }

            $invalidProfiles = DB::table('tutor_profiles')
                ->whereRaw("
                approval_status IS NULL
                OR NOT (
                    (
                        approval_status = 'pending'
                        AND suspended_at IS NULL
                    )
                    OR (
                        approval_status IN (
                            'changes_requested', 'active', 'rejected'
                        )
                        AND submitted_at IS NOT NULL
                        AND suspended_at IS NULL
                    )
                    OR (
                        approval_status = 'active'
                        AND submitted_at IS NOT NULL
                        AND suspended_at IS NOT NULL
                    )
                )
            ")
                ->exists();

            if ($invalidProfiles) {
                throw new RuntimeException(
                    'Unmapped tutor profiles in SQLite.',
                );
            }

            DB::table('users')
                ->where('status', 'inactive')
                ->update(['status' => 'suspended']);

            DB::table('users')
                ->where('status', 'cancelled')
                ->update(['status' => 'deactivated']);

            Schema::table('tutor_profiles', function (Blueprint $table): void {
                $table->string('status', 20)->default('draft');
            });

            DB::statement("
            UPDATE tutor_profiles
            SET status = CASE
                WHEN suspended_at IS NOT NULL
                    THEN 'suspended'
                WHEN approval_status = 'active'
                    THEN 'approved'
                WHEN approval_status = 'changes_requested'
                    THEN 'changes_requested'
                WHEN approval_status = 'rejected'
                    THEN 'rejected'
                WHEN approval_status = 'pending'
                    AND submitted_at IS NOT NULL
                    THEN 'submitted'
                WHEN approval_status = 'pending'
                    AND submitted_at IS NULL
                    THEN 'draft'
            END
        ");

            Schema::table('tutor_profiles', function (Blueprint $table): void {
                $table->index('status');
            });
        });
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Automatic rollback is disabled. '
            .'Restore the backup or use a reviewed reverse migration.'
        );
    }
};
