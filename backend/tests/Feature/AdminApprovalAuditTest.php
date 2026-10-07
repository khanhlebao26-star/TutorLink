<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\Specialization;
use App\Models\TutorProfile;
use App\Models\User;
use App\Support\AdminPermissions;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminApprovalAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_changes_resubmit_and_approve_records_every_decision(): void
    {
        $admin = User::factory()->admin()->create();
        $tutor = User::factory()->create(['role' => 'tutor']);
        $profile = $this->createSubmittedProfile($tutor);
        $this->seed(CatalogSeeder::class);
        $specializationId = Specialization::query()->value('id');

        $this->actingAs($admin)->postJson("/api/v1/admin/tutor-profiles/{$profile->id}/request-changes", [
            'reason' => 'Bổ sung chứng chỉ giảng dạy.',
        ])->assertOk()->assertJsonPath('data.profile.status', 'changes_requested');

        $this->actingAs($tutor)->putJson('/api/v1/tutor/profile', [
            'headline' => 'English tutor updated',
            'bio' => 'Updated profile.',
            'experience_years' => 6,
            'specialization_id' => $specializationId,
        ])->assertOk()->assertJsonPath('data.status', 'changes_requested');

        $this->actingAs($tutor)->postJson('/api/v1/tutor/profile/submit')
            ->assertOk()
            ->assertJsonPath('data.approval_status', 'pending');

        $this->actingAs($admin)->postJson("/api/v1/admin/tutor-profiles/{$profile->id}/approve", [
            'reason' => 'Đã kiểm tra và hồ sơ hợp lệ.',
        ])->assertOk()->assertJsonPath('data.profile.status', 'approved');

        $this->assertDatabaseHas('tutor_profiles', [
            'id' => $profile->id,
            'approval_status' => 'active',
            'reviewed_by' => $admin->id,
            'review_reason' => 'Đã kiểm tra và hồ sơ hợp lệ.',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'tutor_profile_changes_requested',
            'resource_type' => 'tutor_profile',
            'resource_id' => $profile->id,
            'reason' => 'Bổ sung chứng chỉ giảng dạy.',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => 'tutor_profile_approved',
            'resource_type' => 'tutor_profile',
            'resource_id' => $profile->id,
            'reason' => 'Đã kiểm tra và hồ sơ hợp lệ.',
        ]);
        $this->assertSame(2, DB::table('audit_logs')->where('resource_id', $profile->id)->count());
    }

    public function test_approval_decisions_require_a_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $tutor = User::factory()->create(['role' => 'tutor']);
        $profile = $this->createSubmittedProfile($tutor);

        foreach (['approve', 'request-changes', 'reject'] as $action) {
            $this->actingAs($admin)
                ->postJson("/api/v1/admin/tutor-profiles/{$profile->id}/{$action}", [])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('reason');
        }
    }

    public function test_admin_without_permission_and_non_admin_cannot_review_profiles(): void
    {
        $adminWithoutPermission = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);
        $tutor = User::factory()->create(['role' => 'tutor']);
        $profile = $this->createSubmittedProfile($tutor);

        $this->actingAs($adminWithoutPermission)
            ->getJson('/api/v1/admin/tutor-profiles')
            ->assertForbidden();
        $this->actingAs($customer)
            ->getJson('/api/v1/admin/tutor-profiles')
            ->assertForbidden();
        $this->actingAs($adminWithoutPermission)
            ->postJson("/api/v1/admin/tutor-profiles/{$profile->id}/approve", ['reason' => 'Không có quyền.'])
            ->assertForbidden();
    }

    public function test_only_reviewer_with_permission_can_download_verification_document(): void
    {
        Storage::fake('local');
        $admin = User::factory()->admin()->create();
        $adminWithoutPermission = User::factory()->create(['role' => 'admin']);
        $profileViewer = User::factory()->create([
            'role' => 'admin',
            'permissions' => [AdminPermissions::VIEW_TUTOR_PROFILES],
        ]);
        $tutor = User::factory()->create(['role' => 'tutor']);
        $other = User::factory()->create(['role' => 'customer']);
        $profile = $this->createSubmittedProfile($tutor);
        $file = File::create([
            'owner_id' => $tutor->id,
            'disk' => 'local',
            'object_key' => 'uploads/'.$tutor->id.'/certificate.pdf',
            'original_name' => 'certificate.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 7,
            'visibility' => 'private',
            'scan_status' => 'clean',
            'purpose' => 'verification_document',
        ]);
        Storage::disk('local')->put($file->object_key, 'proof');
        DB::table('verification_documents')->insert([
            'tutor_profile_id' => $profile->id,
            'file_id' => $file->id,
            'document_type' => 'certificate',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($profileViewer)
            ->getJson("/api/v1/admin/tutor-profiles/{$profile->id}")
            ->assertOk()
            ->assertJsonPath('data.verification_documents', null);
        $this->actingAs($admin)
            ->getJson("/api/v1/admin/tutor-profiles/{$profile->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data.verification_documents');
        $this->actingAs($other)->getJson("/api/v1/files/{$file->id}/download")->assertForbidden();
        $this->actingAs($adminWithoutPermission)->getJson("/api/v1/files/{$file->id}/download")->assertForbidden();
        $this->actingAs($admin)->get("/api/v1/files/{$file->id}/download")->assertOk();
    }

    public function test_account_and_profile_suspension_are_separate_and_audited(): void
    {
        $admin = User::factory()->admin()->create();
        $tutor = User::factory()->create(['role' => 'tutor']);
        $profile = $this->createSubmittedProfile($tutor);

        $this->actingAs($admin)->postJson("/api/v1/admin/tutor-profiles/{$profile->id}/suspend", [
            'reason' => 'Tạm dừng kiểm tra hồ sơ.',
        ])->assertOk();
        $this->assertDatabaseHas('users', ['id' => $tutor->id, 'status' => 'active']);
        $this->assertNotNull($profile->refresh()->suspended_at);

        $this->actingAs($tutor->refresh())->postJson('/api/v1/tutor/profile/submit')->assertForbidden();

        $this->actingAs($admin)->postJson("/api/v1/admin/tutor-profiles/{$profile->id}/restore", [
            'reason' => 'Đã hoàn tất kiểm tra hồ sơ.',
        ])->assertOk();
        $this->assertNull($profile->refresh()->suspended_at);

        $this->actingAs($admin)->postJson("/api/v1/admin/users/{$tutor->id}/suspend", [
            'reason' => 'Tạm dừng tài khoản theo chính sách.',
        ])->assertOk();
        $this->assertDatabaseHas('users', ['id' => $tutor->id, 'status' => 'suspended']);
        $this->actingAs($tutor->refresh())->postJson('/api/v1/tutor/profile/submit')->assertForbidden();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'tutor_profile_suspended',
            'resource_type' => 'tutor_profile',
            'resource_id' => $profile->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'account_suspended',
            'resource_type' => 'user',
            'resource_id' => $tutor->id,
        ]);
    }

    private function createSubmittedProfile(User $tutor): TutorProfile
    {
        return $tutor->tutorProfile()->create([
            'headline' => 'English tutor',
            'bio' => 'Experienced tutor.',
            'experience_years' => 5,
            'approval_status' => 'pending',
            'submitted_at' => now(),
        ]);
    }
}
