<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\AdminPermissions;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CatalogSeeder::class,
        ]);

        User::query()
            ->where('role', 'admin')
            ->each(function (User $admin): void {
                $admin->forceFill(['permissions' => AdminPermissions::all()])->save();
            });
    }
}
