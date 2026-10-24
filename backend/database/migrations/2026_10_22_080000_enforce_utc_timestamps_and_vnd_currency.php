<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private array $timestamps = [
        'failed_jobs' => ['failed_at'],
        'passkeys' => ['last_used_at', 'created_at', 'updated_at'],
        'password_reset_tokens' => ['created_at'],
        'personal_access_tokens' => ['last_used_at', 'expires_at', 'created_at', 'updated_at'],
        'users' => ['email_verified_at', 'created_at', 'updated_at', 'two_factor_confirmed_at'],
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->timestamps as $table => $columns) {
            foreach ($columns as $column) {
                DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} TYPE timestamptz USING {$column} AT TIME ZONE 'UTC'");
            }
        }

        foreach (['programs', 'service_listings', 'fee_records'] as $table) {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_currency_vnd_check CHECK (currency = 'VND')");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['programs', 'service_listings', 'fee_records'] as $table) {
            DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$table}_currency_vnd_check");
        }

        foreach ($this->timestamps as $table => $columns) {
            foreach ($columns as $column) {
                DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} TYPE timestamp without time zone USING {$column} AT TIME ZONE 'UTC'");
            }
        }
    }
};
