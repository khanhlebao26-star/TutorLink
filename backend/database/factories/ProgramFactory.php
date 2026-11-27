<?php

namespace Database\Factories;

use App\Models\Program;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Program> */
class ProgramFactory extends Factory
{
    public function definition(): array
    {
        return ['customer_id' => User::factory()->customer(), 'owner_tutor_profile_id' => TutorProfile::factory(), 'title' => fake()->sentence(4), 'delivery_mode' => 'online', 'agreed_fee_minor' => 200000, 'currency' => 'VND', 'fee_unit' => 'session', 'status' => 'draft', 'timezone' => 'Asia/Ho_Chi_Minh'];
    }
}
