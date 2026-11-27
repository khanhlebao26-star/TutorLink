<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $sources = ['users', 'tutor_profiles', 'service_listings', 'categories', 'subcategories', 'specializations', 'verification_documents', 'files', 'tutor_specializations', 'availability_rules', 'reviews', 'programs', 'program_members', 'listing_moderation_holds'];

    public function up(): void
    {
        DB::statement('INSERT INTO marketplace_cache_versions (id, generation) VALUES (2, gen_random_uuid()) ON CONFLICT DO NOTHING');
        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION invalidate_scoped_marketplace_cache() RETURNS trigger LANGUAGE plpgsql AS $$
DECLARE changed boolean; projection text; row_filter text := ''; 
BEGIN
    projection := 'to_jsonb(r) - ''updated_at''';
    IF TG_TABLE_NAME = 'users' THEN
        projection := 'jsonb_build_array(r.id,r.full_name,r.role,r.status,r.email_verified_at,r.suspended_at,r.deleted_at)';
        row_filter := ' WHERE EXISTS (SELECT 1 FROM tutor_profiles p WHERE p.user_id = r.id) OR EXISTS (SELECT 1 FROM reviews v WHERE v.customer_id = r.id)';
    ELSIF TG_TABLE_NAME = 'files' THEN
        row_filter := ' WHERE EXISTS (SELECT 1 FROM tutor_profiles p WHERE p.avatar_file_id = r.id) OR EXISTS (SELECT 1 FROM verification_documents d WHERE d.file_id = r.id)';
    ELSIF TG_TABLE_NAME = 'programs' THEN
        projection := 'jsonb_build_array(r.id,r.owner_tutor_profile_id,r.status)';
    END IF;
    IF TG_OP = 'UPDATE' THEN
        EXECUTE 'SELECT EXISTS ((SELECT '||projection||' FROM new_rows r'||row_filter||' EXCEPT SELECT '||projection||' FROM old_rows r'||row_filter||') UNION ALL (SELECT '||projection||' FROM old_rows r'||row_filter||' EXCEPT SELECT '||projection||' FROM new_rows r'||row_filter||'))' INTO changed;
    ELSIF TG_OP = 'INSERT' THEN
        EXECUTE 'SELECT EXISTS (SELECT 1 FROM new_rows r'||row_filter||')' INTO changed;
    ELSIF TG_OP = 'DELETE' THEN
        EXECUTE 'SELECT EXISTS (SELECT 1 FROM old_rows r'||row_filter||')' INTO changed;
    ELSE
        changed := true;
    END IF;
    IF changed THEN
        INSERT INTO marketplace_cache_versions (id, generation) VALUES (1, gen_random_uuid()) ON CONFLICT (id) DO UPDATE SET generation = EXCLUDED.generation;
        IF TG_TABLE_NAME IN ('categories', 'subcategories', 'specializations') THEN
            INSERT INTO marketplace_cache_versions (id, generation) VALUES (2, gen_random_uuid()) ON CONFLICT (id) DO UPDATE SET generation = EXCLUDED.generation;
        END IF;
    END IF;
    RETURN NULL;
END $$;
SQL);
        foreach ($this->sources as $table) {
            DB::statement("DROP TRIGGER marketplace_cache_changed ON {$table}");
            DB::unprepared("CREATE TRIGGER marketplace_cache_insert AFTER INSERT ON {$table} REFERENCING NEW TABLE AS new_rows FOR EACH STATEMENT EXECUTE FUNCTION invalidate_scoped_marketplace_cache()");
            DB::unprepared("CREATE TRIGGER marketplace_cache_update AFTER UPDATE ON {$table} REFERENCING OLD TABLE AS old_rows NEW TABLE AS new_rows FOR EACH STATEMENT EXECUTE FUNCTION invalidate_scoped_marketplace_cache()");
            DB::unprepared("CREATE TRIGGER marketplace_cache_delete AFTER DELETE ON {$table} REFERENCING OLD TABLE AS old_rows FOR EACH STATEMENT EXECUTE FUNCTION invalidate_scoped_marketplace_cache()");
            DB::unprepared("CREATE TRIGGER marketplace_cache_truncate AFTER TRUNCATE ON {$table} FOR EACH STATEMENT EXECUTE FUNCTION invalidate_scoped_marketplace_cache()");
        }
    }

    public function down(): void
    {
        foreach ($this->sources as $table) {
            foreach (['insert', 'update', 'delete', 'truncate'] as $event) {
                DB::statement("DROP TRIGGER IF EXISTS marketplace_cache_{$event} ON {$table}");
            }
            DB::unprepared("CREATE TRIGGER marketplace_cache_changed AFTER INSERT OR UPDATE OR DELETE OR TRUNCATE ON {$table} FOR EACH STATEMENT EXECUTE FUNCTION invalidate_marketplace_cache()");
        }
        DB::statement('DROP FUNCTION IF EXISTS invalidate_scoped_marketplace_cache()');
        DB::table('marketplace_cache_versions')->where('id', 2)->delete();
    }
};
