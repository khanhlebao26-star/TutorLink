<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table): void {
            $table->string('scan_engine', 20)->nullable();
            $table->string('scan_version', 255)->nullable();
            $table->char('scanned_sha256', 64)->nullable();
            $table->timestampTz('scanned_at', 6)->nullable();
        });

        // Existing clean rows retain null evidence.
        // Do not fabricate antivirus results for legacy files.
    }

    public function down(): void
    {
        if (DB::table('files')->whereNotNull('scanned_at')->exists()) {
            throw new RuntimeException(
                'Preserve scan evidence and plan the rollback before dropping these columns.'
            );
        }

        Schema::table('files', function (Blueprint $table): void {
            $table->dropColumn([
                'scan_engine',
                'scan_version',
                'scanned_sha256',
                'scanned_at',
            ]);
        });
    }
};
