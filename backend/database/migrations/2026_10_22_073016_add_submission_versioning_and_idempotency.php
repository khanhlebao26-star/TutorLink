<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            Schema::table('tasks', function (Blueprint $table): void {
                $table->integer('version')->default(1);
                $table->foreignId('current_submission_id')->nullable();
            });

            Schema::table('task_submissions', function (Blueprint $table): void {
                $table->integer('submission_version')->nullable();
                $table->uuid('idempotency_key')->nullable();
                $table->char('request_hash', 64)->nullable();
            });

            // Legacy rows each represented the sole current submission. Synthetic keys
            // preserve them without claiming an old client request can be replayed.
            DB::table('task_submissions')->orderBy('id')->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $task = DB::table('tasks')->where('id', $row->task_id)->first();
                    if ($task === null || ($task->assigned_to !== null && (int) $task->assigned_to !== (int) $row->customer_id)
                        || $task->current_submission_id !== null) {
                        throw new RuntimeException('Legacy submission does not match a single Task assignee. Review the data before migrating.');
                    }
                    DB::table('task_submissions')->where('id', $row->id)->update([
                        'submission_version' => 1,
                        'idempotency_key' => (string) Str::uuid(),
                        'request_hash' => hash('sha256', 'legacy:'.$row->id),
                    ]);
                    DB::table('tasks')->where('id', $row->task_id)->update([
                        'assigned_to' => $row->customer_id,
                        'current_submission_id' => $row->id,
                    ]);
                }
            });

            Schema::table('task_submissions', function (Blueprint $table): void {
                $table->integer('submission_version')->nullable(false)->change();
                $table->uuid('idempotency_key')->nullable(false)->change();
                $table->char('request_hash', 64)->nullable(false)->change();
                $table->dropUnique('task_submissions_task_id_customer_id_unique');
                $table->unique(
                    ['task_id', 'customer_id', 'submission_version'],
                    'task_submissions_task_customer_version_unique',
                );
                $table->unique(
                    ['task_id', 'customer_id', 'idempotency_key'],
                    'task_submissions_task_customer_idempotency_unique',
                );
                $table->unique(
                    ['task_id', 'id'],
                    'task_submissions_task_id_unique',
                );
                $table->unique(
                    ['task_id', 'customer_id', 'id'],
                    'task_submissions_task_customer_id_unique',
                );
                $table->index(
                    ['task_id', 'submission_version'],
                    'task_submissions_task_submission_version_desc_index',
                );
            });

            Schema::table('tasks', function (Blueprint $table): void {
                $table->foreign(
                    ['id', 'current_submission_id'],
                    'tasks_current_submission_task_foreign',
                )->references(['task_id', 'id'])
                    ->on('task_submissions')
                    ->restrictOnDelete();
                $table->foreign(
                    ['id', 'assigned_to', 'current_submission_id'],
                    'tasks_current_submission_task_customer_foreign',
                )->references(['task_id', 'customer_id', 'id'])
                    ->on('task_submissions')
                    ->restrictOnDelete();
            });

            if (DB::getDriverName() === 'pgsql') {
                DB::statement(
                    'ALTER TABLE tasks ADD CONSTRAINT tasks_current_submission_assignee_check CHECK (current_submission_id IS NULL OR assigned_to IS NOT NULL)'
                );
                DB::statement(
                    'ALTER TABLE tasks ADD CONSTRAINT tasks_version_check CHECK (version >= 1)'
                );
                DB::statement(
                    'ALTER TABLE task_submissions ADD CONSTRAINT task_submissions_submission_version_check CHECK (submission_version >= 1)'
                );
                DB::statement(
                    "ALTER TABLE task_submissions ADD CONSTRAINT task_submissions_request_hash_check CHECK (request_hash ~ '^[0-9a-f]{64}$')"
                );
                DB::statement(
                    'DROP INDEX task_submissions_task_submission_version_desc_index'
                );
                DB::statement(
                    'CREATE INDEX task_submissions_task_submission_version_desc_index ON task_submissions (task_id, submission_version DESC)'
                );
            }
        });
    }

    public function down(): void
    {
        if (DB::table('task_submissions')->exists()) {
            throw new RuntimeException(
                'Refusing to remove submission history and idempotency metadata while submissions exist.'
            );
        }

        DB::transaction(function (): void {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement(
                    'ALTER TABLE tasks DROP CONSTRAINT IF EXISTS tasks_current_submission_assignee_check'
                );
                DB::statement(
                    'ALTER TABLE tasks DROP CONSTRAINT IF EXISTS tasks_version_check'
                );
                DB::statement(
                    'ALTER TABLE task_submissions DROP CONSTRAINT IF EXISTS task_submissions_submission_version_check'
                );
                DB::statement(
                    'ALTER TABLE task_submissions DROP CONSTRAINT IF EXISTS task_submissions_request_hash_check'
                );
            }

            Schema::table('tasks', function (Blueprint $table): void {
                $table->dropForeign('tasks_current_submission_task_foreign');
                $table->dropForeign('tasks_current_submission_task_customer_foreign');
                $table->dropColumn(['current_submission_id', 'version']);
            });

            Schema::table('task_submissions', function (Blueprint $table): void {
                $table->dropUnique('task_submissions_task_customer_version_unique');
                $table->dropUnique('task_submissions_task_customer_idempotency_unique');
                $table->dropUnique('task_submissions_task_customer_id_unique');
                $table->dropUnique('task_submissions_task_id_unique');
                $table->dropIndex('task_submissions_task_submission_version_desc_index');
                $table->dropColumn([
                    'submission_version',
                    'idempotency_key',
                    'request_hash',
                ]);
                $table->unique(['task_id', 'customer_id']);
            });
        });
    }
};
