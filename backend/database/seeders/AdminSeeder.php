<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        $email = config('auth.local_admin.email');
        $password = config('auth.local_admin.password');

        if (! is_string($email) || $email === '' || ! is_string($password) || $password === '') {
            throw new RuntimeException('Set LOCAL_ADMIN_EMAIL and LOCAL_ADMIN_PASSWORD before seeding a local admin.');
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'full_name' => 'Local Admin',
                'password_hash' => $password,
                'role' => 'admin',
                'status' => 'active',
                'email_verified_at' => now(),
                'permissions' => AdminPermissions::all(),
            ],
        );
    }
}
