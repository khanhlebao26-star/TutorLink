<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('name', 'full_name');
            $table->renameColumn('password', 'password_hash');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('customer')->after('id');
            $table->string('status', 20)->default('active')->after('role');
            $table->date('date_of_birth')->nullable()->after('email_verified_at');
            $table->string('terms_version', 32)->nullable()->after('date_of_birth');
            $table->timestampTz('suspended_at')->nullable()->after('terms_version');
            $table->softDeletesTz();
            $table->index(['role', 'status']);
        });

        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('disk', 32)->default('s3');
            $table->string('object_key', 1024)->unique();
            $table->string('original_name')->nullable();
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size_bytes');
            $table->string('visibility', 20)->default('private');
            $table->char('checksum_sha256', 64)->nullable();
            $table->string('scan_status', 20)->default('pending');
            $table->timestampsTz();
            $table->index(['owner_id', 'visibility']);
        });

        $this->addPostgresChecks();
    }

    public function down(): void
    {
        Schema::dropIfExists('files');

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role', 'status']);
            $table->dropSoftDeletesTz();
            $table->dropColumn([
                'role',
                'status',
                'date_of_birth',
                'terms_version',
                'suspended_at',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('full_name', 'name');
            $table->renameColumn('password_hash', 'password');
        });
    }

    private function addPostgresChecks(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_role_check CHECK (role IN ('customer', 'tutor', 'admin'))");
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_status_check CHECK (status IN ('pending', 'active', 'suspended', 'cancelled'))");
        DB::statement("ALTER TABLE files ADD CONSTRAINT files_visibility_check CHECK (visibility IN ('private', 'program', 'public'))");
        DB::statement("ALTER TABLE files ADD CONSTRAINT files_scan_status_check CHECK (scan_status IN ('pending', 'clean', 'rejected'))");
    }
};
