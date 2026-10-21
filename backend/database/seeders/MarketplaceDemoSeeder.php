<?php

namespace Database\Seeders;

use App\Models\ServiceListing;
use App\Models\Specialization;
use App\Models\TutorProfile;
use Illuminate\Database\Seeder;

class MarketplaceDemoSeeder extends Seeder
{
    public function run(): void
    {
        $profile = TutorProfile::query()
            ->where('status', 'approved')
            ->whereHas('user', function ($query): void {
                $query->where('status', 'active');
            })
            ->first();

        $specialization = Specialization::query()
            ->where('status', 'active')
            ->whereHas('subcategory', function ($subcategory): void {
                $subcategory
                    ->where('status', 'active')
                    ->whereHas('category', function ($category): void {
                        $category->where('status', 'active');
                    });
            })
            ->first();

        if (!$profile || !$specialization) {
            return;
        }

        ServiceListing::updateOrCreate(
            [
                'slug' => 'demo-tutor-session',
            ],
            [
                'tutor_profile_id' => $profile->id,
                'specialization_id' => $specialization->id,
                'title' => 'Demo tutoring session',
                'description' => 'Listing dùng để kiểm thử Marketplace.',
                'delivery_mode' => 'online',
                'city' => 'Ho Chi Minh City',
                'district' => null,
                'price_from_minor' => 200000,
                'currency' => 'VND',
                'price_unit' => 'session',
                'status' => 'active',
            ],
        );
    }
}