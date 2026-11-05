<?php

use App\Filament\Pages\Operations;
use App\Filament\Resources\ReportResource;
use App\Models\TutorProfile;
use App\Models\User;
use App\Support\AdminPermissions as Permissions;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\ProgramApiFixtures;
use Tests\Support\TestActors;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    config(['admin.panel_enabled' => true]);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function adminFrontendProfile(User $admin): TutorProfile
{
    $tutor = TestActors::tutor();
    $tutor->tutorProfile->forceFill(['status' => 'submitted', 'submitted_at' => now(), 'assigned_admin_id' => $admin->id, 'assignment_version' => 2, 'headline' => '<script>alert("unsafe")</script>'])->save();

    return $tutor->tutorProfile->fresh();
}

it('keeps the panel opt-in and redirects guests to its login', function () {
    $this->get('/admin/login')->assertOk();
    $this->get('/admin-business/assigned-profiles')->assertRedirect(route('filament.admin.auth.login'));
    config(['admin.panel_enabled' => false]);
    $this->get('/admin/login')->assertNotFound();
    $this->get('/admin/operations')->assertNotFound();
    $this->get('/admin-business/assigned-profiles')->assertNotFound();
});

it('denies the panel and Blade to non-admin or revoked dashboard permission', function () {
    $this->actingAs(TestActors::customer())->get('/admin/operations')->assertForbidden();
    $admin = TestActors::admin([Permissions::VIEW_TUTOR_PROFILES]);
    $this->actingAs($admin)->get('/admin-business/assigned-profiles')->assertForbidden();
    $admin = TestActors::admin();
    $this->actingAs($admin)->get('/admin/operations')->assertOk();
    User::whereKey($admin->id)->update(['permissions' => []]);
    $this->get('/admin/operations')->assertForbidden();
});

it('renders assigned Blade profiles with escaped text, filters and CSRF form', function () {
    $admin = TestActors::admin();
    $mine = adminFrontendProfile($admin);
    $other = adminFrontendProfile(TestActors::admin());
    $this->actingAs($admin)->get('/admin-business/assigned-profiles?status=submitted')->assertOk()->assertSee($mine->user->full_name)->assertDontSee($other->user->full_name);
    $response = $this->get("/admin-business/assigned-profiles/{$mine->id}")->assertOk()->assertSee('name="_token"', false)->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert("unsafe")</script>', false);
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    $this->get("/admin-business/assigned-profiles/{$other->id}")->assertNotFound();
});

it('writes a Blade decision through the shared action, audit and outbox, and rejects stale versions', function () {
    $admin = TestActors::admin();
    $profile = adminFrontendProfile($admin);
    $payload = ['operation' => 'request-changes', 'assignment_version' => 1, 'reason' => 'Please add evidence'];
    $this->actingAs($admin)->from("/admin-business/assigned-profiles/{$profile->id}")->post("/admin-business/assigned-profiles/{$profile->id}", $payload)->assertSessionHasErrors('operation');
    expect($profile->fresh()->status)->toBe('submitted');
    $payload['assignment_version'] = 2;
    $this->post("/admin-business/assigned-profiles/{$profile->id}", $payload)->assertRedirect();
    expect($profile->fresh()->status)->toBe('changes_requested');
    $this->assertDatabaseHas('audit_logs', ['resource_type' => 'tutor_profile', 'resource_id' => $profile->id, 'actor_id' => $admin->id, 'action' => 'tutor_profile_changes_requested']);
    $this->assertDatabaseHas('notification_outbox', ['resource_id' => $profile->id, 'type' => 'tutor_profile_changes_requested']);
});

it('renders Filament resources without leaking unassigned report content', function () {
    $admin = TestActors::admin();
    $id = DB::table('reports')->insertGetId(['reporter_id' => TestActors::customer()->id, 'resource_type' => 'program', 'resource_id' => 1, 'reason' => 'other', 'details' => 'PRIVATE OUTSIDE EVIDENCE', 'status' => 'open', 'assignment_version' => 0, 'created_at' => now(), 'updated_at' => now()]);
    $this->actingAs($admin)->get('/admin/reports')->assertOk()->assertDontSee('PRIVATE OUTSIDE EVIDENCE');
    $this->get('/admin/tutor-profiles')->assertOk();
    $this->get('/admin/reviews')->assertOk();
    Livewire::test(Operations::class, ['section' => 'report-assignment', 'recordId' => $id])->assertOk()->assertDontSee('PRIVATE OUTSIDE EVIDENCE');
    expect(ReportResource::getEloquentQuery()->first()->getAttributes())->not->toHaveKey('details');
});

it('rechecks report assignment on the next Livewire request and refuses stale assignment decisions', function () {
    $admin = TestActors::admin();
    $id = DB::table('reports')->insertGetId(['reporter_id' => TestActors::customer()->id, 'resource_type' => 'program', 'resource_id' => 1, 'reason' => 'other', 'details' => 'ASSIGNED PRIVATE DETAILS', 'status' => 'open', 'assigned_admin_id' => $admin->id, 'assignment_version' => 1, 'created_at' => now(), 'updated_at' => now()]);
    $this->actingAs($admin);
    $page = Livewire::test(Operations::class, ['section' => 'report', 'recordId' => $id])->assertOk()->assertSee('ASSIGNED PRIVATE DETAILS');
    DB::table('reports')->where('id', $id)->update(['assigned_admin_id' => TestActors::admin()->id, 'assignment_version' => 2]);
    $page->call('$refresh')->assertNotFound();
    $this->getJson("/api/v1/admin/reports/{$id}/evidence")->assertNotFound();
});

it('moderates reviews with one shared audit action while keeping text intact', function () {
    $admin = TestActors::admin();
    $tutor = ProgramApiFixtures::eligibleTutor();
    $customer = TestActors::customer();
    $program = ProgramApiFixtures::draft($tutor, $customer, ['status' => 'completed']);
    $id = DB::table('reviews')->insertGetId(['program_id' => $program, 'customer_id' => $customer->id, 'tutor_profile_id' => $tutor->tutorProfile->id, 'rating' => 5, 'comment' => 'Original comment', 'visibility' => 'visible', 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
    $this->actingAs($admin);
    Livewire::test(Operations::class, ['section' => 'review', 'recordId' => $id])->set('reason', 'Needs moderation')->call('operate', 'hide')->assertOk()->assertSee('Original comment');
    $this->assertDatabaseHas('reviews', ['id' => $id, 'visibility' => 'hidden', 'version' => 2, 'comment' => 'Original comment']);
    $this->assertDatabaseHas('audit_logs', ['resource_type' => 'review', 'resource_id' => $id, 'action' => 'review.hide', 'actor_id' => $admin->id]);
    $this->postJson("/api/v1/admin/reviews/{$id}/restore", ['expected_version' => 1, 'reason' => 'Stale'])->assertConflict();
});

it('projects only allowed audit fields in Filament and denies direct client changes', function () {
    $admin = TestActors::admin();
    $profile = adminFrontendProfile($admin);
    DB::table('audit_logs')->insert(['actor_id' => $admin->id, 'resource_type' => 'tutor_profile', 'resource_id' => $profile->id, 'action' => 'test.fixture', 'before' => json_encode(['status' => 'submitted', 'secret' => 'PRIVATE JSONB TOKEN']), 'after' => json_encode(['status' => 'approved', 'password' => 'PRIVATE PASSWORD']), 'created_at' => now()]);
    $this->actingAs($admin);
    Livewire::test(Operations::class, ['section' => 'audit'])->assertOk()->assertSee('test.fixture')->assertDontSee('PRIVATE JSONB TOKEN')->assertDontSee('PRIVATE PASSWORD');
    $this->deleteJson('/api/v1/admin/audit-logs/1')->assertMethodNotAllowed();
    $this->patchJson('/api/v1/admin/audit-logs/1', ['after' => []])->assertMethodNotAllowed();
});
