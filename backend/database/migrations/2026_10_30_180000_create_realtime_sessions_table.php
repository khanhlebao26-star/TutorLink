<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('realtime_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->char('session_hash', 64);
            $table->char('channel_token', 64);

            $table->timestampTz('expires_at', 6);
            $table->timestampTz('revoked_at', 6)->nullable();
            $table->timestampsTz(6);

            $table->unique(['user_id', 'session_hash']);
            $table->unique('channel_token');

            $table->index(
                ['user_id', 'revoked_at', 'expires_at'],
                'realtime_sessions_active_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('realtime_sessions');
    }
};
