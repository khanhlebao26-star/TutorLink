<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasLegacyConversations = DB::table('conversations')
            ->where(function ($query): void {
                $query
                    ->where('type', '!=', 'direct')
                    ->orWhereNotIn('status', ['active', 'cancelled']);
            })
            ->exists();

        if ($hasLegacyConversations) {
            throw new RuntimeException(
                'Legacy conversations require an explicit mapping before migration.'
            );
        }

        if (DB::table('messages')->exists()) {
            throw new RuntimeException(
                'Existing messages require a canonical payload backfill plan before migration.'
            );
        }

        Schema::table('messages', function (Blueprint $table): void {
            $table->char('payload_hash', 64)->nullable();

            $table->dropIndex('messages_conversation_created_index');

            $table->index(
                ['conversation_id', 'created_at', 'id'],
                'messages_conversation_created_id_index',
            );
        });

        Schema::table('conversations', function (Blueprint $table): void {
            $table->string('status', 20)->default('active')->change();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE conversations '
                .'DROP CONSTRAINT conversations_status_check'
            );

            DB::statement(
                'ALTER TABLE conversations '
                .'ADD CONSTRAINT conversations_status_check '
                ."CHECK (status IN ('active', 'cancelled'))"
            );

            DB::statement(
                'ALTER TABLE conversations '
                .'DROP CONSTRAINT conversations_type_check'
            );

            DB::statement(
                'ALTER TABLE conversations '
                .'ADD CONSTRAINT conversations_type_check '
                ."CHECK (type = 'direct')"
            );
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE conversations '
                .'DROP CONSTRAINT conversations_status_check'
            );

            DB::statement(
                'ALTER TABLE conversations '
                .'ADD CONSTRAINT conversations_status_check '
                ."CHECK (status IN ('pending', 'active', 'cancelled', 'expired'))"
            );

            DB::statement(
                'ALTER TABLE conversations '
                .'DROP CONSTRAINT conversations_type_check'
            );

            DB::statement(
                'ALTER TABLE conversations '
                .'ADD CONSTRAINT conversations_type_check '
                ."CHECK (type IN ('direct', 'program'))"
            );
        }

        Schema::table('conversations', function (Blueprint $table): void {
            $table->string('status', 20)->default('pending')->change();
        });

        Schema::table('messages', function (Blueprint $table): void {
            $table->dropIndex('messages_conversation_created_id_index');
            $table->dropColumn('payload_hash');

            $table->index(
                ['conversation_id', 'created_at'],
                'messages_conversation_created_index',
            );
        });
    }
};
