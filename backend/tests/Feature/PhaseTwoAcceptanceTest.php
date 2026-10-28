<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\Specialization;
use App\Models\TutorProfile;
use App\Models\User;
use App\Support\AdminPermissions;
use Database\Seeders\AdminSeeder;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class PhaseTwoAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_avatar_rejects_documents_and_files_over_two_mib(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        foreach ([UploadedFile::fake()->create('avatar.pdf', 100, 'application/pdf'), UploadedFile::fake()->create('avatar.jpg', 2049, 'image/jpeg')] as $file) {
            $this->actingAs($user)->postJson('/api/v1/files', ['file' => $file, 'purpose' => 'avatar'])
                ->assertUnprocessable()->assertJsonValidationErrors('file');
        }
        $this->assertDatabaseCount('files', 0);
    }

    public function test_missing_or_tampered_storage_cannot_be_completed(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $file = $this->file($user, 'verification_document');
        $file->update(['scan_status' => 'pending']);
        Storage::disk('local')->delete($file->object_key);
        $this->actingAs($user)->postJson("/api/v1/files/{$file->id}/complete")
            ->assertStatus(503)
            ->assertHeader('Retry-After', '10')
            ->assertJsonPath('code', 'file_storage_unavailable');
        $this->assertSame('pending', $file->refresh()->scan_status);

        Storage::disk('local')->put($file->object_key, 'tampered');
        $this->actingAs($user)->postJson("/api/v1/files/{$file->id}/complete")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'file_rejected');
        $this->assertSame('rejected', $file->refresh()->scan_status);
        $this->actingAs($user)->get("/api/v1/files/{$file->id}/download")->assertConflict();
    }

    public function test_attached_files_cannot_be_deleted_and_storage_is_preserved(): void
    {
        Storage::fake('local');
        $user = User::factory()->create(['role' => 'tutor']);
        $profile = $this->readyProfile($user);
        foreach ([$profile->avatar_file_id, $profile->verificationDocuments()->value('file_id')] as $id) {
            $file = File::findOrFail($id);
            $this->actingAs($user)->deleteJson("/api/v1/files/{$id}")->assertConflict();
            $this->assertDatabaseHas('files', ['id' => $id]);
            Storage::disk('local')->assertExists($file->object_key);
        }
    }

    public function test_unlinked_owner_file_can_be_deleted(): void
    {
        Storage::fake('local');
        $owner = User::factory()->create();
        $file = $this->file($owner, 'verification_document');
        $this->actingAs($owner)->deleteJson("/api/v1/files/{$file->id}")->assertNoContent();
        $this->assertDatabaseMissing('files', ['id' => $file->id]);
        Storage::disk('local')->assertMissing($file->object_key);
    }

    public function test_generic_links_do_not_grant_cross_resource_access_or_fake_future_features(): void
    {
        Storage::fake('local');
        $tutor = User::factory()->create(['role' => 'tutor']);
        $other = User::factory()->create(['role' => 'tutor']);
        $profile = $this->readyProfile($other);
        $file = $this->file($tutor, 'verification_document');
        $document = $profile->verificationDocuments()->firstOrFail();
        $this->actingAs($tutor)->postJson("/api/v1/files/{$file->id}/links", [
            'resource_type' => 'verification_document',
            'resource_id' => $document->id,
        ])->assertForbidden();
        $this->actingAs($tutor)->postJson("/api/v1/files/{$file->id}/links", [
            'resource_type' => 'task',
            'resource_id' => 999,
        ])->assertConflict();
        $this->assertDatabaseCount('file_links', 0);
    }

    public function test_deactivated_account_cannot_use_me_or_login(): void
    {
        $user = User::factory()->create(['status' => 'deactivated', 'password_hash' => 'Password123!']);
        $this->actingAs($user)->getJson('/api/v1/me')->assertForbidden();
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'Password123!'])->assertForbidden();
    }

    public function test_unverified_tutor_cannot_submit_and_incomplete_profile_returns_field_errors(): void
    {
        $tutor = User::factory()->unverified()->create(['role' => 'tutor']);
        $profile = $tutor->tutorProfile()->create(['headline' => 'Incomplete', 'status' => 'draft']);
        $this->actingAs($tutor)->postJson('/api/v1/tutor/profile/submit')->assertForbidden();
        $tutor->markEmailAsVerified();
        $this->actingAs($tutor->refresh())->postJson('/api/v1/tutor/profile/submit')
            ->assertUnprocessable()->assertJsonValidationErrors(['bio', 'specialization_id', 'avatar_file_id']);
        $this->assertNull($profile->refresh()->submitted_at);
    }

    public function test_ready_profile_submit_is_idempotent_and_locks_every_edit_path(): void
    {
        Storage::fake('local');
        $tutor = User::factory()->create(['role' => 'tutor']);
        $profile = $this->readyProfile($tutor);
        $first = $this->actingAs($tutor)->postJson('/api/v1/tutor/profile/submit')->assertOk()->assertJsonPath('data.status', 'submitted');
        $second = $this->actingAs($tutor)->postJson('/api/v1/tutor/profile/submit')->assertOk();
        $this->assertSame($first->json('data.submitted_at'), $second->json('data.submitted_at'));
        $this->actingAs($tutor)->putJson('/api/v1/tutor/profile/avatar', ['file_id' => $profile->avatar_file_id])->assertConflict();
        $this->actingAs($tutor)->postJson('/api/v1/tutor/profile/verification-documents', [
            'file_id' => $profile->verificationDocuments()->value('file_id'),
            'document_type' => 'identity_card',
        ])->assertConflict();
        $this->actingAs($tutor)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.profile.status', 'submitted');
    }

    public function test_admin_queue_excludes_drafts_and_view_only_admin_has_no_actions(): void
    {
        $tutor = User::factory()->create(['role' => 'tutor']);
        $profile = $tutor->tutorProfile()->create(['headline' => 'Draft', 'status' => 'draft']);
        $admin = User::factory()->create(['role' => 'admin', 'permissions' => [AdminPermissions::VIEW_TUTOR_PROFILES]]);
        $profile->update(['assigned_admin_id' => $admin->id]);
        $this->actingAs($admin)->getJson('/api/v1/admin/tutor-profiles')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($admin)->getJson("/api/v1/admin/tutor-profiles/{$profile->id}")->assertOk()->assertJsonPath('data.allowed_actions', []);
    }

    public function test_reject_is_terminal_and_restore_requires_each_separate_permission(): void
    {
        $admin = User::factory()->admin()->create();
        $tutor = User::factory()->create(['role' => 'tutor']);
        $profile = $tutor->tutorProfile()->create(['headline' => 'Submitted', 'status' => 'submitted', 'submitted_at' => now(), 'assigned_admin_id' => $admin->id]);
        $this->actingAs($admin)->postJson("/api/v1/admin/tutor-profiles/{$profile->id}/reject", ['reason' => 'Invalid proof.', 'assignment_version' => 0])->assertOk()->assertJsonPath('data.profile.status', 'rejected');
        $this->actingAs($admin)->postJson("/api/v1/admin/tutor-profiles/{$profile->id}/approve", ['reason' => 'Stale action.', 'assignment_version' => 0])->assertConflict();
        $this->actingAs($admin)
            ->postJson("/api/v1/admin/tutor-profiles/{$profile->id}/suspend", [
                'reason' => 'Policy.',
            ])
            ->assertOk();
        $viewer = User::factory()->create(['role' => 'admin', 'permissions' => [AdminPermissions::VIEW_TUTOR_PROFILES]]);
        $this->actingAs($viewer)->postJson("/api/v1/admin/tutor-profiles/{$profile->id}/restore", ['reason' => 'No permission.'])->assertForbidden();
        $this->actingAs($viewer)->postJson("/api/v1/admin/users/{$tutor->id}/restore", ['reason' => 'No permission.'])->assertForbidden();
        $this->assertSame('suspended', $profile->refresh()->status);
        $this->assertSame('rejected', $profile->status_before_suspension);
        $this->actingAs($admin)->postJson("/api/v1/admin/tutor-profiles/{$profile->id}/restore", ['reason' => 'Restore previous state.'])->assertOk();
        $this->assertSame('rejected', $profile->refresh()->status);
        $this->assertNull($profile->suspended_at);
        $this->assertSame('suspended', $tutor->refresh()->status);
    }

    public function test_deactivated_accounts_cannot_be_approved_or_reactivated_by_suspend_restore(): void
    {
        $admin = User::factory()->admin()->create();
        $tutor = User::factory()->create(['role' => 'tutor', 'status' => 'deactivated']);
        $profile = $tutor->tutorProfile()->create(['headline' => 'Submitted', 'status' => 'submitted', 'submitted_at' => now(), 'assigned_admin_id' => $admin->id]);
        $this->actingAs($admin)->postJson("/api/v1/admin/tutor-profiles/{$profile->id}/approve", ['reason' => 'Cancelled account.', 'assignment_version' => 0])->assertConflict();
        $this->actingAs($admin)->postJson("/api/v1/admin/users/{$tutor->id}/suspend", ['reason' => 'Cancelled account.', 'assignment_version' => 0])->assertConflict();
        $this->assertSame('deactivated', $tutor->refresh()->status);
    }

    public function test_audit_failure_rolls_back_admin_decision(): void
    {
        $admin = User::factory()->admin()->create();
        $tutor = User::factory()->create(['role' => 'tutor']);
        Storage::fake('local');
        $profile = $this->readyProfile($tutor);
        $profile->update(['status' => 'submitted', 'submitted_at' => now(), 'assigned_admin_id' => $admin->id]);
        $profile->verificationDocuments()->update(['status' => 'approved']);
        DB::listen(function ($query): void {
            if (str_contains($query->sql, 'insert into "audit_logs"')) {
                throw new RuntimeException('Injected audit write failure.');
            }
        });
        $this->actingAs($admin)->postJson("/api/v1/admin/tutor-profiles/{$profile->id}/approve", ['reason' => 'Must roll back.', 'assignment_version' => 0])->assertStatus(500);
        $this->assertSame('submitted', $profile->refresh()->status);
        $this->assertNull($profile->reviewed_at);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_owner_verification_link_is_idempotent_and_locked_after_submit(): void
    {
        Storage::fake('local');
        $tutor = User::factory()->create(['role' => 'tutor']);
        $profile = $this->readyProfile($tutor);
        $document = $profile->verificationDocuments()->firstOrFail();
        $payload = ['resource_type' => 'verification_document', 'resource_id' => $document->id];
        $this->actingAs($tutor)->postJson("/api/v1/files/{$document->file_id}/links", $payload)->assertOk();
        $this->actingAs($tutor)->postJson("/api/v1/files/{$document->file_id}/links", $payload)->assertOk();
        $this->assertDatabaseCount('file_links', 1);
        $this->actingAs($tutor)->postJson('/api/v1/tutor/profile/submit')->assertOk();
        $this->actingAs($tutor)->postJson("/api/v1/files/{$document->file_id}/links", $payload)->assertConflict();
        $this->assertDatabaseCount('file_links', 1);
    }

    public function test_admin_seed_cannot_change_a_customer_role_or_password(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $passwordHash = $customer->password_hash;
        config()->set('auth.local_admin.email', $customer->email);
        config()->set('auth.local_admin.password', 'SyntheticPassword123!');
        try {
            $this->seed(AdminSeeder::class);
            $this->fail('A customer must not be promoted by the admin seeder.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('non-admin account', $error->getMessage());
        }
        $this->assertSame('customer', $customer->refresh()->role);
        $this->assertSame($passwordHash, $customer->password_hash);
    }

    public function test_customer_cannot_access_tutor_onboarding(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer)->getJson('/api/v1/tutor/profile')->assertForbidden();
        $this->actingAs($customer)->postJson('/api/v1/tutor/profile/submit')->assertForbidden();
    }

    public function test_logout_clears_web_session_after_sanctum_authentication(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'web')->withHeader('Origin', 'http://localhost:3000')
            ->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->assertGuest('web');
    }

    private function readyProfile(User $tutor): TutorProfile
    {
        $this->seed(CatalogSeeder::class);
        $profile = $tutor->tutorProfile()->create([
            'headline' => 'English tutor',
            'bio' => 'Experienced tutor.',
            'status' => 'draft',
            'avatar_file_id' => $this->file($tutor, 'avatar')->id,
        ]);
        $profile->specializations()->attach(Specialization::query()->value('id'));
        $profile->verificationDocuments()->create(['file_id' => $this->file($tutor, 'verification_document')->id, 'document_type' => 'identity_card']);

        return $profile;
    }

    private function file(User $owner, string $purpose): File
    {
        $file = File::create([
            'owner_id' => $owner->id,
            'disk' => 'local',
            'object_key' => 'proof/'.str()->uuid(),
            'original_name' => 'fixture.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 5,
            'visibility' => 'private',
            'scan_status' => 'clean',
            'purpose' => $purpose,
        ]);
        Storage::disk('local')->put($file->object_key, 'proof');

        return $file;
    }
}
