<?php

namespace Tests\Support;

use App\Models\User;
use App\Support\AdminPermissions;

final class TestActors
{
    public static function user(array $attributes = []): User
    {
        return User::factory()->create(array_replace([
            'date_of_birth' => '1990-01-01',
        ], $attributes));
    }

    public static function customer(array $attributes = []): User
    {
        $user = self::user(array_replace($attributes, [
            'role' => 'customer',
            'permissions' => [],
        ]));

        $user->customerProfile()->create([]);

        return $user->load('customerProfile');
    }

    public static function tutor(array $attributes = []): User
    {
        $user = self::user(array_replace($attributes, [
            'role' => 'tutor',
            'permissions' => [],
        ]));

        $user->tutorProfile()->create([
            'headline' => 'Test tutor',
            'bio' => 'Tutor profile for automated tests.',
            'status' => 'draft',
        ]);

        return $user->load('tutorProfile');
    }

    public static function admin(?array $permissions = null): User
    {
        return User::factory()->admin()->create([
            'date_of_birth' => '1990-01-01',
            'permissions' => $permissions ?? AdminPermissions::all(),
        ]);
    }
}
