<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_profiles', function (Blueprint $table): void {
            $table->string('subjects_of_interest', 500)->nullable();
            $table->string('learning_level', 120)->nullable();
            $table->text('learning_goals')->nullable();
            $table->string('preferred_schedule', 500)->nullable();
            $table->string('preferred_mode', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customer_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'subjects_of_interest', 'learning_level', 'learning_goals',
                'preferred_schedule', 'preferred_mode',
            ]);
        });
    }
};
