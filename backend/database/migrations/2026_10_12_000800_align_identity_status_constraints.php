<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::transaction(function (): void {
            if (DB::table('tutor_profiles')->whereIn('approval_status', ['cancelled', 'expired'])->exists()) {
                throw new RuntimeException(
                    'Cannot align tutor profile approval statuses until cancelled/expired rows have an approved mapping.',
                );
            }

            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_status_check');
            DB::table('users')->where('status', 'pending')->update(['status' => 'draft']);
            DB::table('users')->where('status', 'suspended')->update(['status' => 'inactive']);
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('draft', 'active', 'inactive', 'cancelled'))");

            DB::statement('ALTER TABLE tutor_profiles DROP CONSTRAINT IF EXISTS tutor_profiles_approval_status_check');
            DB::statement("ALTER TABLE tutor_profiles ADD CONSTRAINT tutor_profiles_approval_status_check CHECK (approval_status IN ('pending', 'changes_requested', 'active', 'rejected'))");
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::transaction(function (): void {
            if (DB::table('users')->whereIn('status', ['draft', 'inactive'])->exists()) {
                throw new RuntimeException('Cannot restore the legacy user status constraint while draft/inactive rows exist.');
            }

            if (DB::table('tutor_profiles')->where('approval_status', 'changes_requested')->exists()) {
                throw new RuntimeException('Cannot restore the legacy tutor profile constraint while changes_requested rows exist.');
            }

            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_status_check');
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('pending', 'active', 'suspended', 'cancelled'))");

            DB::statement('ALTER TABLE tutor_profiles DROP CONSTRAINT IF EXISTS tutor_profiles_approval_status_check');
            DB::statement("ALTER TABLE tutor_profiles ADD CONSTRAINT tutor_profiles_approval_status_check CHECK (approval_status IN ('pending', 'active', 'rejected', 'cancelled', 'expired'))");
        });
    }
};
