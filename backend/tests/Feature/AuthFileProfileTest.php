<?php

namespace Tests\Feature;

use App\Models\Specialization;
use App\Models\User;
use App\Support\AdminPermissions;
use Database\Seeders\AdminSeeder;
use Database\Seeders\CatalogSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
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

    public function test_registration_sends_a_frontend_email_verification_link(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/register', [
            'full_name' => 'Email Verification User',
            'email' => 'verification@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'role' => 'customer',
            'date_of_birth' => '2000-01-01',
        ])->assertCreated();

        $user = User::query()->where('email', 'verification@example.com')->firstOrFail();
        Notification::assertSentTo($user, VerifyEmail::class);

        $url = (new VerifyEmail)->toMail($user)->actionUrl;
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertStringStartsWith('http://localhost:3000/auth/verify-email?', $url);
        $this->assertSame($user->id, (int) $query['id']);
        $this->assertStringContainsString('signature=', $url);
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

    public function test_unauthenticated_api_request_returns_json_401(): void
    {
        $this->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthenticated.');
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

        $this->getJson(parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY))
            ->assertOk();

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_password_reset_sends_a_frontend_link(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'reset-link@example.com']);

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => $user->email,
        ])->assertOk();

        Notification::assertSentTo($user, ResetPassword::class);
        $notification = Notification::sent($user, ResetPassword::class)->first();
        $url = $notification->toMail($user)->actionUrl;
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame('http://localhost:3000/auth/reset-password', explode('?', $url, 2)[0]);
        $this->assertNotEmpty($query['token']);
        $this->assertSame($user->email, $query['email']);
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

    public function test_expired_password_reset_token_is_rejected(): void
    {
        $user = User::factory()->create(['email' => 'expired-reset@example.com']);
        $token = Password::broker()->createToken($user);
        $now = now();

        Carbon::setTestNow($now->copy()->addMinutes((int) config('auth.passwords.users.expire') + 1));

        try {
            $this->postJson('/api/v1/auth/reset-password', [
                'token' => $token,
                'email' => $user->email,
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])->assertUnprocessable()
                ->assertJsonPath('message', 'The password reset token is invalid or expired.');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_invalid_password_reset_token_is_rejected(): void
    {
        $user = User::factory()->create(['email' => 'invalid-reset@example.com']);

        $this->postJson('/api/v1/auth/reset-password', [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'The password reset token is invalid or expired.');
    }

    public function test_password_reset_is_throttled_for_sixty_seconds(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'throttled-reset@example.com']);

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])
            ->assertOk();

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])
            ->assertStatus(429)
            ->assertJsonPath('message', 'Please wait 60 seconds before requesting another password reset email.')
            ->assertJsonPath('retry_after', 60)
            ->assertHeader('Retry-After', '60');
    }

    public function test_local_admin_seeder_is_idempotent_and_grants_all_permissions(): void
    {
        config()->set('auth.local_admin.email', 'local-admin@example.test');
        config()->set('auth.local_admin.password', 'Password123!');

        $this->seed(AdminSeeder::class);
        $this->seed(AdminSeeder::class);

        $this->assertDatabaseCount('users', 1);
        $admin = User::query()->where('email', 'local-admin@example.test')->firstOrFail();

        $this->assertSame('admin', $admin->role);
        $this->assertSame(AdminPermissions::all(), $admin->permissions);
        $this->assertTrue(Hash::check('Password123!', $admin->password_hash));
    }

    public function test_csrf_mismatch_returns_json_419_for_api_requests(): void
    {
        $request = Request::create(
            '/api/v1/auth/login',
            'POST',
            server: ['HTTP_ACCEPT' => 'application/json'],
        );
        $response = app(ExceptionHandler::class)->render(
            $request,
            new TokenMismatchException('CSRF token mismatch.'),
        );

        $this->assertSame(419, $response->getStatusCode());
        $this->assertSame('application/json', $response->headers->get('Content-Type'));
        $this->assertSame('CSRF token mismatch.', $response->getData(true)['message']);
    }

    public function test_invalid_payload_returns_json_422_with_field_errors(): void
    {
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['email']]);
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
        $specializationId = Specialization::query()->value('id');

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
        $specializationId = Specialization::query()->value('id');
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
        $specializationId = Specialization::query()->value('id');
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
        $this->actingAs($tutor)->postJson('/api/v1/tutor/profile/verification-documents', [
            'file_id' => $fileId,
            'document_type' => 'teaching_certificate',
        ])->assertConflict();

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
