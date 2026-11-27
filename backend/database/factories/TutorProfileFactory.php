<?php

namespace Database\Factories;

use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TutorProfile> */
class TutorProfileFactory extends Factory
{
    public function definition(): array
    {
        return ['user_id' => User::factory()->tutor(), 'headline' => fake()->sentence(4), 'bio' => fake()->paragraph(), 'experience_years' => 3, 'status' => 'draft', 'qualification_claims' => []];
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => 'approved', 'reviewed_at' => now()]);
    }
}
