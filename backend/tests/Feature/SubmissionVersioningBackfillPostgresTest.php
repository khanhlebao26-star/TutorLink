<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

it('reruns PostgreSQL backfill for representative legacy rows and rolls back inconsistent data', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('Legacy backfill transactional DDL verification requires PostgreSQL.');
    }

    $originalSearchPath = DB::selectOne('SHOW search_path')->search_path;
    $withLegacySchema = function (Closure $scenario) use ($originalSearchPath): void {
        $schema = 'issue74_backfill_'.Str::lower(Str::random(10));
        DB::statement('CREATE SCHEMA "'.$schema.'"');
        DB::selectOne("SELECT set_config('search_path', ?, false)", [$schema]);

        try {
            Schema::create('users', fn (Blueprint $table) => $table->id());
            Schema::create('programs', fn (Blueprint $table) => $table->id());
            Schema::create('tasks', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('program_id')->constrained('programs')->restrictOnDelete();
                $table->string('title', 180);
                $table->text('description')->nullable();
                $table->timestampTz('due_at')->nullable();
                $table->string('status', 20)->default('todo');
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->timestampsTz();
            });
            Schema::create('task_submissions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('task_id')->constrained('tasks')->restrictOnDelete();
                $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
                $table->text('content')->nullable();
                $table->string('status', 20)->default('submitted');
                $table->timestampTz('submitted_at')->useCurrent();
                $table->text('feedback')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestampTz('reviewed_at')->nullable();
                $table->timestampsTz();
                $table->unique(['task_id', 'customer_id']);
            });
            DB::statement("ALTER TABLE task_submissions ADD CONSTRAINT task_submissions_status_check CHECK (status IN ('submitted', 'accepted', 'revision_requested'))");
            DB::table('users')->insert([['id' => 1], ['id' => 2]]);
            DB::table('programs')->insert(['id' => 1]);

            $scenario();
        } finally {
            DB::selectOne("SELECT set_config('search_path', ?, false)", [$originalSearchPath]);
            DB::statement('DROP SCHEMA IF EXISTS "'.$schema.'" CASCADE');
        }
    };

    $insertLegacyRows = function (?int $assignedTo, int $submissionCustomer, array $submissionAttributes = []): array {
        $task = DB::table('tasks')->insertGetId([
            'program_id' => 1,
            'title' => 'Legacy task',
            'status' => 'submitted',
            'assigned_to' => $assignedTo,
        ]);
        $submission = DB::table('task_submissions')->insertGetId([
            'task_id' => $task,
            'customer_id' => $submissionCustomer,
            'content' => 'Legacy submission',
            ...$submissionAttributes,
        ]);

        return [$task, $submission];
    };

    $withLegacySchema(function () use ($insertLegacyRows): void {
        [$missingAssigneeTask, $missingAssigneeSubmission] = $insertLegacyRows(null, 1);
        [$preservedAssigneeTask, $preservedAssigneeSubmission] = $insertLegacyRows(2, 2, [
            'status' => 'accepted',
            'feedback' => 'Feedback from the legacy row',
            'reviewed_by' => 1,
            'reviewed_at' => now('UTC'),
        ]);

        $migration = require database_path('migrations/2026_10_22_073016_add_submission_versioning_and_idempotency.php');
        $migration->up();

        foreach ([
            [$missingAssigneeTask, $missingAssigneeSubmission, 1],
            [$preservedAssigneeTask, $preservedAssigneeSubmission, 2],
        ] as [$taskId, $submissionId, $customerId]) {
            $task = DB::table('tasks')->find($taskId);
            $submission = DB::table('task_submissions')->find($submissionId);
            expect((int) $task->assigned_to)->toBe($customerId)
                ->and((int) $task->current_submission_id)->toBe($submissionId)
                ->and((int) $submission->submission_version)->toBe(1)
                ->and(Str::isUuid($submission->idempotency_key))->toBeTrue()
                ->and($submission->request_hash)->toBe(hash('sha256', 'legacy:'.$submissionId));
        }
        $acceptedSubmission = DB::table('task_submissions')->find($preservedAssigneeSubmission);
        expect($acceptedSubmission->status)->toBe('accepted')
            ->and($acceptedSubmission->feedback)->toBe('Feedback from the legacy row')
            ->and((int) $acceptedSubmission->reviewed_by)->toBe(1)
            ->and($acceptedSubmission->reviewed_at)->not->toBeNull();
    });

    $withLegacySchema(function () use ($insertLegacyRows): void {
        [$taskId, $submissionId] = $insertLegacyRows(1, 2);
        $migration = require database_path('migrations/2026_10_22_073016_add_submission_versioning_and_idempotency.php');

        expect(fn () => $migration->up())
            ->toThrow(RuntimeException::class, 'Legacy submission does not match');

        expect(Schema::hasColumn('tasks', 'current_submission_id'))->toBeFalse()
            ->and(Schema::hasColumn('task_submissions', 'submission_version'))->toBeFalse()
            ->and((int) DB::table('tasks')->find($taskId)->assigned_to)->toBe(1)
            ->and((int) DB::table('task_submissions')->find($submissionId)->customer_id)->toBe(2);
    });
});
