<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table): void {
            $table->string('purpose', 50)->nullable()->after('scan_status');
            $table->index(['owner_id', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table): void {
            $table->dropIndex(['owner_id', 'purpose']);
            $table->dropColumn('purpose');
        });
    }
};
