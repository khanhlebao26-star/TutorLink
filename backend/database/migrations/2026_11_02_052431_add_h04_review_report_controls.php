<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('reports')->whereNull('status')->orWhereNotIn('status', ['pending', 'reviewing', 'open', 'resolved', 'dismissed'])->exists()) {
            throw new RuntimeException('Unmapped Report status requires data review.');
        }
        if (DB::table('reports')->whereIn('status', ['pending', 'reviewing', 'open'])
            ->select('reporter_id', 'resource_type', 'resource_id')->groupBy('reporter_id', 'resource_type', 'resource_id')->havingRaw('count(*) > 1')->exists()) {
            throw new RuntimeException('Duplicate open Reports require data review.');
        }
        Schema::table('reviews', function (Blueprint $table): void {
            $table->string('visibility', 20)->default('visible');
            $table->integer('version')->default(1);
            $table->index(['tutor_profile_id', 'visibility', 'created_at', 'id']);
        });
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE reports DROP CONSTRAINT IF EXISTS reports_status_check');
        }
        DB::table('reports')->whereIn('status', ['pending', 'reviewing'])->update(['status' => 'open']);
        Schema::table('reports', function (Blueprint $table): void {
            $table->string('status', 20)->default('open')->change();
            $table->index(['reporter_id', 'created_at']);
        });
        DB::statement("CREATE UNIQUE INDEX reports_one_open_per_resource ON reports (reporter_id,resource_type,resource_id) WHERE status = 'open'");
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE reports ADD CONSTRAINT reports_status_check CHECK (status IN ('open','resolved','dismissed'))");
            DB::statement("ALTER TABLE reviews ADD CONSTRAINT reviews_visibility_version_check CHECK (visibility IN ('visible','hidden') AND version >= 1)");
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Review a reverse migration to preserve Review visibility and Report history.');
    }
};
