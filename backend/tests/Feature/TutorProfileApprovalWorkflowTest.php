<?php

use App\Actions\TutorProfiles\AssignTutorProfile;
use App\Actions\TutorProfiles\DecideTutorProfile;
use App\Actions\TutorProfiles\SuspendTutorProfile;
use App\Models\File;
use App\Models\Specialization;
use App\Models\User;
use App\Support\AdminPermissions;
use App\Support\NotificationOutboxWriter;
use App\Support\TutorEligibility;
use Carbon\CarbonImmutable;
use Database\Seeders\CatalogSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->seed(CatalogSeeder::class);
    $this->admin = User::factory()->admin()->create();
    $this->otherAdmin = User::factory()->admin()->create();
    $this->tutor = User::factory()->create(['role' => 'tutor']);
    $this->profile = $this->tutor->tutorProfile()->create([
        'headline' => 'English Tutor', 'bio' => 'Experienced teacher', 'status' => 'draft',
        'assigned_admin_id' => $this->admin->id, 'assignment_version' => 1,
    ]);
    $this->profile->specializations()->sync([Specialization::query()->value('id')]);
    foreach (['avatar', 'verification_document'] as $purpose) {
        $file = File::create([
            'owner_id' => $this->tutor->id, 'disk' => 'local', 'object_key' => 'h02/'.$purpose,
            'original_name' => 'proof.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 5,
            'visibility' => 'private', 'scan_status' => 'clean', 'purpose' => $purpose,
        ]);
        Storage::disk('local')->put($file->object_key, 'proof');
        if ($purpose === 'avatar') {
            $this->profile->update(['avatar_file_id' => $file->id]);
        } else {
            $this->document = $this->profile->verificationDocuments()->create([
                'file_id' => $file->id, 'document_type' => 'identity_card', 'status' => 'pending',
            ]);
        }
    }
});

it('assigns reassigns and revokes with optimistic locking and a minimal response', function () {
    $url = "/api/v1/admin/tutor-profiles/{$this->profile->id}/assignment";
    $this->actingAs($this->admin)->putJson($url, [
        'assigned_admin_id' => $this->otherAdmin->id, 'assignment_version' => 1, 'reason' => 'Reviewer rotation',
    ])->assertOk()->assertJsonPath('data.assignment_version', 2)->assertJsonMissingPath('data.profile');
    $this->putJson($url, ['assigned_admin_id' => null, 'assignment_version' => 1, 'reason' => 'Stale request'])
        ->assertConflict()->assertJsonPath('code', 'stale_assignment_version');
    $this->assertDatabaseCount('audit_logs', 1);
    $this->putJson($url, ['assigned_admin_id' => null, 'assignment_version' => 2, 'reason' => 'Return to queue'])
        ->assertOk()->assertJsonPath('data.assigned_admin_id', null)->assertJsonPath('data.assignment_version', 3);
    $this->assertDatabaseCount('audit_logs', 2);
});

it('rejects invalid assignees and callers without assignment permission', function () {
    $url = "/api/v1/admin/tutor-profiles/{$this->profile->id}/assignment";
    foreach ([$this->tutor, User::factory()->admin()->create(['status' => 'suspended'])] as $target) {
        $this->actingAs($this->admin)->putJson($url, ['assigned_admin_id' => $target->id, 'assignment_version' => 1, 'reason' => 'Invalid reviewer'])
            ->assertUnprocessable();
    }
    $viewer = User::factory()->create(['role' => 'admin', 'permissions' => [AdminPermissions::VIEW_TUTOR_PROFILES]]);
    $this->actingAs($viewer)->putJson($url, ['assigned_admin_id' => $this->admin->id, 'assignment_version' => 1, 'reason' => 'Cannot assign'])
        ->assertForbidden();
    $this->assertDatabaseCount('audit_logs', 0);
});

it('removes the old reviewer scope for detail decisions and private downloads immediately', function () {
    $this->profile->update(['status' => 'submitted', 'assigned_admin_id' => $this->otherAdmin->id, 'assignment_version' => 2]);
    $this->actingAs($this->admin)->getJson("/api/v1/admin/tutor-profiles/{$this->profile->id}")->assertNotFound();
    $this->postJson("/api/v1/admin/tutor-profiles/{$this->profile->id}/reject", ['reason' => 'Old reviewer', 'assignment_version' => 1])->assertNotFound();
    $this->getJson("/api/v1/files/{$this->document->file_id}/download")->assertForbidden();
    $this->actingAs($this->otherAdmin)->get("/api/v1/files/{$this->document->file_id}/download")->assertOk();
    $this->assertDatabaseHas('audit_logs', ['action' => 'verification_document.download', 'actor_id' => $this->otherAdmin->id]);
});

it('keeps assignment-only queue metadata separate from profile details and scoped counts', function () {
    $this->profile->update(['status' => 'submitted']);
    $coordinator = User::factory()->create(['role' => 'admin', 'permissions' => [AdminPermissions::ASSIGN_TUTOR_PROFILES]]);
    $this->actingAs($coordinator)->getJson('/api/v1/admin/tutor-profiles?scope=all')
        ->assertOk()->assertJsonPath('meta.total', 1)->assertJsonMissingPath('data.0.profile')->assertJsonMissingPath('data.0.user');
    $this->getJson("/api/v1/admin/tutor-profiles/{$this->profile->id}")->assertNotFound();
    $this->actingAs($this->otherAdmin)->getJson('/api/v1/admin/tutor-profiles')->assertOk()->assertJsonPath('meta.total', 0);
});

it('requires identity and structured qualification evidence before submit', function () {
    $this->document->update(['document_type' => 'other']);
    $this->actingAs($this->tutor)->postJson('/api/v1/tutor/profile/submit')->assertUnprocessable()->assertJsonValidationErrors('verification_document_file_id');
    $this->document->update(['document_type' => 'identity_card']);
    $this->profile->update(['qualification_claims' => [['type' => 'degree', 'name' => 'Teaching degree']]]);
    $this->postJson('/api/v1/tutor/profile/submit')->assertUnprocessable()->assertJsonValidationErrors('qualification_claims');
    $this->assertSame('draft', $this->profile->refresh()->status);
    $this->assertDatabaseCount('audit_logs', 0);
});

it('submits once retains eligible assignments and requires separate document approval', function () {
    $this->actingAs($this->tutor)->postJson('/api/v1/tutor/profile/submit')->assertOk()->assertJsonPath('data.status', 'submitted');
    $this->postJson('/api/v1/tutor/profile/submit')->assertOk();
    $this->assertDatabaseCount('audit_logs', 1);
    $this->assertSame($this->admin->id, $this->profile->refresh()->assigned_admin_id);
    $url = "/api/v1/admin/tutor-profiles/{$this->profile->id}/approve";
    $data = ['reason' => 'Review complete', 'assignment_version' => 1];
    $this->actingAs($this->admin)->postJson($url, $data)->assertUnprocessable();
    $this->postJson("/api/v1/admin/verification-documents/{$this->document->id}/approve", $data)->assertOk()->assertJsonPath('data.status', 'approved');
    $this->postJson($url, $data)->assertOk()->assertJsonPath('data.profile.status', 'approved');
    $this->assertDatabaseCount('notification_outbox', 1);
});

it('returns a stale conflict even if a profile is reassigned back to the same reviewer', function () {
    $this->profile->update(['status' => 'submitted', 'assignment_version' => 3]);
    $this->actingAs($this->admin)->postJson("/api/v1/admin/tutor-profiles/{$this->profile->id}/reject", [
        'reason' => 'Decision from old page', 'assignment_version' => 1,
    ])->assertConflict();
    $this->assertDatabaseCount('audit_logs', 0);
});

it('clears an ineligible reviewer on resubmission and forbids rejected submissions', function () {
    $this->profile->update(['status' => 'changes_requested']);
    $this->admin->update(['permissions' => []]);
    $this->actingAs($this->tutor)->postJson('/api/v1/tutor/profile/submit')->assertOk();
    $this->assertNull($this->profile->refresh()->assigned_admin_id);
    $this->assertSame(2, $this->profile->assignment_version);
    $this->profile->update(['status' => 'rejected']);
    $this->postJson('/api/v1/tutor/profile/submit')->assertConflict();
});

it('checks document decision permission separately from download and refuses expired documents', function () {
    $this->document->update(['expires_at' => now()->subMinute()]);
    $data = ['reason' => 'Review document', 'assignment_version' => 1];
    $this->actingAs($this->admin)->postJson("/api/v1/admin/verification-documents/{$this->document->id}/approve", $data)->assertConflict();
    $this->admin->update(['permissions' => [AdminPermissions::DOWNLOAD_VERIFICATION_DOCUMENTS]]);
    $this->actingAs($this->admin->refresh())->postJson("/api/v1/admin/verification-documents/{$this->document->id}/reject", $data)->assertForbidden();
    $this->assertDatabaseCount('audit_logs', 0);
});

it('suspends profile and active account atomically then restores only the saved profile state', function () {
    $this->profile->update(['status' => 'changes_requested', 'assigned_admin_id' => $this->otherAdmin->id]);
    $this->actingAs($this->admin)->postJson("/api/v1/admin/tutor-profiles/{$this->profile->id}/suspend", ['reason' => 'Safety review'])->assertOk();
    $this->assertSame('suspended', $this->tutor->refresh()->status);
    $this->assertSame('changes_requested', $this->profile->refresh()->status_before_suspension);
    $this->assertDatabaseCount('audit_logs', 2);
    $this->postJson("/api/v1/admin/tutor-profiles/{$this->profile->id}/restore", ['reason' => 'Review complete'])->assertOk();
    $this->assertSame('changes_requested', $this->profile->refresh()->status);
    $this->assertSame('suspended', $this->tutor->refresh()->status);
});

it('cannot partially suspend with either required permission missing', function (string $permission) {
    $this->admin->update(['permissions' => [$permission]]);
    $this->actingAs($this->admin)->postJson("/api/v1/admin/tutor-profiles/{$this->profile->id}/suspend", ['reason' => 'Safety review'])->assertForbidden();
    $this->assertSame('draft', $this->profile->refresh()->status);
    $this->assertSame('active', $this->tutor->refresh()->status);
    $this->assertDatabaseCount('audit_logs', 0);
    $this->assertDatabaseCount('notification_outbox', 0);
})->with([AdminPermissions::SUSPEND_TUTOR_PROFILES, AdminPermissions::SUSPEND_ACCOUNTS]);

it('rolls back account profile audit and token revocation if the outbox write fails', function () {
    $token = $this->tutor->createToken('fixture')->accessToken;
    $this->instance(NotificationOutboxWriter::class, new class
    {
        public function record(mixed ...$arguments): never
        {
            throw new RuntimeException('outbox failed');
        }
    });
    try {
        app(SuspendTutorProfile::class)->execute($this->admin, $this->profile->id, true, 'Safety review');
        $this->fail('The outbox failure should propagate.');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('outbox failed');
    }
    $this->assertSame('draft', $this->profile->refresh()->status);
    $this->assertSame('active', $this->tutor->refresh()->status);
    $this->assertDatabaseHas('personal_access_tokens', ['id' => $token->id]);
    $this->assertDatabaseCount('audit_logs', 0);
});

it('enforces authorization when business actions are called directly with a stale actor object', function () {
    User::whereKey($this->admin->id)->update(['permissions' => '[]']);
    expect(fn () => app(AssignTutorProfile::class)->execute($this->admin, $this->profile->id, [
        'assigned_admin_id' => $this->otherAdmin->id, 'assignment_version' => 1, 'reason' => 'Forbidden direct call',
    ]))->toThrow(HttpResponseException::class);
    $this->profile->update(['status' => 'submitted']);
    expect(fn () => app(DecideTutorProfile::class)->execute($this->admin, $this->profile->id, 'rejected', [
        'assignment_version' => 1, 'reason' => 'Forbidden direct decision',
    ]))->toThrow(HttpResponseException::class);
    $this->assertDatabaseCount('audit_logs', 0);
});

it('keeps approved content unchanged until a versioned revision is approved', function () {
    $this->profile->update(['status' => 'approved']);
    $this->document->update(['status' => 'approved']);
    $data = ['headline' => 'Updated headline', 'bio' => 'Updated biography', 'specialization_id' => Specialization::query()->value('id')];
    $this->actingAs($this->tutor)->putJson('/api/v1/tutor/profile', $data)->assertOk()->assertJsonPath('data.headline', 'English Tutor')
        ->assertJsonPath('data.revision.payload.headline', 'Updated headline');
    $this->putJson('/api/v1/tutor/profile', $data)->assertConflict();
    $this->postJson('/api/v1/tutor/profile/submit')->assertOk()->assertJsonPath('data.status', 'approved')
        ->assertJsonPath('data.revision.status', 'submitted');
    $this->actingAs($this->admin)->postJson("/api/v1/admin/tutor-profiles/{$this->profile->id}/approve", [
        'reason' => 'Revision accepted', 'assignment_version' => 1, 'expected_version' => 1,
    ])->assertConflict();
    $this->postJson("/api/v1/admin/tutor-profiles/{$this->profile->id}/approve", [
        'reason' => 'Revision accepted', 'assignment_version' => 1, 'expected_version' => 2,
    ])->assertOk()->assertJsonPath('data.profile.headline', 'Updated headline')->assertJsonPath('data.profile.revision.status', 'approved');
});

it('preserves approved content on revision rejection and does not reopen the rejected revision', function () {
    $this->profile->update(['status' => 'approved']);
    $this->document->update(['status' => 'approved']);
    $data = ['headline' => 'Rejected edit', 'bio' => 'New bio', 'specialization_id' => Specialization::query()->value('id')];
    $this->actingAs($this->tutor)->putJson('/api/v1/tutor/profile', $data)->assertOk();
    $this->postJson('/api/v1/tutor/profile/submit')->assertOk();
    $this->actingAs($this->admin)->postJson("/api/v1/admin/tutor-profiles/{$this->profile->id}/reject", [
        'reason' => 'Not accepted', 'assignment_version' => 1, 'expected_version' => 2,
    ])->assertOk();
    $this->assertSame('English Tutor', $this->profile->refresh()->headline);
    $this->actingAs($this->tutor)->putJson('/api/v1/tutor/profile', $data)->assertConflict();
    $this->postJson('/api/v1/tutor/profile/submit')->assertConflict();
});

it('supports changes requested revisions with versioned edits and retains the public profile', function () {
    $this->profile->update(['status' => 'approved']);
    $this->document->update(['status' => 'approved']);
    $data = ['headline' => 'Needs review', 'bio' => 'Revised bio', 'specialization_id' => Specialization::query()->value('id')];
    $this->actingAs($this->tutor)->putJson('/api/v1/tutor/profile', $data)->assertOk();
    $this->postJson('/api/v1/tutor/profile/submit')->assertOk();
    $this->actingAs($this->admin)->postJson("/api/v1/admin/tutor-profiles/{$this->profile->id}/request-changes", [
        'reason' => 'Please clarify qualifications', 'assignment_version' => 1, 'expected_version' => 2,
    ])->assertOk()->assertJsonPath('data.profile.status', 'approved')
        ->assertJsonPath('data.profile.revision.status', 'changes_requested');
    $this->actingAs($this->tutor)->putJson('/api/v1/tutor/profile', [...$data, 'headline' => 'Clarified revision', 'expected_version' => 2])->assertConflict();
    $this->putJson('/api/v1/tutor/profile', [...$data, 'headline' => 'Clarified revision', 'expected_version' => 3])->assertOk();
    $this->postJson('/api/v1/tutor/profile/submit')->assertOk()->assertJsonPath('data.revision.version', 5);
    $this->assertSame('English Tutor', $this->profile->refresh()->headline);
});

it('retains document decisions when the owner retries attaching the same file with another type', function () {
    $this->document->update(['status' => 'approved']);
    $this->actingAs($this->tutor)->postJson('/api/v1/tutor/profile/verification-documents', [
        'file_id' => $this->document->file_id, 'document_type' => 'degree',
    ])->assertOk()->assertJsonPath('data.document_type', 'identity_card');
    $this->assertSame('approved', $this->document->refresh()->status);
    $this->assertDatabaseCount('verification_documents', 1);
});

it('protects revision avatar files from deletion while keeping the original avatar public', function () {
    $this->profile->update(['status' => 'approved']);
    $originalAvatar = $this->profile->avatar_file_id;
    $file = File::create([
        'owner_id' => $this->tutor->id, 'disk' => 'local', 'object_key' => 'h02/new-avatar',
        'original_name' => 'avatar.png', 'mime_type' => 'image/png', 'size_bytes' => 5,
        'visibility' => 'private', 'scan_status' => 'clean', 'purpose' => 'avatar',
    ]);
    Storage::disk('local')->put($file->object_key, 'proof');
    $this->actingAs($this->tutor)->putJson('/api/v1/tutor/profile/avatar', ['file_id' => $file->id])
        ->assertOk()->assertJsonPath('data.avatar_file_id', $originalAvatar)->assertJsonPath('data.revision.payload.avatar_file_id', $file->id);
    $this->deleteJson("/api/v1/files/{$file->id}")->assertConflict();
});

it('enforces unique open revisions and positive revision versions in PostgreSQL', function () {
    $insert = ['tutor_profile_id' => $this->profile->id, 'revision_no' => 1, 'status' => 'draft', 'payload' => '{}', 'version' => 1];
    DB::table('tutor_profile_revisions')->insert($insert);
    expect(fn () => DB::transaction(fn () => DB::table('tutor_profile_revisions')->insert([...$insert, 'revision_no' => 2])))
        ->toThrow(QueryException::class);
    $this->assertDatabaseCount('tutor_profile_revisions', 1);
    if (DB::getDriverName() === 'pgsql') {
        expect(fn () => DB::transaction(fn () => DB::table('tutor_profile_revisions')->update(['version' => 0])))
            ->toThrow(QueryException::class);
    }
});

it('requires private identity files and matching ownership before submit', function () {
    $this->document->file->update(['visibility' => 'public']);
    $this->actingAs($this->tutor)->postJson('/api/v1/tutor/profile/submit')->assertUnprocessable();
    $this->document->file->update(['visibility' => 'private', 'owner_id' => $this->otherAdmin->id]);
    $this->postJson('/api/v1/tutor/profile/submit')->assertUnprocessable();
    $this->assertDatabaseCount('audit_logs', 0);
});

it('does not grant Program eligibility when a claimed qualification has expired', function () {
    $this->profile->update(['status' => 'approved', 'qualification_claims' => [['type' => 'degree', 'name' => 'Teaching']]]);
    $this->document->update(['status' => 'approved']);
    expect(app(TutorEligibility::class)->canManagePrograms($this->tutor, $this->profile, CarbonImmutable::now('UTC')))->toBeFalse();
    $file = $this->document->file->replicate();
    $file->object_key = 'h02/degree';
    $file->save();
    $degree = $this->profile->verificationDocuments()->create([
        'file_id' => $file->id, 'document_type' => 'degree', 'status' => 'approved', 'expires_at' => now()->addDay(),
    ]);
    expect(app(TutorEligibility::class)->canManagePrograms($this->tutor, $this->profile, CarbonImmutable::now('UTC')))->toBeTrue();
    $degree->update(['expires_at' => now()->subMinute()]);
    expect(app(TutorEligibility::class)->canManagePrograms($this->tutor, $this->profile, CarbonImmutable::now('UTC')))->toBeFalse();
    expect($this->tutor->refresh()->status)->toBe('active');
    expect($this->profile->refresh()->status)->toBe('approved');
});

it('backfills the legacy suspension state and preserves profile and document data', function () {
    $migration = require database_path('migrations/2026_10_27_074546_add_h02_approval_support.php');
    $migration->down();
    DB::table('tutor_profiles')->where('id', $this->profile->id)->update(['status' => 'suspended', 'suspended_at' => now()]);
    $migration->up();
    $this->assertDatabaseHas('tutor_profiles', [
        'id' => $this->profile->id, 'status' => 'suspended', 'status_before_suspension' => 'approved', 'headline' => 'English Tutor',
    ]);
    $this->assertDatabaseHas('verification_documents', ['id' => $this->document->id, 'status' => 'pending']);
    $this->assertDatabaseCount('tutor_profile_revisions', 0);
});

it('hides profiles with deleted tutor accounts without partial decisions or detail errors', function () {
    $this->profile->update(['status' => 'submitted']);
    $this->tutor->delete();
    $this->actingAs($this->admin)->getJson('/api/v1/admin/tutor-profiles')->assertOk()->assertJsonPath('meta.total', 0);
    $this->getJson("/api/v1/admin/tutor-profiles/{$this->profile->id}")->assertNotFound();
    $this->postJson("/api/v1/admin/tutor-profiles/{$this->profile->id}/reject", [
        'assignment_version' => 1, 'reason' => 'Deleted owner',
    ])->assertNotFound();
    $this->assertDatabaseCount('audit_logs', 0);
});

it('allows profile suspension and restoration with profile permission when the account is already suspended', function () {
    $this->admin->update(['permissions' => [AdminPermissions::SUSPEND_TUTOR_PROFILES]]);
    $this->tutor->update(['status' => 'suspended', 'suspended_at' => now()]);
    $this->profile->update(['status' => 'submitted', 'assigned_admin_id' => $this->otherAdmin->id]);
    $this->actingAs($this->admin)->postJson("/api/v1/admin/tutor-profiles/{$this->profile->id}/suspend", ['reason' => 'Profile-only moderation'])
        ->assertOk()->assertJsonMissingPath('data.user.email')->assertJsonMissingPath('data.profile.bio');
    $this->assertDatabaseMissing('audit_logs', ['action' => 'account_suspended']);
    $this->postJson("/api/v1/admin/tutor-profiles/{$this->profile->id}/restore", ['reason' => 'Restore only the profile'])->assertOk();
    expect($this->profile->refresh()->status)->toBe('submitted');
    expect($this->tutor->refresh()->status)->toBe('suspended');
    $this->assertDatabaseCount('audit_logs', 2);
});
