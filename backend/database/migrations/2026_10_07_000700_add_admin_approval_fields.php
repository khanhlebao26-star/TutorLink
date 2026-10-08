<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->json('permissions')->default('[]')->after('status');
        });

        Schema::table('tutor_profiles', function (Blueprint $table): void {
            $table->text('review_reason')->nullable()->after('approval_status');
            $table->timestampTz('suspended_at')->nullable()->after('reviewed_at');
            $table->text('suspension_reason')->nullable()->after('suspended_at');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE tutor_profiles DROP CONSTRAINT IF EXISTS tutor_profiles_approval_status_check');
            DB::statement("ALTER TABLE tutor_profiles ADD CONSTRAINT tutor_profiles_approval_status_check CHECK (approval_status IN ('pending', 'changes_requested', 'active', 'rejected', 'cancelled', 'expired'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE tutor_profiles DROP CONSTRAINT IF EXISTS tutor_profiles_approval_status_check');
            DB::statement("ALTER TABLE tutor_profiles ADD CONSTRAINT tutor_profiles_approval_status_check CHECK (approval_status IN ('pending', 'active', 'rejected', 'cancelled', 'expired'))");
        }

        Schema::table('tutor_profiles', function (Blueprint $table): void {
            $table->dropColumn(['review_reason', 'suspended_at', 'suspension_reason']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('permissions');
        });
    }
};
