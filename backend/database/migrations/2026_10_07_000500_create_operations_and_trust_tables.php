<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->restrictOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->string('title', 180);
            $table->text('body');
            $table->timestampTz('published_at')->nullable();
            $table->timestampsTz();
            $table->index(['program_id', 'published_at']);
        });

        Schema::create('fee_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3)->default('VND');
            $table->string('declared_status', 20)->default('declared');
            $table->text('disclaimer')->nullable();
            $table->timestampsTz();
            $table->unique(['program_id', 'period_start']);
        });

        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('tutor_profile_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->timestampsTz();
            $table->unique(['program_id', 'customer_id']);
            $table->index(['tutor_profile_id', 'created_at']);
        });

        Schema::create('file_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained('files')->restrictOnDelete();
            $table->string('resource_type', 40);
            $table->unsignedBigInteger('resource_id');
            $table->string('purpose', 50)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['file_id', 'resource_type', 'resource_id']);
            $table->index(['resource_type', 'resource_id']);
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 100);
            $table->jsonb('payload');
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['user_id', 'read_at', 'created_at']);
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->restrictOnDelete();
            $table->string('resource_type', 40);
            $table->unsignedBigInteger('resource_id');
            $table->string('reason', 100);
            $table->text('details')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('handled_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestampsTz();
            $table->index(['resource_type', 'resource_id']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100);
            $table->string('resource_type', 40);
            $table->unsignedBigInteger('resource_id');
            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();
            $table->text('reason')->nullable();
            $table->uuid('request_id')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['actor_id', 'created_at']);
            $table->index(
                ['resource_type', 'resource_id', 'created_at'],
                'audit_logs_resource_created_index'
            );
            $table->index('request_id');
        });

        $this->addPostgresChecks();
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('reports');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('file_links');
        Schema::dropIfExists('reviews');
        Schema::dropIfExists('fee_records');
        Schema::dropIfExists('announcements');
    }

    private function addPostgresChecks(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE fee_records ADD CONSTRAINT fee_records_period_check CHECK (period_end >= period_start)');
        DB::statement('ALTER TABLE fee_records ADD CONSTRAINT fee_records_amount_check CHECK (amount_minor >= 0)');
        DB::statement("ALTER TABLE fee_records ADD CONSTRAINT fee_records_status_check CHECK (declared_status IN ('declared', 'confirmed', 'cancelled'))");
        DB::statement('ALTER TABLE reviews ADD CONSTRAINT reviews_rating_check CHECK (rating BETWEEN 1 AND 5)');
        DB::statement("ALTER TABLE file_links ADD CONSTRAINT file_links_resource_type_check CHECK (resource_type IN ('message', 'task', 'task_submission', 'announcement', 'verification_document'))");
        DB::statement("ALTER TABLE reports ADD CONSTRAINT reports_status_check CHECK (status IN ('pending', 'reviewing', 'resolved', 'dismissed'))");
    }
};
