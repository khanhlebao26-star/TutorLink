<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Database\Seeder;

class AdminPermissionSeeder extends Seeder
{
    public function run(): void
    {
        User::query()
            ->where('role', 'admin')
            ->each(function (User $admin): void {
                $admin->forceFill(['permissions' => AdminPermissions::all()])->save();
            });
    }
}
