<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $sources = ['users', 'tutor_profiles', 'service_listings', 'categories', 'subcategories', 'specializations', 'verification_documents', 'files', 'tutor_specializations', 'availability_rules', 'reviews', 'programs', 'program_members', 'listing_moderation_holds'];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('Marketplace cache invalidation requires PostgreSQL.');
        }
        Schema::create('marketplace_cache_versions', function (Blueprint $table): void {
            $table->unsignedSmallInteger('id')->primary();
            $table->uuid('generation');
        });
        DB::statement('INSERT INTO marketplace_cache_versions (id, generation) VALUES (1, gen_random_uuid())');
        DB::unprepared('CREATE OR REPLACE FUNCTION invalidate_marketplace_cache() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN INSERT INTO marketplace_cache_versions (id, generation) VALUES (1, gen_random_uuid()) ON CONFLICT (id) DO UPDATE SET generation = EXCLUDED.generation; RETURN NULL; END $$');
        foreach ($this->sources as $table) {
            DB::unprepared("CREATE TRIGGER marketplace_cache_changed AFTER INSERT OR UPDATE OR DELETE OR TRUNCATE ON {$table} FOR EACH STATEMENT EXECUTE FUNCTION invalidate_marketplace_cache()");
        }
        DB::statement("CREATE INDEX verification_documents_public_expiry_idx ON verification_documents (expires_at DESC) WHERE status = 'approved' AND expires_at IS NOT NULL");
    }

    public function down(): void
    {
        foreach ($this->sources as $table) {
            DB::unprepared("DROP TRIGGER IF EXISTS marketplace_cache_changed ON {$table}");
        }
        DB::statement('DROP FUNCTION IF EXISTS invalidate_marketplace_cache()');
        DB::statement('DROP INDEX IF EXISTS verification_documents_public_expiry_idx');
        Schema::dropIfExists('marketplace_cache_versions');
    }
};
