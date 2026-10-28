<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE messages ALTER COLUMN created_at TYPE timestamptz(6), ALTER COLUMN updated_at TYPE timestamptz(6)');
        }

        Schema::table('messages', function (Blueprint $table): void {
            $table->index(['sender_id', 'created_at', 'id'], 'messages_sender_created_id_index');
        });
    }

    public function down(): void
    {
        if (
            DB::getDriverName() === 'pgsql' && DB::table('messages')->whereRaw(
                "created_at <> date_trunc('second', created_at) OR updated_at <> date_trunc('second', updated_at)",
            )->exists()
        ) {
            throw new RuntimeException('Fractional message timestamps require a preservation plan before rollback.');
        }

        Schema::table('messages', function (Blueprint $table): void {
            $table->dropIndex('messages_sender_created_id_index');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE messages ALTER COLUMN created_at TYPE timestamptz(0), ALTER COLUMN updated_at TYPE timestamptz(0)');
        }
    }
};
