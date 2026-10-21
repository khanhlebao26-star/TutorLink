<?php

namespace Tests\Feature;

use App\Models\ServiceListing;
use App\Models\Specialization;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketplaceSearchCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_mixed_case_search_matches_title_and_description_with_filters_and_pagination(): void
    {
        $this->seed(CatalogSeeder::class);
        $specialization = Specialization::query()->where('status', 'active')->firstOrFail();
        $tutor = User::factory()->create(['role' => 'tutor', 'status' => 'active']);
        $profile = $tutor->tutorProfile()->create([
            'headline' => 'Search tutor',
            'bio' => 'Search compatibility fixture.',
            'experience_years' => 3,
            'status' => 'approved',
        ]);

        $createListing = fn (array $overrides): ServiceListing => ServiceListing::create(array_merge([
            'tutor_profile_id' => $profile->id,
            'specialization_id' => $specialization->id,
            'slug' => 'search-'.Str::uuid(),
            'title' => 'Other subject',
            'description' => 'Individual tuition.',
            'delivery_mode' => 'online',
            'city' => 'Ho Chi Minh City',
            'price_from_minor' => 200000,
            'currency' => 'VND',
            'price_unit' => 'session',
            'status' => 'active',
        ], $overrides));

        $titleMatch = $createListing(['title' => 'Practical AlGeBrA']);
        $descriptionMatch = $createListing(['description' => 'Learn ALGEBRA with worked examples.']);
        $createListing(['title' => 'Geometry']);
        // A description match must not escape the grouped public/filter predicates.
        $createListing(['description' => 'Algebra', 'delivery_mode' => 'offline']);
        $createListing(['description' => 'Algebra', 'status' => 'draft']);
        $createListing(['description' => 'Algebra', 'price_from_minor' => 500000]);

        $query = http_build_query([
            'q' => 'aLgEbRa',
            'specialization_id' => $specialization->id,
            'delivery_mode' => 'online',
            'city' => 'Ho Chi Minh City',
            'price_unit' => 'session',
            'min_price' => 100000,
            'max_price' => 300000,
            'per_page' => 1,
        ]);

        $this->getJson('/api/v1/listings?'.$query)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $descriptionMatch->id)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.current_page', 1);

        $this->getJson('/api/v1/listings?'.$query.'&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $titleMatch->id)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.current_page', 2);

        $this->getJson('/api/v1/listings?q=nonexistent-subject')
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }
}
