<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Subcategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Subcategory> */
class SubcategoryFactory extends Factory
{
    public function definition(): array
    {
        return ['category_id' => Category::factory(), 'name' => fake()->words(2, true), 'slug' => fake()->unique()->slug(), 'status' => 'active'];
    }
}
