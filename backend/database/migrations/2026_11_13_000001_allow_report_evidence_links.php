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

        DB::statement('ALTER TABLE file_links DROP CONSTRAINT file_links_resource_type_check');
        DB::statement("ALTER TABLE file_links ADD CONSTRAINT file_links_resource_type_check CHECK (resource_type IN ('message', 'task', 'task_submission', 'announcement', 'verification_document', 'report'))");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        if (DB::table('file_links')->where('resource_type', 'report')->exists()) {
            throw new RuntimeException('Report evidence links exist; preserve report evidence before rolling back.');
        }
        DB::statement('ALTER TABLE file_links DROP CONSTRAINT file_links_resource_type_check');
        DB::statement("ALTER TABLE file_links ADD CONSTRAINT file_links_resource_type_check CHECK (resource_type IN ('message', 'task', 'task_submission', 'announcement', 'verification_document'))");
    }
};
