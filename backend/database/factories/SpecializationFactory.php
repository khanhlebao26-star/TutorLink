<?php

namespace Database\Factories;

use App\Models\Specialization;
use App\Models\Subcategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Specialization> */
class SpecializationFactory extends Factory
{
    public function definition(): array
    {
        return ['subcategory_id' => Subcategory::factory(), 'name' => fake()->words(2, true), 'slug' => fake()->unique()->slug(), 'status' => 'active'];
    }
}
