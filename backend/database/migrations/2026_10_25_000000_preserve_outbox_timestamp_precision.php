<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->changePrecision(6);
    }

    public function down(): void
    {
        $this->changePrecision(0);
    }

    private function changePrecision(int $precision): void
    {
        Schema::table('notification_outbox', function (Blueprint $table) use ($precision): void {
            $table->timestampTz('retry_started_at', $precision)
                ->useCurrent()
                ->change();

            $table->timestampTz('available_at', $precision)
                ->useCurrent()
                ->change();

            foreach ([
                'processed_at',
                'failed_at',
                'replay_requested_at',
                'resolved_at',
            ] as $column) {
                $table->timestampTz($column, $precision)
                    ->nullable()
                    ->change();
            }
        });
    }
};
