<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\ProgramApiFixtures;
use Tests\Support\TestActors;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function phaseFiveListing(): array
{
    $tutor = ProgramApiFixtures::eligibleTutor();
    $id = ProgramApiFixtures::listing($tutor);
    DB::table('service_listings')->where('id', $id)->update(['status' => 'active']);

    return [$tutor, DB::table('service_listings')->find($id)];
}

it('rechecks public eligibility on every surface after cached verified account changes', function () {
    [$tutor, $listing] = phaseFiveListing();
    $paths = ['/api/v1/listings', '/api/v1/listings/'.$listing->slug, '/api/v1/tutors/'.$tutor->tutorProfile->public_slug, '/api/v1/tutors/'.$tutor->tutorProfile->public_slug.'/reviews'];
    foreach ($paths as $path) {
        $this->getJson($path)->assertOk()->assertHeader('X-Marketplace-Cache', 'MISS');
        $this->getJson($path)->assertOk()->assertHeader('X-Marketplace-Cache', 'HIT');
    }
    $tutor->forceFill(['email_verified_at' => null])->save();
    $this->getJson($paths[0])->assertOk()->assertJsonCount(0, 'data');
    foreach (array_slice($paths, 1) as $path) {
        $this->getJson($path)->assertNotFound();
    }
    $tutor->forceFill(['email_verified_at' => now()])->save();
    $this->getJson($paths[1])->assertOk();
    DB::table('users')->where('id', $tutor->id)->update(['status' => 'suspended']);
    $this->getJson($paths[0])->assertJsonCount(0, 'data');
    $this->getJson($paths[2])->assertNotFound();
});

it('invalidates listing profile catalog and moderation mutations including query builder writes', function () {
    [$tutor, $listing] = phaseFiveListing();
    $url = '/api/v1/listings/'.$listing->slug;
    $this->getJson($url)->assertOk();
    foreach (['tutor_profiles' => $tutor->tutorProfile->id, 'specializations' => $listing->specialization_id, 'service_listings' => $listing->id] as $table => $id) {
        $old = DB::table($table)->where('id', $id)->value('status');
        DB::table($table)->where('id', $id)->update(['status' => $table === 'tutor_profiles' ? 'suspended' : 'inactive']);
        $this->getJson($url)->assertNotFound();
        DB::table($table)->where('id', $id)->update(['status' => $old]);
        $this->getJson($url)->assertOk();
    }
    DB::table('listing_moderation_holds')->insert(['listing_id' => $listing->id, 'hidden_by_admin_id' => TestActors::admin()->id, 'hidden_at' => now(), 'hide_reason' => 'Review', 'hide_reason_code' => 'other']);
    $this->getJson($url)->assertNotFound();
    DB::table('listing_moderation_holds')->where('listing_id', $listing->id)->update(['restored_at' => now(), 'restored_by_admin_id' => TestActors::admin()->id, 'restore_reason' => 'Reviewed', 'restore_reason_code' => 'other']);
    $this->getJson($url)->assertOk();
});

it('expires required documents without waiting for a scheduler or cache TTL', function () {
    $this->freezeTime();
    [$tutor, $listing] = phaseFiveListing();
    DB::table('verification_documents')->where('tutor_profile_id', $tutor->tutorProfile->id)->update(['expires_at' => now()->addSeconds(10)]);
    $url = '/api/v1/listings/'.$listing->slug;
    $this->getJson($url)->assertOk();
    $this->getJson($url)->assertHeader('X-Marketplace-Cache', 'HIT');
    $this->travel(10)->seconds();
    $this->getJson($url)->assertNotFound();
    DB::table('verification_documents')->where('tutor_profile_id', $tutor->tutorProfile->id)->update(['expires_at' => now()->addDay()]);
    $this->getJson($url)->assertOk();
    $tutor->tutorProfile->update(['qualification_claims' => [['type' => 'degree', 'name' => 'Teaching']]]);
    $this->getJson($url)->assertNotFound();
});

it('isolates cache generations across rollback and safely falls back when cache is unavailable', function () {
    [, $listing] = phaseFiveListing();
    $url = '/api/v1/listings/'.$listing->slug;
    $this->getJson($url)->assertJsonPath('data.title', 'English conversation');
    DB::beginTransaction();
    DB::table('service_listings')->where('id', $listing->id)->update(['title' => 'Uncommitted title']);
    $this->getJson($url)->assertJsonPath('data.title', 'Uncommitted title');
    DB::rollBack();
    $this->getJson($url)->assertJsonPath('data.title', 'English conversation');
    Cache::shouldReceive('get')->once()->andThrow(new RuntimeException('cache offline'));
    Cache::shouldReceive('put')->once()->andThrow(new RuntimeException('cache offline'));
    $this->getJson($url)->assertOk()->assertHeader('X-Marketplace-Cache', 'BYPASS')->assertJsonPath('data.title', 'English conversation');
});

it('bounds embedded cards with stable pagination and never publishes private profile columns', function () {
    [$tutor, $listing] = phaseFiveListing();
    $template = (array) $listing;
    unset($template['id']);
    for ($i = 0; $i < 11; $i++) {
        DB::table('service_listings')->insert([...$template, 'slug' => 'card-'.$i]);
    }
    $url = '/api/v1/tutors/'.$tutor->tutorProfile->public_slug;
    $response = $this->getJson($url)->assertOk()->assertJsonCount(10, 'data.listings')
        ->assertJsonPath('data.listings_meta', ['page' => 1, 'per_page' => 10, 'has_more' => true, 'next_page' => 2])
        ->assertJsonMissingPath('data.email')->assertJsonMissingPath('data.verification_documents')
        ->assertJsonMissingPath('data.listings.0.description')->assertJsonMissingPath('data.listings.0.allowed_actions');
    expect($response->getContent())->not->toContain($tutor->email);
    $this->getJson($url.'?listings_page=2')->assertOk()->assertJsonCount(2, 'data.listings')->assertJsonPath('data.listings_meta.next_page', null);
    $this->getJson($url.'?listings_page=0')->assertUnprocessable();
    $this->getJson('/api/v1/listings?sort=price_from_minor;DROP TABLE users')->assertUnprocessable();
    $this->getJson('/api/v1/listings?per_page=51')->assertUnprocessable();
    $oldSlug = $tutor->tutorProfile->public_slug;
    $tutor->update(['full_name' => 'Renamed Tutor']);
    expect($tutor->tutorProfile->fresh()->public_slug)->toBe($oldSlug);
});

it('caches only anonymous public DTOs and marks authenticated and private responses no-store', function () {
    [$tutor, $listing] = phaseFiveListing();
    $url = '/api/v1/listings/'.$listing->slug;
    $this->getJson($url)->assertHeader('Cache-Control', 'must-revalidate, no-cache, public');
    $this->getJson($url)->assertHeader('X-Marketplace-Cache', 'HIT');
    $this->actingAs($tutor, 'web')->getJson($url)->assertHeader('Cache-Control', 'no-store, private')->assertHeaderMissing('X-Marketplace-Cache');
    $this->getJson('/api/v1/me')->assertHeader('Cache-Control', 'no-store, private');
    $this->actingAs(TestActors::admin(), 'web')->getJson('/api/v1/admin/tutor-profiles?per_page=51')->assertUnprocessable();
});

it('keeps query counts bounded as public and assigned admin pages grow', function () {
    $admin = TestActors::admin();
    for ($i = 0; $i < 20; $i++) {
        [$tutor] = phaseFiveListing();
        $tutor->tutorProfile->update(['assigned_admin_id' => $admin->id]);
    }
    $this->actingAs($admin);
    foreach (['/api/v1/listings?', '/api/v1/admin/tutor-profiles?status=approved&'] as $url) {
        $counts = [];
        foreach ([1, 20] as $size) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson($url.'per_page='.$size)->assertOk()->assertJsonCount($size, 'data');
            $counts[] = count(DB::getQueryLog());
            DB::disableQueryLog();
        }
        expect($counts[1])->toBe($counts[0]);
    }
});
