<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            Schema::table('tutor_profiles', function (Blueprint $table): void {
                $table->foreignId('assigned_admin_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->foreignId('assigned_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->timestampTz('assigned_at')->nullable();
                $table->integer('assignment_version')->default(0);
                $table->index(
                    ['status', 'assigned_admin_id', 'submitted_at'],
                    'tutor_profiles_assignment_queue_index',
                );
            });

            Schema::table('reports', function (Blueprint $table): void {
                $table->foreignId('assigned_admin_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->foreignId('assigned_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->timestampTz('assigned_at')->nullable();
                $table->integer('assignment_version')->default(0);
                $table->index(
                    ['status', 'assigned_admin_id', 'created_at'],
                    'reports_assignment_queue_index',
                );
            });

            if (DB::getDriverName() === 'pgsql') {
                DB::statement(
                    'ALTER TABLE tutor_profiles ADD CONSTRAINT tutor_profiles_assignment_version_check CHECK (assignment_version >= 0)'
                );
                DB::statement(
                    'ALTER TABLE reports ADD CONSTRAINT reports_assignment_version_check CHECK (assignment_version >= 0)'
                );
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement(
                    'ALTER TABLE tutor_profiles DROP CONSTRAINT IF EXISTS tutor_profiles_assignment_version_check'
                );
                DB::statement(
                    'ALTER TABLE reports DROP CONSTRAINT IF EXISTS reports_assignment_version_check'
                );
            }

            Schema::table('tutor_profiles', function (Blueprint $table): void {
                $table->dropIndex('tutor_profiles_assignment_queue_index');
                $table->dropForeign(['assigned_admin_id']);
                $table->dropForeign(['assigned_by']);
                $table->dropColumn([
                    'assigned_admin_id',
                    'assigned_by',
                    'assigned_at',
                    'assignment_version',
                ]);
            });

            Schema::table('reports', function (Blueprint $table): void {
                $table->dropIndex('reports_assignment_queue_index');
                $table->dropForeign(['assigned_admin_id']);
                $table->dropForeign(['assigned_by']);
                $table->dropColumn([
                    'assigned_admin_id',
                    'assigned_by',
                    'assigned_at',
                    'assignment_version',
                ]);
            });
        });
    }
};
