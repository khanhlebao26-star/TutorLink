<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tutor_profiles', function (Blueprint $table): void {
            $table->string('public_slug', 180)
                ->nullable()
                ->unique()
                ->after('user_id');
        });

        $profiles = DB::table('tutor_profiles')
            ->select(['id', 'user_id'])
            ->orderBy('id')
            ->get();

        foreach ($profiles as $profile) {
            $name = DB::table('users')
                ->where('id', $profile->user_id)
                ->value('full_name') ?? 'tutor';

            $base = Str::slug($name) ?: 'tutor';
            $suffix = '-'.$profile->id;
            $base = Str::substr(
                $base,
                0,
                180 - strlen($suffix),
            );

            DB::table('tutor_profiles')
                ->where('id', $profile->id)
                ->update([
                    'public_slug' => $base.$suffix,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('tutor_profiles', function (Blueprint $table): void {
            $table->dropUnique('tutor_profiles_public_slug_unique');
            $table->dropColumn('public_slug');
        });
    }
};
