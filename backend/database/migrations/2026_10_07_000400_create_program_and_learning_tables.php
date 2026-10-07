<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_tutor_profile_id')->constrained('tutor_profiles')->restrictOnDelete();
            $table->foreignId('source_listing_id')->nullable()->constrained('service_listings')->nullOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->string('title', 180);
            $table->text('goal')->nullable();
            $table->string('level', 80)->nullable();
            $table->string('delivery_mode', 20);
            $table->unsignedBigInteger('agreed_fee_minor');
            $table->char('currency', 3)->default('VND');
            $table->string('fee_unit', 30);
            $table->text('location_text')->nullable();
            $table->text('meeting_url')->nullable();
            $table->text('schedule_note')->nullable();
            $table->string('timezone', 64)->default('Asia/Ho_Chi_Minh');
            $table->unsignedInteger('planned_session_count')->nullable();
            $table->string('status', 20)->default('pending');
            $table->jsonb('offer_snapshot')->nullable();
            $table->timestampTz('snapshot_locked_at')->nullable();
            $table->timestampTz('activated_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->timestampsTz();
            $table->index(['owner_tutor_profile_id', 'status']);
            $table->unique(['id', 'version']);
        });

        Schema::create('program_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('program_version');
            $table->foreignId('invitee_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('invited_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('pending');
            $table->jsonb('offer_snapshot');
            $table->uuid('idempotency_key');
            $table->timestampTz('expires_at');
            $table->timestampTz('responded_at')->nullable();
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampsTz();
            $table->unique(['invited_by', 'idempotency_key']);
            $table->index(['invitee_user_id', 'status', 'expires_at'], 'program_invitation_inbox_index');
        });

        Schema::create('program_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('member_role', 20);
            $table->string('status', 20)->default('active');
            $table->timestampTz('joined_at')->useCurrent();
            $table->timestampTz('left_at')->nullable();
            $table->timestampsTz();
            $table->unique(['program_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('program_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('program_version');
            $table->unsignedInteger('sequence_number');
            $table->string('title', 180);
            $table->timestampTz('planned_starts_at');
            $table->timestampTz('planned_ends_at');
            $table->string('timezone', 64);
            $table->string('delivery_mode', 20);
            $table->text('location_text')->nullable();
            $table->text('meeting_url')->nullable();
            $table->string('status', 20)->default('planned');
            $table->timestampsTz();
            $table->unique(['program_id', 'program_version', 'sequence_number'], 'program_session_sequence_unique');
            $table->index(['program_id', 'planned_starts_at']);
        });

        Schema::create('lesson_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_session_id')->unique()->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('scheduled');
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('ended_at')->nullable();
            $table->timestampTz('tutor_joined_at')->nullable();
            $table->timestampTz('customer_joined_at')->nullable();
            $table->text('completion_note')->nullable();
            $table->timestampsTz();
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->restrictOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->timestampTz('due_at')->nullable();
            $table->string('status', 20)->default('todo');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['program_id', 'due_at', 'status']);
        });

        Schema::create('task_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->restrictOnDelete();
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

        Schema::create('progress_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->string('metric_name', 100);
            $table->decimal('value', 12, 4);
            $table->text('note')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('recorded_at')->useCurrent();
            $table->timestampsTz();
            $table->index(['program_id', 'customer_id', 'recorded_at']);
        });

        $this->addPostgresConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('progress_records');
        Schema::dropIfExists('task_submissions');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('lesson_sessions');
        Schema::dropIfExists('program_sessions');
        Schema::dropIfExists('program_members');
        Schema::dropIfExists('program_invitations');
        Schema::dropIfExists('programs');
    }

    private function addPostgresConstraints(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE programs ADD CONSTRAINT programs_delivery_mode_check CHECK (delivery_mode IN ('online', 'offline', 'hybrid'))");
        DB::statement("ALTER TABLE programs ADD CONSTRAINT programs_status_check CHECK (status IN ('pending', 'active', 'rejected', 'cancelled', 'expired'))");
        DB::statement('ALTER TABLE programs ADD CONSTRAINT programs_fee_check CHECK (agreed_fee_minor >= 0)');
        DB::statement("ALTER TABLE program_invitations ADD CONSTRAINT program_invitations_status_check CHECK (status IN ('pending', 'active', 'rejected', 'cancelled', 'expired'))");
        DB::statement("CREATE UNIQUE INDEX program_invitations_pending_unique ON program_invitations (program_id, program_version, invitee_user_id) WHERE status = 'pending'");
        DB::statement("ALTER TABLE program_members ADD CONSTRAINT program_members_role_check CHECK (member_role IN ('customer', 'tutor', 'observer'))");
        DB::statement("ALTER TABLE program_members ADD CONSTRAINT program_members_status_check CHECK (status IN ('active', 'cancelled', 'expired'))");
        DB::statement("CREATE UNIQUE INDEX program_members_one_active_customer ON program_members (program_id) WHERE member_role = 'customer' AND status = 'active'");
        DB::statement("ALTER TABLE program_sessions ADD CONSTRAINT program_sessions_delivery_mode_check CHECK (delivery_mode IN ('online', 'offline', 'hybrid'))");
        DB::statement("ALTER TABLE program_sessions ADD CONSTRAINT program_sessions_status_check CHECK (status IN ('planned', 'cancelled'))");
        DB::statement('ALTER TABLE program_sessions ADD CONSTRAINT program_sessions_time_check CHECK (planned_ends_at > planned_starts_at)');
        DB::statement("ALTER TABLE lesson_sessions ADD CONSTRAINT lesson_sessions_status_check CHECK (status IN ('scheduled', 'in_progress', 'completed', 'cancelled', 'no_show'))");
        DB::statement('ALTER TABLE lesson_sessions ADD CONSTRAINT lesson_sessions_time_check CHECK (ended_at IS NULL OR started_at IS NULL OR ended_at > started_at)');
        DB::statement("ALTER TABLE tasks ADD CONSTRAINT tasks_status_check CHECK (status IN ('todo', 'in_progress', 'submitted', 'completed', 'cancelled'))");
        DB::statement("ALTER TABLE task_submissions ADD CONSTRAINT task_submissions_status_check CHECK (status IN ('submitted', 'reviewed', 'revision_requested'))");
    }
};
