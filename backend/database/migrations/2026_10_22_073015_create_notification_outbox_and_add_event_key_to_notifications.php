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
            Schema::table('notifications', function (Blueprint $table): void {
                $table->string('event_key', 191)->nullable();
            });

            DB::table('notifications')
                ->whereNull('event_key')
                ->orderBy('id')
                ->chunkById(500, function ($notifications): void {
                    foreach ($notifications as $notification) {
                        // This marks legacy rows without inventing their original business event key.
                        DB::table('notifications')
                            ->where('id', $notification->id)
                            ->update(['event_key' => 'legacy:'.$notification->id]);
                    }
                }, 'id');

            Schema::table('notifications', function (Blueprint $table): void {
                $table->string('event_key', 191)->nullable(false)->change();
                $table->unique(
                    ['user_id', 'type', 'event_key'],
                    'notifications_user_type_event_unique',
                );
            });

            Schema::create('notification_outbox', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('type', 100);
                $table->string('resource_type', 40);
                $table->unsignedBigInteger('resource_id');
                $table->string('event_key', 191);
                $table->jsonb('payload');
                $table->integer('attempt_count')->default(0);
                $table->timestampTz('retry_started_at')->useCurrent();
                $table->timestampTz('available_at')->useCurrent();
                $table->timestampTz('processed_at')->nullable();
                $table->timestampTz('failed_at')->nullable();
                $table->timestampTz('replay_requested_at')->nullable();
                $table->timestampTz('resolved_at')->nullable();
                $table->string('last_error_code', 80)->nullable();
                $table->timestampTz('created_at')->useCurrent();
                $table->unique(
                    ['user_id', 'type', 'event_key'],
                    'notification_outbox_user_type_event_unique',
                );
            });

            if (DB::getDriverName() === 'pgsql') {
                DB::statement(
                    'ALTER TABLE notification_outbox ADD CONSTRAINT notification_outbox_attempt_count_check CHECK (attempt_count >= 0)'
                );
            }

            if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
                DB::statement(
                    'CREATE INDEX notification_outbox_worker_index ON notification_outbox (available_at, id) WHERE processed_at IS NULL AND resolved_at IS NULL AND (failed_at IS NULL OR replay_requested_at > failed_at)'
                );
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function (): void {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement(
                    'ALTER TABLE notification_outbox DROP CONSTRAINT IF EXISTS notification_outbox_attempt_count_check'
                );
            }

            if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
                DB::statement('DROP INDEX IF EXISTS notification_outbox_worker_index');
            }

            Schema::dropIfExists('notification_outbox');

            Schema::table('notifications', function (Blueprint $table): void {
                $table->dropUnique('notifications_user_type_event_unique');
                $table->dropColumn('event_key');
            });
        });
    }
};
