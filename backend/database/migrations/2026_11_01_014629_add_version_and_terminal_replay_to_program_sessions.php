<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_sessions', function (Blueprint $table): void {
            $table->unsignedInteger('version')->default(1);
            $table->jsonb('terminal_replay')->nullable();
        });

        Schema::table('program_sessions', function (Blueprint $table): void {
            $table->string('status', 20)
                ->default('scheduled')
                ->change();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE program_sessions '
                .'ADD CONSTRAINT program_sessions_version_check '
                .'CHECK (version >= 1)'
            );
        }
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Automatic rollback is disabled because it would remove '
            .'Session version and durable replay history. '
            .'Use a reviewed reverse migration.'
        );
    }
};
