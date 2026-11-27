<?php

namespace Database\Factories;

use App\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CustomerProfile> */
class CustomerProfileFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory()->customer(), 'bio' => fake()->paragraph(), 'city' => fake()->city()];
    }
}
