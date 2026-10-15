<?php

namespace Tests\Feature;

use App\Models\ServiceListing;
use App\Models\Specialization;
use App\Models\TutorProfile;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MarketplaceCloseoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_status_counts_and_actions_are_scoped_before_pagination(): void
    {
        [$user, $profile] = $this->tutor();
        foreach (['draft', 'active', 'inactive', 'cancelled'] as $status) {
            $this->listing($profile, $status);
        }
        [, $other] = $this->tutor();
        $this->listing($other, 'draft');

        $this->actingAs($user)->getJson('/api/v1/me/listings?status=active&per_page=1')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('meta.status_counts', ['all' => 4, 'draft' => 1, 'active' => 1, 'inactive' => 1, 'cancelled' => 1])
            ->assertJsonPath('meta.allowed_actions', ['create'])
            ->assertJsonPath('data.0.allowed_actions', ['edit', 'pause', 'archive']);
        $this->getJson('/api/v1/me/listings?status=cancelled')
            ->assertJsonPath('data.0.allowed_actions', []);
        $this->getJson('/api/v1/me/listings?status=all&per_page=1&page=2')
            ->assertJsonPath('meta.total', 4)->assertJsonPath('data.0.status', 'inactive');
        $this->getJson('/api/v1/me/listings?status=hidden')->assertUnprocessable();
        $this->getJson('/api/v1/me/listings?per_page=51')->assertUnprocessable();
        $this->getJson('/api/v1/me/listings?page=0')->assertUnprocessable();
    }

    public function test_owner_actions_match_catalog_and_terminal_state_enforcement(): void
    {
        [$user, $profile] = $this->tutor();
        $draft = $this->listing($profile, 'draft');
        $this->actingAs($user)->getJson("/api/v1/me/listings/{$draft->id}")
            ->assertJsonPath('data.allowed_actions', ['edit', 'publish', 'archive']);
        $this->patchJson("/api/v1/me/listings/{$draft->id}", ['status' => 'inactive'])->assertConflict();
        $draft->specialization->subcategory->category->update(['status' => 'inactive']);
        $this->getJson("/api/v1/me/listings/{$draft->id}")
            ->assertJsonPath('data.allowed_actions', ['edit', 'archive']);
        $this->patchJson("/api/v1/me/listings/{$draft->id}", ['status' => 'active'])->assertUnprocessable();
        $this->deleteJson("/api/v1/me/listings/{$draft->id}")->assertNoContent();
        $this->patchJson("/api/v1/me/listings/{$draft->id}", ['title' => 'Cannot edit'])->assertConflict();
        $profile->update(['approval_status' => 'pending']);
        $user->unsetRelation('tutorProfile');
        $this->getJson('/api/v1/me/listings')->assertForbidden();
    }

    public function test_public_listing_exposes_tutor_slug_without_owner_actions(): void
    {
        [, $profile] = $this->tutor();
        $listing = $this->listing($profile, 'active');
        $this->getJson("/api/v1/listings/{$listing->slug}")->assertOk()
            ->assertJsonPath('data.tutor_slug', $profile->public_slug)
            ->assertJsonMissingPath('data.allowed_actions');
        $this->getJson("/api/v1/tutors/{$profile->public_slug}")->assertOk()
            ->assertJsonPath('data.listings.0.tutor_slug', $profile->public_slug);
    }

    public function test_reviews_exclude_all_non_completed_programs_and_return_empty_aggregate(): void
    {
        [, $profile] = $this->tutor();
        foreach (['pending', 'active', 'cancelled', 'rejected', 'expired'] as $status) {
            $this->review($profile, $status, 5);
        }
        $this->getJson("/api/v1/tutors/{$profile->public_slug}/reviews")->assertOk()
            ->assertJsonCount(0, 'data')->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 0)->assertJsonPath('aggregate.average', null)
            ->assertJsonPath('aggregate.count', 0)
            ->assertJsonPath('aggregate.histogram', ['1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0]);
    }

    public function test_reviews_validate_pagination_and_public_tutor_visibility(): void
    {
        [$user, $profile] = $this->tutor();
        $path = "/api/v1/tutors/{$profile->public_slug}/reviews";
        foreach (['page=0', 'page=no', 'per_page=0', 'per_page=51'] as $query) {
            $this->getJson("{$path}?{$query}")->assertUnprocessable();
        }
        $this->getJson('/api/v1/tutors/unknown-slug/reviews')->assertNotFound();
        $profile->update(['approval_status' => 'pending']);
        $this->getJson($path)->assertNotFound();
        $profile->update(['approval_status' => 'active', 'suspended_at' => now()]);
        $this->getJson($path)->assertNotFound();
        $profile->update(['suspended_at' => null]);
        $user->update(['status' => 'inactive']);
        $this->getJson($path)->assertNotFound();
    }

    public function test_completed_review_pagination_aggregates_full_set_and_exposes_only_public_fields(): void
    {
        $this->requireCompletedFixtureSupport();
        [, $profile] = $this->tutor();
        $ids = [];
        foreach ([1, 3, 5] as $rating) {
            $ids[] = $this->review($profile, 'completed', $rating);
        }
        $path = "/api/v1/tutors/{$profile->public_slug}/reviews?per_page=1&page=2";
        $response = $this->getJson($path)->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ids[1])->assertJsonPath('meta.total', 3)
            ->assertJsonPath('aggregate.average', 3)->assertJsonPath('aggregate.count', 3)
            ->assertJsonPath('aggregate.histogram', ['1' => 1, '2' => 0, '3' => 1, '4' => 0, '5' => 1]);
        $this->assertEqualsCanonicalizing(['id', 'author_name', 'rating', 'comment', 'created_at'], array_keys($response->json('data.0')));
        $this->getJson("/api/v1/tutors/{$profile->public_slug}/reviews?page=9&per_page=1")
            ->assertJsonCount(0, 'data')->assertJsonPath('aggregate.count', 3);
    }

    public function test_completed_reviews_require_matching_owner_customer_membership_and_undeleted_author(): void
    {
        $this->requireCompletedFixtureSupport();
        [, $profile] = $this->tutor();
        $historical = $this->review($profile, 'completed', 4, ['member_role' => 'customer', 'status' => 'cancelled', 'left_at' => now()]);
        $this->review($profile, 'completed', 5, ['member_role' => 'observer']);
        $wrongRole = $this->review($profile, 'completed', 5);
        User::findOrFail(DB::table('reviews')->where('id', $wrongRole)->value('customer_id'))
            ->update(['role' => 'tutor']);
        $outsider = $this->review($profile, 'completed', 5);
        DB::table('program_members')->where('program_id', DB::table('reviews')->where('id', $outsider)->value('program_id'))->delete();
        $this->review($profile, 'completed', 0);
        $deleted = $this->review($profile, 'completed', 2);
        $deletedAuthor = DB::table('reviews')->where('id', $deleted)->value('customer_id');
        User::findOrFail($deletedAuthor)->delete();
        $wrongOwner = $this->review($profile, 'completed', 1);
        [, $other] = $this->tutor();
        DB::table('programs')->where('id', DB::table('reviews')->where('id', $wrongOwner)->value('program_id'))
            ->update(['owner_tutor_profile_id' => $other->id]);
        $customer = DB::table('reviews')->where('id', $historical)->value('customer_id');
        User::findOrFail($customer)->update(['status' => 'inactive']);
        DB::table('reviews')->where('id', $historical)->update(['created_at' => null]);
        $this->getJson("/api/v1/tutors/{$profile->public_slug}/reviews")->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $historical)
            ->assertJsonPath('data.0.created_at', null)
            ->assertJsonPath('aggregate.count', 1);
    }

    private function requireCompletedFixtureSupport(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $this->markTestSkipped('W3 Program lifecycle has not added completed to the PostgreSQL status constraint; user explicitly deferred this migration. Populated review projection is tested with SQLite only.');
        }
    }

    private function tutor(): array
    {
        $user = User::factory()->create(['role' => 'tutor', 'status' => 'active']);

        return [$user, $user->tutorProfile()->create(['headline' => 'Test tutor', 'approval_status' => 'active'])];
    }

    private function listing(TutorProfile $profile, string $status): ServiceListing
    {
        $this->seed(CatalogSeeder::class);

        return ServiceListing::create([
            'tutor_profile_id' => $profile->id,
            'specialization_id' => Specialization::where('status', 'active')->firstOrFail()->id,
            'slug' => 'closeout-'.Str::uuid(), 'title' => 'Closeout listing', 'description' => 'Service description',
            'delivery_mode' => 'online', 'price_from_minor' => 200000, 'currency' => 'VND',
            'price_unit' => 'session', 'status' => $status,
        ]);
    }

    private function review(TutorProfile $profile, string $status, int $rating, array $membership = []): int
    {
        $customer = User::factory()->create(['role' => 'customer', 'status' => 'active']);
        $program = DB::table('programs')->insertGetId([
            'owner_tutor_profile_id' => $profile->id, 'title' => 'Private Program', 'delivery_mode' => 'online',
            'agreed_fee_minor' => 200000, 'currency' => 'VND', 'fee_unit' => 'session', 'status' => $status,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('program_members')->insert([
            'program_id' => $program, 'user_id' => $customer->id, 'member_role' => 'customer',
            'status' => 'active', 'created_at' => now(), 'updated_at' => now(), ...$membership,
        ]);

        return DB::table('reviews')->insertGetId([
            'program_id' => $program, 'customer_id' => $customer->id, 'tutor_profile_id' => $profile->id,
            'rating' => $rating, 'comment' => 'Helpful lessons',
            'created_at' => '2026-10-15 01:00:00', 'updated_at' => now(),
        ]);
    }
}
