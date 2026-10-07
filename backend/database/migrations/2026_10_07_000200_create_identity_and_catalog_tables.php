<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->foreignId('avatar_file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->string('city', 120)->nullable();
            $table->string('district', 120)->nullable();
            $table->text('bio')->nullable();
            $table->timestampsTz();
        });

        Schema::create('tutor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->string('headline', 160);
            $table->text('bio')->nullable();
            $table->unsignedSmallInteger('experience_years')->default(0);
            $table->string('approval_status', 20)->default('pending');
            $table->timestampTz('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampsTz();
            $table->index(['approval_status', 'submitted_at']);
        });

        Schema::create('verification_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tutor_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('file_id')->constrained('files')->restrictOnDelete();
            $table->string('document_type', 50);
            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestampsTz();
            $table->unique(['tutor_profile_id', 'file_id']);
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->string('status', 20)->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampsTz();
            $table->index(['status', 'sort_order']);
        });

        Schema::create('subcategories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->string('slug', 140);
            $table->string('status', 20)->default('active');
            $table->timestampsTz();
            $table->unique(['category_id', 'slug']);
        });

        Schema::create('specializations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subcategory_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->string('slug', 140);
            $table->string('status', 20)->default('active');
            $table->timestampsTz();
            $table->unique(['subcategory_id', 'slug']);
        });

        Schema::create('tutor_specializations', function (Blueprint $table) {
            $table->foreignId('tutor_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('specialization_id')->constrained()->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->primary(['tutor_profile_id', 'specialization_id']);
        });

        Schema::create('service_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tutor_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('specialization_id')->constrained()->restrictOnDelete();
            $table->string('slug', 180)->unique();
            $table->string('title', 180);
            $table->text('description');
            $table->string('delivery_mode', 20);
            $table->string('city', 120)->nullable();
            $table->string('district', 120)->nullable();
            $table->unsignedBigInteger('price_from_minor');
            $table->char('currency', 3)->default('VND');
            $table->string('price_unit', 30);
            $table->string('status', 20)->default('draft');
            $table->timestampsTz();
            $table->index(
                ['status', 'specialization_id', 'delivery_mode', 'city', 'price_from_minor'],
                'service_listings_marketplace_index'
            );
        });

        Schema::create('availability_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tutor_profile_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday');
            $table->time('start_time');
            $table->time('end_time');
            $table->string('timezone', 64)->default('Asia/Ho_Chi_Minh');
            $table->timestampsTz();
            $table->index(['tutor_profile_id', 'weekday']);
        });

        Schema::create('favorites', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tutor_profile_id')->constrained()->cascadeOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->primary(['user_id', 'tutor_profile_id']);
        });

        $this->addPostgresChecks();
    }

    public function down(): void
    {
        Schema::dropIfExists('favorites');
        Schema::dropIfExists('availability_rules');
        Schema::dropIfExists('service_listings');
        Schema::dropIfExists('tutor_specializations');
        Schema::dropIfExists('specializations');
        Schema::dropIfExists('subcategories');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('verification_documents');
        Schema::dropIfExists('tutor_profiles');
        Schema::dropIfExists('customer_profiles');
    }

    private function addPostgresChecks(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE tutor_profiles ADD CONSTRAINT tutor_profiles_approval_status_check CHECK (approval_status IN ('pending', 'active', 'rejected', 'cancelled', 'expired'))");
        DB::statement("ALTER TABLE verification_documents ADD CONSTRAINT verification_documents_status_check CHECK (status IN ('pending', 'active', 'rejected', 'expired'))");
        DB::statement("ALTER TABLE categories ADD CONSTRAINT categories_status_check CHECK (status IN ('active', 'inactive'))");
        DB::statement("ALTER TABLE subcategories ADD CONSTRAINT subcategories_status_check CHECK (status IN ('active', 'inactive'))");
        DB::statement("ALTER TABLE specializations ADD CONSTRAINT specializations_status_check CHECK (status IN ('active', 'inactive'))");
        DB::statement("ALTER TABLE service_listings ADD CONSTRAINT service_listings_delivery_mode_check CHECK (delivery_mode IN ('online', 'offline', 'hybrid'))");
        DB::statement("ALTER TABLE service_listings ADD CONSTRAINT service_listings_status_check CHECK (status IN ('draft', 'active', 'inactive', 'cancelled'))");
        DB::statement('ALTER TABLE service_listings ADD CONSTRAINT service_listings_price_check CHECK (price_from_minor >= 0)');
        DB::statement('ALTER TABLE availability_rules ADD CONSTRAINT availability_rules_weekday_check CHECK (weekday BETWEEN 0 AND 6)');
        DB::statement('ALTER TABLE availability_rules ADD CONSTRAINT availability_rules_time_check CHECK (end_time > start_time)');
    }
};
