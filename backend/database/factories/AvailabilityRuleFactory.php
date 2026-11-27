<?php

namespace Database\Factories;

use App\Models\AvailabilityRule;
use App\Models\TutorProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AvailabilityRule> */
class AvailabilityRuleFactory extends Factory
{
    public function definition(): array
    {
        return ['tutor_profile_id' => TutorProfile::factory(), 'weekday' => 1, 'start_time' => '18:00', 'end_time' => '20:00', 'timezone' => 'Asia/Ho_Chi_Minh'];
    }
}
