<?php

use App\Actions\Accounts\ChangeAccountStatus;
use App\Actions\Reports\AssignReport;
use App\Actions\Reports\DecideReport;
use App\Actions\TutorProfiles\AssignTutorProfile;
use App\Actions\TutorProfiles\DecideTutorProfile;
use App\Actions\TutorProfiles\SuspendTutorProfile;
use App\Models\User;
use App\Support\AdminPermissions as Permissions;
use Filament\Panel;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Tests\Support\ProgramApiFixtures;
use Tests\Support\TestActors;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function sharedAdminReport(?User $assignee = null): int
{
    return DB::table('reports')->insertGetId(['reporter_id' => TestActors::customer()->id, 'resource_type' => 'program', 'resource_id' => 100, 'reason' => 'other', 'details' => 'PRIVATE EVIDENCE', 'status' => 'open', 'assigned_admin_id' => $assignee?->id, 'assignment_version' => 0, 'created_at' => now(), 'updated_at' => now()]);
}

function sharedAdminFailure(Closure $action, int $status): void
{
    try {
        $action();
        test()->fail('The business action must deny the operation.');
    } catch (HttpResponseException $exception) {
        expect($exception->getResponse()->getStatusCode())->toBe($status);
    }
}

it('enforces fresh role permission assignment and versions in direct Report calls and HTTP', function () {
    $admin = TestActors::admin();
    $other = TestActors::admin();
    $id = sharedAdminReport();
    $data = ['assigned_admin_id' => $admin->id, 'assignment_version' => 0, 'reason' => 'Triage'];
    $result = app(AssignReport::class)->execute($admin, $id, $data);
    expect($result)->toBe(['id' => $id, 'assigned_admin_id' => $admin->id, 'assignment_version' => 1]);
    sharedAdminFailure(fn () => app(AssignReport::class)->execute($admin, $id, $data), 409);
    sharedAdminFailure(fn () => app(DecideReport::class)->execute($other, $id, 'resolved', ['assignment_version' => 1, 'resolution_note' => 'No access']), 404);
    $this->actingAs($other)->postJson("/api/v1/admin/reports/{$id}/resolve", ['assignment_version' => 1, 'resolution_note' => 'No access'])->assertNotFound();
    $admin->forceFill(['permissions' => []])->save();
    sharedAdminFailure(fn () => app(AssignReport::class)->execute($admin, $id, $data), 403);
    $this->actingAs($admin)->putJson("/api/v1/admin/reports/{$id}/assignment", $data)->assertForbidden();
    $this->assertDatabaseHas('reports', ['id' => $id, 'status' => 'open', 'assignment_version' => 1]);
    $this->assertDatabaseCount('audit_logs', 1);
    $this->assertDatabaseCount('notification_outbox', 0);
});

it('uses the same transactional Report audit and outbox from direct and HTTP decisions', function () {
    $admin = TestActors::admin();
    $first = sharedAdminReport($admin);
    $second = sharedAdminReport($admin);
    $data = ['assignment_version' => 0, 'resolution_note' => 'Reviewed'];
    $result = app(DecideReport::class)->execute($admin, $first, 'resolved', $data);
    expect($result->status)->toBe('resolved');
    $this->actingAs($admin)->postJson("/api/v1/admin/reports/{$second}/resolve", $data)->assertOk()->assertJsonPath('data.status', 'resolved');
    expect(DB::table('audit_logs')->where('action', 'report.resolve')->count())->toBe(2);
    expect(DB::table('notification_outbox')->where('type', 'report_resolved')->count())->toBe(2);
    sharedAdminFailure(fn () => app(DecideReport::class)->execute($admin, $first, 'dismissed', $data), 409);
    $this->assertDatabaseCount('notification_outbox', 2);
});

it('rolls back direct Report changes and notifications if the audit write fails', function () {
    $admin = TestActors::admin();
    $id = sharedAdminReport($admin);
    DB::unprepared("CREATE OR REPLACE FUNCTION reject_h05_audit() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN RAISE EXCEPTION 'test audit unavailable'; END $$");
    DB::unprepared('CREATE TRIGGER reject_h05_audit BEFORE INSERT ON audit_logs FOR EACH ROW EXECUTE FUNCTION reject_h05_audit()');
    try {
        try {
            app(DecideReport::class)->execute($admin, $id, 'dismissed', ['assignment_version' => 0, 'resolution_note' => 'Reviewed']);
            $this->fail('Audit failure must roll back the business transaction.');
        } catch (QueryException $exception) {
            expect($exception->getMessage())->toContain('test audit unavailable');
        }
        $this->assertDatabaseHas('reports', ['id' => $id, 'status' => 'open', 'handled_at' => null]);
        $this->assertDatabaseCount('notification_outbox', 0);
    } finally {
        DB::unprepared('DROP TRIGGER reject_h05_audit ON audit_logs');
        DB::unprepared('DROP FUNCTION reject_h05_audit()');
    }
});

it('reuses profile approval assignment and suspension actions with current permission checks', function () {
    $admin = TestActors::admin();
    $tutor = ProgramApiFixtures::eligibleTutor();
    $profile = $tutor->tutorProfile;
    $profile->update(['status' => 'submitted']);
    app(AssignTutorProfile::class)->execute($admin, $profile->id, ['assigned_admin_id' => $admin->id, 'assignment_version' => 0, 'reason' => 'Assigned']);
    app(DecideTutorProfile::class)->execute($admin, $profile->id, 'changes_requested', ['assignment_version' => 1, 'reason' => 'Please revise']);
    $this->assertDatabaseHas('tutor_profiles', ['id' => $profile->id, 'status' => 'changes_requested']);
    $this->assertDatabaseHas('audit_logs', ['action' => 'tutor_profile_changes_requested', 'resource_id' => $profile->id]);
    app(SuspendTutorProfile::class)->execute($admin, $profile->id, true, 'Investigate');
    $this->assertDatabaseHas('users', ['id' => $tutor->id, 'status' => 'suspended']);
    $admin->forceFill(['permissions' => []])->save();
    sharedAdminFailure(fn () => app(SuspendTutorProfile::class)->execute($admin, $profile->id, false, 'Restore'), 403);
    $this->actingAs($admin)->postJson("/api/v1/admin/tutor-profiles/{$profile->id}/restore", ['reason' => 'Restore'])->assertForbidden();
});

it('protects direct account suspension revokes tokens and applies the same HTTP policy', function () {
    $admin = TestActors::admin();
    $target = TestActors::customer();
    $target->createToken('old token', ['my-listings:read']);
    app(ChangeAccountStatus::class)->execute($admin, $target->id, true, 'Investigate');
    $this->assertDatabaseHas('users', ['id' => $target->id, 'status' => 'suspended']);
    expect($target->tokens()->count())->toBe(0);
    $this->assertDatabaseHas('audit_logs', ['action' => 'account_suspended', 'resource_id' => $target->id]);
    $this->actingAs($admin)->postJson("/api/v1/admin/users/{$target->id}/restore", ['reason' => 'Reviewed'])->assertOk()->assertJsonPath('data.status', 'active');
    sharedAdminFailure(fn () => app(ChangeAccountStatus::class)->execute($admin, $admin->id, true, 'Self'), 422);
    $stale = $admin->fresh();
    $admin->forceFill(['email_verified_at' => null])->save();
    sharedAdminFailure(fn () => app(ChangeAccountStatus::class)->execute($stale, $target->id, true, 'Unverified'), 403);
    $this->assertDatabaseCount('audit_logs', 2);
});

it('returns only permitted dashboard counts scoped to current assignments and never evidence or identities', function () {
    $this->getJson('/api/v1/admin/dashboard')->assertUnauthorized();
    $admin = TestActors::admin([Permissions::VIEW_DASHBOARD, Permissions::VIEW_REPORTS]);
    sharedAdminReport($admin);
    sharedAdminReport(TestActors::admin());
    sharedAdminReport();
    $response = $this->actingAs($admin)->getJson('/api/v1/admin/dashboard')->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.resources.reports.mine.open', 1)->assertJsonMissingPath('data.resources.reports.unassigned')->assertJsonMissingPath('data.resources.tutor_profiles');
    expect($response->getContent())->not->toContain('PRIVATE EVIDENCE', $admin->email, 'reporter_id');
    $this->actingAs(TestActors::admin())->getJson('/api/v1/admin/dashboard')->assertOk()->assertJsonPath('data.resources.reports.unassigned.open', 1)->assertJsonPath('data.resources.notification_outbox.pending', 0);
    $this->actingAs(TestActors::customer())->getJson('/api/v1/admin/dashboard')->assertForbidden();
    $admin->forceFill(['permissions' => []])->save();
    $this->actingAs($admin)->getJson('/api/v1/admin/dashboard')->assertForbidden();
});

it('keeps Filament panels closed until Gate BE and requires fresh verified Admin permission', function () {
    $admin = TestActors::admin();
    $panel = Panel::make()->id('admin');
    expect($admin->canAccessPanel($panel))->toBeFalse();
    config(['admin.panel_enabled' => true]);
    expect($admin->canAccessPanel($panel))->toBeTrue();
    expect(TestActors::customer()->canAccessPanel($panel))->toBeFalse();
    $admin->forceFill(['permissions' => []])->save();
    expect($admin->canAccessPanel($panel))->toBeFalse();
    config(['admin.panel_enabled' => false]);
    $this->get('/admin')->assertNotFound();
    $this->get('/admin/login')->assertNotFound();
});
