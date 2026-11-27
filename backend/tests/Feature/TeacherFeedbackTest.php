<?php

use App\Http\Requests\TutorProfileRequest;
use App\Http\Requests\UpdateListingRequest;
use App\Http\Requests\UpdateProgramRequest;
use App\Models\AvailabilityRule;
use App\Models\CustomerProfile;
use App\Models\Program;
use App\Models\ServiceListing;
use App\Models\TutorProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Tests\Support\ProgramApiFixtures;
use Tests\Support\TestActors;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('relational factories persist valid catalog profiles listings availability and drafts', function (): void {
    $listing = ServiceListing::factory()->active()->create();
    $profile = $listing->tutorProfile;
    $availability = AvailabilityRule::factory()->for($profile)->create();
    $program = Program::factory()->for($profile, 'ownerTutorProfile')->create();
    $customer = CustomerProfile::factory()->create();
    expect($profile->user->role)->toBe('tutor');
    expect($customer->user->role)->toBe('customer');
    expect($listing->specialization->subcategory->category->status)->toBe('active');
    expect($profile->public_slug)->not->toBeEmpty();
    expect($availability->tutor_profile_id)->toBe($profile->id);
    expect($program->status)->toBe('draft');
});

test('password recovery rejects the sixth request from an IP with 429', function (string $path): void {
    $this->freezeTime();
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/auth/'.$path, [])->assertUnprocessable();
    }
    $this->postJson('/api/v1/auth/'.$path, [])->assertTooManyRequests()->assertHeader('Retry-After');
})->with(['forgot-password', 'reset-password']);

test('upload uses configured private disk and enforces owner quota without leaving an object', function (): void {
    Storage::fake('private-test');
    config(['filesystems.default' => 'private-test', 'file-validation.private_disks' => ['private-test'], 'file-validation.user_quota_bytes' => 1500]);
    $owner = TestActors::customer();
    $other = TestActors::customer();
    $upload = fn () => ['purpose' => 'task_attachment', 'file' => UploadedFile::fake()->createWithContent('sample.pdf', str_pad('%PDF-1.4', 1000, ' '))];
    $generation = DB::table('marketplace_cache_versions')->where('id', 1)->value('generation');
    $response = $this->actingAs($owner)->postJson('/api/v1/files', $upload())->assertCreated();
    $this->assertDatabaseHas('files', ['id' => $response->json('data.id'), 'disk' => 'private-test', 'owner_id' => $owner->id]);
    $this->postJson('/api/v1/files/'.$response->json('data.id').'/complete')->assertOk();
    $this->get('/api/v1/files/'.$response->json('data.id').'/download')->assertOk();
    $this->postJson('/api/v1/files', $upload())->assertUnprocessable()->assertJsonPath('code', 'file_quota_exceeded');
    expect(Storage::disk('private-test')->allFiles())->toHaveCount(1);
    expect(DB::table('marketplace_cache_versions')->where('id', 1)->value('generation'))->toBe($generation);
    $this->actingAs($other)->postJson('/api/v1/files', $upload())->assertCreated();
    $this->assertDatabaseCount('files', 2);
});

test('upload rejects the thirty first attempt per owner and isolates another owner', function (): void {
    $this->freezeTime();
    $this->actingAs(TestActors::customer());
    for ($i = 0; $i < 30; $i++) {
        $this->postJson('/api/v1/files', [])->assertUnprocessable();
    }
    $this->postJson('/api/v1/files', [])->assertTooManyRequests();
    $this->actingAs(TestActors::customer())->postJson('/api/v1/files', [])->assertUnprocessable();
});

test('program update rejects malformed insecure and credential-bearing meeting links', function (string $url): void {
    $validator = Validator::make(['meeting_url' => $url], (new UpdateProgramRequest)->rules());
    expect($validator->errors()->has('meeting_url'))->toBeTrue();
})->with(['not-a-url', 'http://example.com/meeting', 'https://user:password@example.com/meeting']);

test('program update accepts an https meeting link', function (): void {
    expect(Validator::make(['expected_version' => 1, 'meeting_url' => 'https://example.com/meeting'], (new UpdateProgramRequest)->rules())->passes())->toBeTrue();
});

test('listing update rejects oversized text and money', function (): void {
    $validator = Validator::make(['description' => str_repeat('a', 10001), 'price_from_minor' => '9223372036854775808'], (new UpdateListingRequest)->rules());
    expect($validator->errors()->has('description'))->toBeTrue();
    expect($validator->errors()->has('price_from_minor'))->toBeTrue();
    expect(Validator::make(['bio' => str_repeat('a', 10001)], (new TutorProfileRequest)->rules())->errors()->has('bio'))->toBeTrue();
});

test('public etag returns 304 only while the current representation still matches', function (): void {
    $listing = ServiceListing::factory()->create();
    $first = $this->getJson('/api/v1/catalog/categories')->assertOk();
    $etag = $first->headers->get('ETag');
    expect($etag)->not->toBeNull();
    $this->getJson('/api/v1/catalog/categories', ['If-None-Match' => $etag])->assertStatus(304)->assertContent('');
    $listing->specialization->subcategory->category->update(['name' => 'Changed category']);
    $this->getJson('/api/v1/catalog/categories', ['If-None-Match' => $etag])->assertOk();
});

test('private reads do not use public conditional responses', function (): void {
    $this->actingAs(TestActors::customer())->getJson('/api/v1/catalog/categories', ['If-None-Match' => '*'])
        ->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertHeaderMissing('ETag');
});

test('irrelevant updates preserve cache while relevant writes invalidate only the required scopes', function (): void {
    $profile = TutorProfile::factory()->create();
    $catalog = fn () => DB::table('marketplace_cache_versions')->where('id', 2)->value('generation');
    $market = fn () => DB::table('marketplace_cache_versions')->where('id', 1)->value('generation');
    $c = $catalog();
    $m = $market();
    User::factory()->create();
    expect($market())->toBe($m);
    $profile->user->update(['remember_token' => 'session-token']);
    expect($market())->toBe($m);
    $profile->user->update(['full_name' => 'New public name']);
    expect($market())->not->toBe($m);
    expect($catalog())->toBe($c);
    $m = $market();
    DB::table('users')->where('id', -1)->update(['full_name' => 'No affected rows']);
    expect($market())->toBe($m);
});

test('a withdrawn listing returns 404 even with its previous etag', function (): void {
    $tutor = ProgramApiFixtures::eligibleTutor();
    $id = ProgramApiFixtures::listing($tutor);
    DB::table('service_listings')->where('id', $id)->update(['status' => 'active']);
    $url = '/api/v1/listings/'.DB::table('service_listings')->where('id', $id)->value('slug');
    $etag = $this->getJson($url)->assertOk()->headers->get('ETag');
    $this->getJson($url, ['If-None-Match' => $etag])->assertStatus(304);
    DB::table('users')->where('id', $tutor->id)->update(['status' => 'suspended', 'suspended_at' => now()]);
    $this->getJson($url, ['If-None-Match' => $etag])->assertNotFound()->assertHeader('Cache-Control', 'no-store, private');
});

test('a public disk configuration rejects upload before writing a file', function (): void {
    Storage::fake('public');
    config(['filesystems.default' => 'public']);
    $this->actingAs(TestActors::customer())->postJson('/api/v1/files', [
        'purpose' => 'task_attachment', 'file' => UploadedFile::fake()->createWithContent('sample.pdf', '%PDF-1.4 sample'),
    ])->assertStatus(503);
    expect(Storage::disk('public')->allFiles())->toBeEmpty();
    $this->assertDatabaseCount('files', 0);
});
