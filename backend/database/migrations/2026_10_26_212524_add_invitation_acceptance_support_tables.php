<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE verification_documents DROP CONSTRAINT IF EXISTS verification_documents_status_check'
            );
        }

        DB::table('verification_documents')
            ->where('status', 'active')
            ->update(['status' => 'approved']);

        Schema::table('verification_documents', function (Blueprint $table): void {
            $table->timestampTz('expires_at')->nullable();
        });

        Schema::create('listing_moderation_holds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('listing_id')->constrained('service_listings')->restrictOnDelete();
            $table->foreignId('hidden_by_admin_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('hidden_at');
            $table->text('hide_reason');
            $table->string('hide_reason_code', 40);
            $table->boolean('reactivate_on_restore')->default(false);
            $table->foreignId('restored_by_admin_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestampTz('restored_at')->nullable();
            $table->text('restore_reason')->nullable();
            $table->string('restore_reason_code', 40)->nullable();
            $table->index(['listing_id', 'restored_at']);
        });

        Schema::create('idempotency_receipts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->uuid('idempotency_key');
            $table->string('method', 10);
            $table->string('canonical_path', 512);
            $table->char('request_hash', 64);
            $table->unsignedSmallInteger('response_status');
            $table->jsonb('response_snapshot');
            $table->timestampTz('created_at');
            $table->timestampTz('expires_at')->nullable();
            $table->unique(['actor_id', 'idempotency_key']);
            $table->index('expires_at');
        });

        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement(
                'CREATE UNIQUE INDEX listing_moderation_holds_one_active_per_listing '
                .'ON listing_moderation_holds (listing_id) WHERE restored_at IS NULL'
            );
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE verification_documents ADD CONSTRAINT verification_documents_status_check '
                ."CHECK (status IN ('pending', 'approved', 'rejected', 'expired'))"
            );
            DB::statement(
                'ALTER TABLE listing_moderation_holds ADD CONSTRAINT listing_moderation_holds_restore_state_check '
                .'CHECK ((restored_at IS NULL AND restored_by_admin_id IS NULL AND restore_reason IS NULL AND restore_reason_code IS NULL) '
                .'OR (restored_at IS NOT NULL AND restored_by_admin_id IS NOT NULL AND restore_reason IS NOT NULL AND restore_reason_code IS NOT NULL))'
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE verification_documents DROP CONSTRAINT IF EXISTS verification_documents_status_check'
            );
            DB::statement(
                'ALTER TABLE listing_moderation_holds DROP CONSTRAINT IF EXISTS listing_moderation_holds_restore_state_check'
            );
        }

        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS listing_moderation_holds_one_active_per_listing');
        }

        Schema::dropIfExists('idempotency_receipts');
        Schema::dropIfExists('listing_moderation_holds');

        DB::table('verification_documents')
            ->where('status', 'approved')
            ->update(['status' => 'active']);

        Schema::table('verification_documents', function (Blueprint $table): void {
            $table->dropColumn('expires_at');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement(
                'ALTER TABLE verification_documents ADD CONSTRAINT verification_documents_status_check '
                ."CHECK (status IN ('pending', 'active', 'rejected', 'expired'))"
            );
        }
    }
};
