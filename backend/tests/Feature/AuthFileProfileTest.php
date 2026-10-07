<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AuthFileProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_registration_creates_a_customer(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'full_name' => 'New Customer',
            'email' => 'new-customer@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'customer',
            'date_of_birth' => '2000-01-01',
        ]);

        $response->assertCreated()->assertJsonPath('data.role', 'customer');
        $this->assertDatabaseHas('users', [
            'email' => 'new-customer@example.com',
            'role' => 'customer',
        ]);
    }

    public function test_tutor_registration_creates_a_tutor(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'full_name' => 'New Tutor',
            'email' => 'new-tutor@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'tutor',
            'date_of_birth' => '1995-01-01',
        ])->assertCreated()->assertJsonPath('data.role', 'tutor');
    }

    public function test_user_under_eighteen_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'full_name' => 'Under Age',
            'email' => 'under-age@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'customer',
            'date_of_birth' => now()->subYears(17)->toDateString(),
        ])->assertUnprocessable();
    }

    public function test_role_cannot_be_changed_through_me(): void
    {
        $user = User::factory()->create(['role' => 'customer']);

        $this->actingAs($user)->patchJson('/api/v1/me', [
            'full_name' => 'Updated Name',
            'role' => 'admin',
        ])->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'role' => 'customer',
            'full_name' => 'Updated Name',
        ]);
    }

    public function test_login_and_current_user_work(): void
    {
        $user = User::factory()->create([
            'email' => 'login@example.com',
            'password_hash' => 'Password123!',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'Password123!',
        ])->assertOk()->assertJsonPath('data.email', 'login@example.com');

        $this->actingAs($user)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_email_verification_marks_the_user_verified(): void
    {
        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(10),
            ['id' => $user->id, 'hash' => sha1($user->getEmailForVerification())],
        );

        $this->actingAs($user)->getJson(parse_url($url, PHP_URL_PATH) . '?' . parse_url($url, PHP_URL_QUERY))
            ->assertOk();

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_password_reset_updates_the_password(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.com']);
        $token = Password::broker()->createToken($user);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertOk();

        $this->assertTrue(Hash::check('NewPassword123!', $user->refresh()->password_hash));
    }

    public function test_unverified_user_cannot_upload_business_file(): void
    {
        Storage::fake('local');
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->post('/api/v1/files', [
            'file' => UploadedFile::fake()->create('certificate.pdf', 100, 'application/pdf'),
            'purpose' => 'verification_document',
        ])->assertForbidden();
    }

    public function test_file_can_be_completed_and_only_owner_can_download_or_delete(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $other = User::factory()->create(['email_verified_at' => now()]);

        $upload = $this->actingAs($owner)->post('/api/v1/files', [
            'file' => UploadedFile::fake()->create('certificate.pdf', 100, 'application/pdf'),
            'purpose' => 'verification_document',
        ])->assertCreated();

        $fileId = $upload->json('data.id');

        $this->actingAs($owner)->get("/api/v1/files/{$fileId}/download")
            ->assertConflict();

        $this->actingAs($owner)->postJson("/api/v1/files/{$fileId}/complete")
            ->assertOk()
            ->assertJsonPath('data.complete', true);

        $this->actingAs($other)->get("/api/v1/files/{$fileId}/download")
            ->assertForbidden();

        $this->actingAs($other)->deleteJson("/api/v1/files/{$fileId}")
            ->assertForbidden();

        $this->actingAs($owner)->get("/api/v1/files/{$fileId}/download")
            ->assertOk();
    }

    public function test_submitted_tutor_profile_cannot_be_edited(): void
    {
        $tutor = User::factory()->create(['role' => 'tutor']);
        $this->seed(CatalogSeeder::class);
        $specializationId = \App\Models\Specialization::query()->value('id');

        $this->actingAs($tutor)->putJson('/api/v1/tutor/profile', [
            'headline' => 'English tutor',
            'bio' => 'Experienced tutor.',
            'experience_years' => 5,
            'specialization_id' => $specializationId,
        ])->assertCreated()->assertJsonPath('data.status', 'draft');

        $this->actingAs($tutor)->postJson('/api/v1/tutor/profile/submit')
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted');

        $this->actingAs($tutor)->putJson('/api/v1/tutor/profile', [
            'headline' => 'Changed headline',
            'experience_years' => 6,
            'specialization_id' => $specializationId,
        ])->assertStatus(409);
    }

    public function test_tutor_profile_stores_specialization_and_avatar(): void
    {
        Storage::fake('local');
        $this->seed(CatalogSeeder::class);
        $specializationId = \App\Models\Specialization::query()->value('id');
        $tutor = User::factory()->create([
            'role' => 'tutor',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($tutor)->putJson('/api/v1/tutor/profile', [
            'headline' => 'English tutor',
            'specialization_id' => $specializationId,
        ])->assertCreated()
            ->assertJsonPath('data.specialization_id', $specializationId);

        $upload = $this->actingAs($tutor)->post('/api/v1/files', [
            'file' => UploadedFile::fake()->create(
                'avatar.jpg',
                100,
                'image/jpeg',
            ),
            'purpose' => 'avatar',
        ])->assertCreated();

        $fileId = $upload->json('data.id');
        $this->actingAs($tutor)->postJson("/api/v1/files/{$fileId}/complete")
            ->assertOk();

        $this->actingAs($tutor)->putJson('/api/v1/tutor/profile/avatar', [
            'file_id' => $fileId,
        ])->assertOk()
            ->assertJsonPath('data.avatar_file_id', $fileId);
    }

    public function test_completed_verification_document_can_be_attached_to_tutor_profile(): void
    {
        Storage::fake('local');
        $this->seed(CatalogSeeder::class);
        $specializationId = \App\Models\Specialization::query()->value('id');
        $tutor = User::factory()->create([
            'role' => 'tutor',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($tutor)->putJson('/api/v1/tutor/profile', [
            'headline' => 'English tutor',
            'specialization_id' => $specializationId,
        ])->assertCreated();

        $upload = $this->actingAs($tutor)->post('/api/v1/files', [
            'file' => UploadedFile::fake()->create('certificate.pdf', 100, 'application/pdf'),
            'purpose' => 'verification_document',
        ])->assertCreated();

        $fileId = $upload->json('data.id');
        $this->actingAs($tutor)->postJson("/api/v1/files/{$fileId}/complete")
            ->assertOk();

        $this->actingAs($tutor)->postJson('/api/v1/tutor/profile/verification-documents', [
            'file_id' => $fileId,
            'document_type' => 'teaching_certificate',
        ])->assertCreated()
            ->assertJsonPath('data.file_id', $fileId)
            ->assertJsonPath('data.document_type', 'teaching_certificate');

        $this->actingAs($tutor)->getJson('/api/v1/tutor/profile')
            ->assertOk()
            ->assertJsonPath('data.verification_document_file_id', $fileId);
    }

    public function test_catalog_can_be_seeded_and_read(): void
    {
        $this->seed(CatalogSeeder::class);

        $this->getJson('/api/v1/catalog/specializations')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'conversation-english');
    }
}