<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        DB::statement(<<<'SQL'
            CREATE INDEX CONCURRENTLY service_listings_active_text_search_gin
                ON service_listings USING gin (title gin_trgm_ops, description gin_trgm_ops)
                WHERE status = 'active' AND currency = 'VND'
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX CONCURRENTLY IF EXISTS service_listings_active_text_search_gin');
    }
};
