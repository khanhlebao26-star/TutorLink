<?php

namespace Database\Factories;

use App\Models\ServiceListing;
use App\Models\Specialization;
use App\Models\TutorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ServiceListing> */
class ServiceListingFactory extends Factory
{
    public function definition(): array
    {
        return ['tutor_profile_id' => TutorProfile::factory(), 'specialization_id' => Specialization::factory(), 'slug' => fake()->unique()->slug(), 'title' => fake()->sentence(4), 'description' => fake()->paragraph(), 'delivery_mode' => 'online', 'price_from_minor' => 200000, 'currency' => 'VND', 'price_unit' => 'session', 'status' => 'draft'];
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => 'active']);
    }
}
