<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tutor_profiles', function (Blueprint $table): void {
            $table->string('status_before_suspension', 20)->nullable();
            $table->jsonb('qualification_claims')->default('[]');
        });
        // The legacy endpoint only suspended approved profiles.
        DB::table('tutor_profiles')->where('status', 'suspended')->update(['status_before_suspension' => 'approved']);
        Schema::table('verification_documents', function (Blueprint $table): void {
            $table->text('review_reason')->nullable();
        });
        Schema::create('tutor_profile_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tutor_profile_id')->constrained()->cascadeOnDelete();
            $table->integer('revision_no');
            $table->string('status', 20)->default('draft');
            $table->jsonb('payload');
            $table->foreignId('avatar_file_id')->nullable()->constrained('files')->restrictOnDelete();
            $table->integer('version')->default(1);
            $table->timestampTz('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('review_reason')->nullable();
            $table->timestampsTz();
            $table->unique(['tutor_profile_id', 'revision_no']);
        });
        DB::statement("CREATE UNIQUE INDEX tutor_profile_open_revision_unique ON tutor_profile_revisions (tutor_profile_id) WHERE status IN ('draft', 'submitted', 'changes_requested')");
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE tutor_profiles ADD CONSTRAINT tutor_profile_previous_status_check CHECK (status_before_suspension IS NULL OR status_before_suspension IN ('draft','submitted','changes_requested','approved','rejected'))");
            DB::statement("ALTER TABLE tutor_profile_revisions ADD CONSTRAINT tutor_profile_revision_state_check CHECK (status IN ('draft','submitted','changes_requested','approved','rejected') AND version > 0 AND revision_no > 0)");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tutor_profile_revisions');
        Schema::table('verification_documents', function (Blueprint $table): void {
            $table->dropColumn('review_reason');
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE tutor_profiles DROP CONSTRAINT IF EXISTS tutor_profile_previous_status_check');
        }
        Schema::table('tutor_profiles', function (Blueprint $table): void {
            $table->dropColumn(['status_before_suspension', 'qualification_claims']);
        });
    }
};
