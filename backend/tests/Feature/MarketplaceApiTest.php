<?php

namespace Tests\Feature;

use App\Models\ServiceListing;
use App\Models\Specialization;
use App\Models\TutorProfile;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;
use App\Models\Category;
use App\Models\Subcategory;
use Database\Seeders\MarketplaceDemoSeeder;
use Illuminate\Support\Facades\DB;

class MarketplaceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_tutor_cannot_publish_listing(): void
    {
        $specialization = $this->seedCatalog();

        [$tutor, $profile] = $this->createTutor('pending');

        $listing = $this->createListing(
            $profile,
            $specialization,
            [
                'status' => 'draft',
            ],
        );

        $this->actingAs($tutor)
            ->patchJson("/api/v1/me/listings/{$listing->id}", [
                'status' => 'active',
            ])
            ->assertForbidden();
    }

    public function test_other_tutor_cannot_update_listing(): void
    {
        $specialization = $this->seedCatalog();

        [$owner, $ownerProfile] = $this->createTutor('active');
        $listing = $this->createListing(
            $ownerProfile,
            $specialization,
            [
                'status' => 'draft',
            ],
        );

        [$otherTutor] = $this->createTutor('active');

        $this->actingAs($otherTutor)
            ->patchJson("/api/v1/me/listings/{$listing->id}", [
                'title' => 'Hacked title',
            ])
            ->assertNotFound();
    }

    public function test_non_public_listing_is_not_returned(): void
    {
        $specialization = $this->seedCatalog();

        [, $profile] = $this->createTutor('active');

        $active = $this->createListing(
            $profile,
            $specialization,
            [
                'slug' => 'active-listing',
                'status' => 'active',
            ],
        );

        $this->createListing(
            $profile,
            $specialization,
            [
                'slug' => 'inactive-listing',
                'status' => 'inactive',
            ],
        );

        $this->createListing(
            $profile,
            $specialization,
            [
                'slug' => 'cancelled-listing',
                'status' => 'cancelled',
            ],
        );

        $this->getJson('/api/v1/listings')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id);
    }

    public function test_price_sort_requires_price_unit(): void
    {
        $specialization = $this->seedCatalog();

        [, $profile] = $this->createTutor('active');

        $cheap = $this->createListing(
            $profile,
            $specialization,
            [
                'slug' => 'cheap-session',
                'status' => 'active',
                'price_from_minor' => 100000,
                'price_unit' => 'session',
            ],
        );

        $this->createListing(
            $profile,
            $specialization,
            [
                'slug' => 'expensive-session',
                'status' => 'active',
                'price_from_minor' => 300000,
                'price_unit' => 'session',
            ],
        );

        $this->getJson('/api/v1/listings?sort=price_asc')
            ->assertUnprocessable();

        $this->getJson(
            '/api/v1/listings?sort=price_asc&price_unit=session',
        )
            ->assertOk()
            ->assertJsonPath('data.0.id', $cheap->id);
    }

    public function test_overlapping_availability_is_rejected(): void
    {
        [$approvedTutor] = $this->createTutor('active');

        $this->actingAs($approvedTutor)
            ->putJson('/api/v1/me/availability-rules', [
                'rules' => [
                    [
                        'weekday' => 1,
                        'start_time' => '08:00',
                        'end_time' => '10:00',
                        'timezone' => 'Asia/Ho_Chi_Minh',
                    ],
                    [
                        'weekday' => 1,
                        'start_time' => '09:00',
                        'end_time' => '11:00',
                        'timezone' => 'Asia/Ho_Chi_Minh',
                    ],
                ],
            ])
            ->assertUnprocessable();
    }

    public function test_delete_listing_soft_cancels_it(): void
    {
        $specialization = $this->seedCatalog();

        [$tutor, $profile] = $this->createTutor('active');

        $listing = $this->createListing(
            $profile,
            $specialization,
            [
                'status' => 'draft',
            ],
        );

        $this->actingAs($tutor)
            ->deleteJson("/api/v1/me/listings/{$listing->id}")
            ->assertNoContent();

        $this->assertDatabaseHas('service_listings', [
            'id' => $listing->id,
            'status' => 'cancelled',
        ]);
    }

    private function seedCatalog(): Specialization
    {
        $this->seed(CatalogSeeder::class);

        return Specialization::query()
            ->where('status', 'active')
            ->firstOrFail();
    }

    private function createTutor(
        string $approvalStatus,
    ): array {
        $tutor = User::factory()->create([
            'role' => 'tutor',
            'status' => 'active',
        ]);

        $profile = $tutor->tutorProfile()->create([
            'headline' => 'Test tutor',
            'bio' => 'Test tutor profile.',
            'experience_years' => 3,
            'approval_status' => $approvalStatus,
        ]);

        return [$tutor, $profile];
    }

    private function createListing(
        TutorProfile $profile,
        Specialization $specialization,
        array $overrides = [],
    ): ServiceListing {
        return ServiceListing::create(array_merge([
            'tutor_profile_id' => $profile->id,
            'specialization_id' => $specialization->id,
            'slug' => 'listing-' . Str::lower(Str::random(8)),
            'title' => 'Test listing',
            'description' => 'Test listing description.',
            'delivery_mode' => 'online',
            'city' => 'Ho Chi Minh City',
            'district' => null,
            'price_from_minor' => 200000,
            'currency' => 'VND',
            'price_unit' => 'session',
            'status' => 'draft',
        ], $overrides));
    }

    public function test_suspended_profile_cannot_publish_listing(): void
    {
        $specialization = $this->seedCatalog();

        [$tutor, $profile] = $this->createTutor('active');

        $listing = $this->createListing($profile, $specialization);

        $profile->update([
            'suspended_at' => now(),
        ]);

        $this->actingAs($tutor)
            ->patchJson("/api/v1/me/listings/{$listing->id}", [
                'status' => 'active',
            ])
            ->assertForbidden();
    }

    public function test_inactive_account_cannot_manage_listing(): void
    {
        $specialization = $this->seedCatalog();

        [$tutor, $profile] = $this->createTutor('active');

        $listing = $this->createListing($profile, $specialization);

        $tutor->update([
            'status' => 'inactive',
        ]);

        $this->actingAs($tutor)
            ->patchJson("/api/v1/me/listings/{$listing->id}", [
                'status' => 'active',
            ])
            ->assertForbidden();
    }

    public function test_other_tutor_cannot_read_or_delete_listing(): void
    {
        $specialization = $this->seedCatalog();

        [$owner, $ownerProfile] = $this->createTutor('active');
        $listing = $this->createListing(
            $ownerProfile,
            $specialization,
            [
                'status' => 'draft',
            ],
        );

        [$otherTutor] = $this->createTutor('active');

        $this->actingAs($otherTutor)
            ->getJson("/api/v1/me/listings/{$listing->id}")
            ->assertNotFound();

        $this->actingAs($otherTutor)
            ->deleteJson("/api/v1/me/listings/{$listing->id}")
            ->assertNotFound();
    }

    public function test_non_public_listing_is_hidden_from_list_and_detail(): void
    {
        $specialization = $this->seedCatalog();

        [, $profile] = $this->createTutor('active');

        $active = $this->createListing(
            $profile,
            $specialization,
            [
                'slug' => 'active-listing',
                'status' => 'active',
            ],
        );

        $inactive = $this->createListing(
            $profile,
            $specialization,
            [
                'slug' => 'inactive-listing',
                'status' => 'inactive',
            ],
        );

        $cancelled = $this->createListing(
            $profile,
            $specialization,
            [
                'slug' => 'cancelled-listing',
                'status' => 'cancelled',
            ],
        );

        $this->getJson('/api/v1/listings')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $active->id);

        $this->getJson("/api/v1/listings/{$active->slug}")
            ->assertOk();

        $this->getJson("/api/v1/listings/{$inactive->slug}")
            ->assertNotFound();

        $this->getJson("/api/v1/listings/{$cancelled->slug}")
            ->assertNotFound();
    }

    public function test_suspend_and_restore_changes_public_visibility(): void
    {
        $specialization = $this->seedCatalog();

        [$tutor, $profile] = $this->createTutor('active');

        $listing = $this->createListing(
            $profile,
            $specialization,
            [
                'status' => 'active',
            ],
        );

        $this->getJson('/api/v1/listings')
            ->assertJsonCount(1, 'data');

        $profile->update([
            'suspended_at' => now(),
        ]);

        $this->getJson('/api/v1/listings')
            ->assertJsonCount(0, 'data');

        $profile->update([
            'suspended_at' => null,
        ]);

        $this->getJson('/api/v1/listings')
            ->assertJsonCount(1, 'data');

        $tutor->update([
            'status' => 'inactive',
        ]);

        $this->getJson('/api/v1/listings')
            ->assertJsonCount(0, 'data');

        $tutor->update([
            'status' => 'active',
        ]);

        $this->getJson('/api/v1/listings')
            ->assertJsonCount(1, 'data');
    }

    public function test_inactive_catalog_is_not_public(): void
    {
        $specialization = $this->seedCatalog();

        [, $profile] = $this->createTutor('active');

        $listing = $this->createListing(
            $profile,
            $specialization,
            [
                'status' => 'active',
            ],
        );

        $this->getJson('/api/v1/listings')
            ->assertJsonCount(1, 'data');

        $specialization->load('subcategory.category');

        $specialization->subcategory->category->update([
            'status' => 'inactive',
        ]);

        $this->getJson('/api/v1/listings')
            ->assertJsonCount(0, 'data');

        $this->getJson("/api/v1/listings/{$listing->slug}")
            ->assertNotFound();
    }

    public function test_listing_filters_are_applied_on_server(): void
    {
        $specialization = $this->seedCatalog();
        $specialization->load('subcategory.category');

        [, $profile] = $this->createTutor('active');

        $matching = $this->createListing(
            $profile,
            $specialization,
            [
                'slug' => 'english-online-hanoi',
                'title' => 'English online Hanoi',
                'description' => 'English tutoring',
                'delivery_mode' => 'online',
                'city' => 'Hanoi',
                'status' => 'active',
            ],
        );

        $this->createListing(
            $profile,
            $specialization,
            [
                'slug' => 'other-city-listing',
                'title' => 'Other service',
                'delivery_mode' => 'offline',
                'city' => 'Da Nang',
                'status' => 'active',
            ],
        );

        $query = http_build_query([
            'q' => 'English',
            'category_id' => $specialization->subcategory->category_id,
            'subcategory_id' => $specialization->subcategory_id,
            'specialization_id' => $specialization->id,
            'delivery_mode' => 'online',
            'city' => 'Hanoi',
        ]);

        $this->getJson("/api/v1/listings?{$query}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $matching->id);
    }

    public function test_listing_pagination_returns_total_count(): void
    {
        $specialization = $this->seedCatalog();

        [, $profile] = $this->createTutor('active');

        foreach (range(1, 3) as $number) {
            $this->createListing(
                $profile,
                $specialization,
                [
                    'slug' => "active-listing-{$number}",
                    'status' => 'active',
                ],
            );
        }

        $this->getJson('/api/v1/listings?page=2&per_page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.total', 3);
    }

    public function test_price_filter_does_not_mix_price_units(): void
    {
        $specialization = $this->seedCatalog();

        [, $profile] = $this->createTutor('active');

        $session = $this->createListing(
            $profile,
            $specialization,
            [
                'slug' => 'session-listing',
                'status' => 'active',
                'price_from_minor' => 200000,
                'price_unit' => 'session',
            ],
        );

        $this->createListing(
            $profile,
            $specialization,
            [
                'slug' => 'hour-listing',
                'status' => 'active',
                'price_from_minor' => 100000,
                'price_unit' => 'hour',
            ],
        );

        $this->getJson('/api/v1/listings?min_price=150000')
            ->assertUnprocessable();

        $this->getJson(
            '/api/v1/listings?min_price=150000&price_unit=session',
        )
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $session->id);
    }

    public function test_owner_detail_and_public_tutor_slug_work(): void
    {
        $specialization = $this->seedCatalog();

        [$tutor, $profile] = $this->createTutor('active');

        $listing = $this->createListing(
            $profile,
            $specialization,
            [
                'status' => 'active',
            ],
        );

        $this->actingAs($tutor)
            ->getJson("/api/v1/me/listings/{$listing->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $listing->id);

        $profile->refresh();

        $this->getJson("/api/v1/tutors/{$profile->public_slug}")
            ->assertOk()
            ->assertJsonPath('data.slug', $profile->public_slug)
            ->assertJsonMissingPath('data.email')
            ->assertJsonMissingPath('data.verification_documents')
            ->assertJsonMissingPath('data.object_key');
    }

    public function test_catalog_seeder_is_idempotent(): void
    {
        $this->seed(CatalogSeeder::class);

        $before = [
            Category::query()->count(),
            Subcategory::query()->count(),
            Specialization::query()->count(),
        ];

        $this->seed(CatalogSeeder::class);

        $after = [
            Category::query()->count(),
            Subcategory::query()->count(),
            Specialization::query()->count(),
        ];

        $this->assertSame($before, $after);
    }

    public function test_archive_preserves_program_reference_and_snapshot(): void
    {
        $specialization = $this->seedCatalog();

        [$tutor, $profile] = $this->createTutor('active');

        $listing = $this->createListing(
            $profile,
            $specialization,
            [
                'status' => 'draft',
            ],
        );

        $snapshot = [
            'title' => $listing->title,
            'price_from_minor' => $listing->price_from_minor,
            'currency' => 'VND',
            'price_unit' => 'session',
        ];

        $programId = DB::table('programs')->insertGetId([
            'owner_tutor_profile_id' => $profile->id,
            'source_listing_id' => $listing->id,
            'version' => 1,
            'title' => 'Test Program',
            'delivery_mode' => 'online',
            'agreed_fee_minor' => 200000,
            'currency' => 'VND',
            'fee_unit' => 'session',
            'status' => 'pending',
            'offer_snapshot' => json_encode($snapshot),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($tutor)
            ->deleteJson("/api/v1/me/listings/{$listing->id}")
            ->assertNoContent();

        $this->assertDatabaseHas('service_listings', [
            'id' => $listing->id,
            'status' => 'cancelled',
        ]);

        $program = DB::table('programs')
            ->where('id', $programId)
            ->first();

        $this->assertSame($listing->id, $program->source_listing_id);
        $actualSnapshot = json_decode($program->offer_snapshot, true);

        ksort($snapshot);
        ksort($actualSnapshot);

        $this->assertSame($snapshot, $actualSnapshot);
    }
}