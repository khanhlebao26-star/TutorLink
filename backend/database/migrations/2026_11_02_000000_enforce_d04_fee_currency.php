<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('BE-D04 Fee currency constraint requires PostgreSQL.');
        }

        $invalidRows = DB::table('fee_records')->whereNull('currency')->orWhere('currency', '<>', 'VND')->count();

        if ($invalidRows > 0) {
            throw new RuntimeException("BE-D04 Fee currency migration refused: {$invalidRows} rows are not VND.");
        }

        DB::statement("ALTER TABLE fee_records ADD CONSTRAINT fee_records_d04_currency_check CHECK (currency = 'VND')");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE fee_records DROP CONSTRAINT IF EXISTS fee_records_d04_currency_check');
    }
};
