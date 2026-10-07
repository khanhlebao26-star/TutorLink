<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->default('direct');
            $table->foreignId('context_listing_id')->nullable()->constrained('service_listings')->nullOnDelete();
            $table->string('direct_key', 191)->nullable()->unique();
            $table->string('status', 20)->default('pending');
            $table->timestampTz('activated_at')->nullable();
            $table->timestampTz('ended_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('conversation_members', function (Blueprint $table) {
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->unsignedBigInteger('last_read_message_id')->nullable();
            $table->timestampTz('last_read_at')->nullable();
            $table->timestampTz('joined_at')->useCurrent();
            $table->primary(['conversation_id', 'user_id']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->restrictOnDelete();
            $table->string('message_type', 20)->default('text');
            $table->text('body')->nullable();
            $table->uuid('client_message_id');
            $table->jsonb('metadata')->nullable();
            $table->timestampsTz();
            $table->unique(['sender_id', 'client_message_id']);
            $table->index(['conversation_id', 'created_at'], 'messages_conversation_created_index');
        });

        Schema::table('conversation_members', function (Blueprint $table) {
            $table->foreign('last_read_message_id')
                ->references('id')
                ->on('messages')
                ->nullOnDelete();
        });

        $this->addPostgresChecks();
    }

    public function down(): void
    {
        Schema::table('conversation_members', function (Blueprint $table) {
            $table->dropForeign(['last_read_message_id']);
        });

        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_members');
        Schema::dropIfExists('conversations');
    }

    private function addPostgresChecks(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE conversations ADD CONSTRAINT conversations_type_check CHECK (type IN ('direct', 'program'))");
        DB::statement("ALTER TABLE conversations ADD CONSTRAINT conversations_status_check CHECK (status IN ('pending', 'active', 'cancelled', 'expired'))");
        DB::statement("ALTER TABLE conversations ADD CONSTRAINT conversations_direct_key_check CHECK ((type = 'direct' AND direct_key IS NOT NULL) OR type <> 'direct')");
        DB::statement("ALTER TABLE messages ADD CONSTRAINT messages_type_check CHECK (message_type IN ('text', 'file', 'system'))");
        DB::statement("ALTER TABLE messages ADD CONSTRAINT messages_content_check CHECK (body IS NOT NULL OR message_type = 'file')");
    }
};
