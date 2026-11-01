<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_sessions', function (Blueprint $table): void {
            foreach ([
                'planned_starts_at',
                'planned_ends_at',
            ] as $column) {
                $table->timestampTz($column, 6)
                    ->change();
            }

            foreach ([
                'created_at',
                'updated_at',
            ] as $column) {
                $table->timestampTz($column, 6)
                    ->nullable()
                    ->change();
            }
        });
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Automatic rollback is disabled because reducing timestamp '
            .'precision would lose microseconds. '
            .'Use a reviewed reverse migration.'
        );
    }
};
