<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\MessageSendFixtures;
use Tests\Support\ProgramApiFixtures;
use Tests\Support\TestActors;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('creates one Review only after completion and hides it from public counts without exposing Customer identity', function () {
    $tutor = ProgramApiFixtures::eligibleTutor();
    $customer = TestActors::customer();
    $program = ProgramApiFixtures::draft($tutor, $customer, ['status' => 'active']);
    ProgramApiFixtures::addMember($program, $customer, 'customer');
    $payload = ['rating' => 5, 'comment' => 'Useful lessons'];
    $key = ['Idempotency-Key' => (string) Str::uuid()];
    $this->actingAs($customer, 'web')->postJson("/api/v1/programs/{$program}/review", $payload, $key)->assertConflict();
    DB::table('programs')->where('id', $program)->update(['status' => 'completed']);
    $id = $this->postJson("/api/v1/programs/{$program}/review", $payload, $key)->assertCreated()->json('data.id');
    $this->postJson("/api/v1/programs/{$program}/review", $payload, $key)->assertOk()->assertJsonPath('data.id', $id);
    $this->postJson("/api/v1/programs/{$program}/review", $payload, ['Idempotency-Key' => (string) Str::uuid()])->assertConflict();
    $this->assertDatabaseCount('reviews', 1);
    $admin = TestActors::admin();
    $this->actingAs($admin, 'web')->postJson("/api/v1/admin/reviews/{$id}/hide", ['expected_version' => 1, 'reason' => 'Policy violation'])->assertOk();
    $this->postJson("/api/v1/admin/reviews/{$id}/restore", ['expected_version' => 1, 'reason' => 'Stale request'])->assertConflict();
    $slug = $tutor->tutorProfile->public_slug;
    $this->getJson("/api/v1/tutors/{$slug}/reviews")->assertOk()->assertJsonPath('aggregate.count', 0);
    $this->actingAs($customer, 'web')->getJson("/api/v1/programs/{$program}/review")->assertOk()->assertJsonPath('data.review.visibility', 'hidden')->assertJsonMissing(['reason' => 'Policy violation']);
    $this->actingAs($admin, 'web')->postJson("/api/v1/admin/reviews/{$id}/restore", ['expected_version' => 2, 'reason' => 'Reviewed'])->assertOk();
    $response = $this->getJson("/api/v1/tutors/{$slug}/reviews")->assertOk()->assertJsonPath('aggregate.count', 1)->assertJsonPath('data.0.author_name', 'Customer đã xác minh');
    expect($response->getContent())->not->toContain($customer->full_name, $customer->email);
});

it('refuses invalid Reviews and outsiders', function () {
    $tutor = ProgramApiFixtures::eligibleTutor();
    $customer = TestActors::customer();
    $program = ProgramApiFixtures::draft($tutor, $customer, ['status' => 'completed']);
    $this->actingAs($customer, 'web')->postJson("/api/v1/programs/{$program}/review", ['rating' => 5], ['Idempotency-Key' => (string) Str::uuid()])->assertNotFound();
    ProgramApiFixtures::addMember($program, $customer, 'customer');
    $this->postJson("/api/v1/programs/{$program}/review", ['rating' => 6])->assertUnprocessable()->assertJsonPath('code', 'validation_failed')->assertJsonStructure(['request_id', 'errors']);
    $this->postJson("/api/v1/programs/{$program}/review", ['rating' => 5, 'comment' => '   '], ['Idempotency-Key' => (string) Str::uuid()])->assertUnprocessable();
    $this->postJson("/api/v1/programs/{$program}/review", ['rating' => 5, 'visibility' => 'visible'])->assertUnprocessable();
    $this->assertDatabaseCount('reviews', 0);
});

it('revokes evidence at reassignment and closure and exposes only the allowed message window', function () {
    $customer = TestActors::customer();
    $tutor = ProgramApiFixtures::eligibleTutor();
    $chat = MessageSendFixtures::conversation($customer, $tutor);
    $ids = [];
    for ($i = 0; $i < 30; $i++) {
        $ids[] = MessageSendFixtures::seed($chat, $customer, ['created_at' => now('UTC')->startOfSecond()->addSeconds($i)]);
    }
    $otherChat = MessageSendFixtures::conversation(TestActors::customer(), $tutor);
    MessageSendFixtures::seed($otherChat, $tutor, ['body' => 'PRIVATE OTHER CHAT']);
    $payload = ['resource_type' => 'message', 'resource_id' => $ids[15], 'reason_code' => 'other', 'description' => 'Please investigate.'];
    $key = ['Idempotency-Key' => (string) Str::uuid()];
    $this->actingAs($customer, 'web');
    $report = $this->postJson('/api/v1/reports', $payload, $key)->assertCreated()->json('data.id');
    $this->postJson('/api/v1/reports', $payload, $key)->assertOk()->assertJsonPath('data.id', $report);
    $this->postJson('/api/v1/reports', $payload, ['Idempotency-Key' => (string) Str::uuid()])->assertConflict();
    $this->actingAs(TestActors::customer(), 'web')->getJson("/api/v1/me/reports/{$report}")->assertNotFound()->assertJsonPath('code', 'resource_not_found');
    $coordinator = TestActors::admin();
    $first = TestActors::admin();
    $second = TestActors::admin();
    $this->actingAs($coordinator, 'web')->putJson("/api/v1/admin/reports/{$report}/assignment", ['assigned_admin_id' => $first->id, 'assignment_version' => 0, 'reason' => 'Triage'])->assertOk();
    $response = $this->actingAs($first, 'web')->getJson("/api/v1/admin/reports/{$report}/evidence")->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertJsonCount(16, 'data.messages');
    expect(array_column($response->json('data.messages'), 'id'))->toBe(array_slice($ids, 5, 16));
    expect($response->getContent())->not->toContain('PRIVATE OTHER CHAT', 'sender_id', $customer->email);
    $this->actingAs($coordinator, 'web')->putJson("/api/v1/admin/reports/{$report}/assignment", ['assigned_admin_id' => $second->id, 'assignment_version' => 1, 'reason' => 'Transfer'])->assertOk();
    $this->actingAs($first, 'web')->getJson("/api/v1/admin/reports/{$report}/evidence")->assertNotFound();
    $this->actingAs($second, 'web')->postJson("/api/v1/admin/reports/{$report}/resolve", ['assignment_version' => 1, 'resolution_note' => 'Stale'])->assertConflict();
    $this->postJson("/api/v1/admin/reports/{$report}/resolve", ['assignment_version' => 2, 'resolution_note' => 'Handled'])->assertOk();
    $this->getJson("/api/v1/admin/reports/{$report}/evidence")->assertForbidden();
    $this->getJson("/api/v1/admin/reports/{$report}")->assertOk()->assertJsonPath('data.resolution_note', 'Handled');
    $this->assertDatabaseHas('notification_outbox', ['type' => 'report_resolved', 'resource_id' => $report]);
    $this->postJson("/api/v1/admin/reports/{$report}/dismiss", ['assignment_version' => 2, 'resolution_note' => 'Again'])->assertConflict();
});

it('restricts audit to current assignments and masks raw JSONB while auditing reads', function () {
    $admin = TestActors::admin();
    $other = TestActors::admin();
    $customer = TestActors::customer();
    $report = DB::table('reports')->insertGetId(['reporter_id' => $customer->id, 'resource_type' => 'program', 'resource_id' => 1, 'reason' => 'spam', 'status' => 'open', 'assigned_admin_id' => $admin->id, 'created_at' => now(), 'updated_at' => now()]);
    $audit = DB::table('audit_logs')->insertGetId(['actor_id' => $admin->id, 'action' => 'report.create', 'resource_type' => 'report', 'resource_id' => $report, 'after' => json_encode(['status' => 'open', 'credentials' => 'SECRET', 'body' => 'PRIVATE MESSAGE', 'file_ids' => [12], 'version' => ['secret' => 'NESTED SECRET'], 'state' => 'SECRET STATE']), 'created_at' => now()]);
    $response = $this->actingAs($admin, 'web')->getJson("/api/v1/admin/audit-logs/{$audit}")->assertOk()->assertJsonPath('data.after', ['status' => 'open']);
    expect($response->getContent())->not->toContain('SECRET', 'PRIVATE MESSAGE', 'file_ids', 'NESTED SECRET', 'SECRET STATE');
    $this->actingAs($other, 'web')->getJson("/api/v1/admin/audit-logs/{$audit}")->assertNotFound();
    $this->actingAs(TestActors::admin([]), 'web')->getJson('/api/v1/admin/audit-logs')->assertForbidden();
    $this->actingAs($admin, 'web')->getJson('/api/v1/admin/reports/not-an-id')->assertNotFound()->assertJsonPath('code', 'resource_not_found');
    $this->actingAs($admin, 'web')->patchJson("/api/v1/admin/audit-logs/{$audit}", ['after' => []])->assertMethodNotAllowed();
    DB::table('reports')->where('id', $report)->update(['assigned_admin_id' => $other->id]);
    $this->getJson("/api/v1/admin/audit-logs/{$audit}")->assertNotFound();
    $this->assertDatabaseHas('audit_logs', ['action' => 'audit.read', 'resource_id' => $report]);
});

it('enforces Report scope validation and quota without charging a matching retry', function () {
    $this->freezeTime();
    $customer = TestActors::customer();
    $tutor = ProgramApiFixtures::eligibleTutor();
    $chat = MessageSendFixtures::conversation($customer, $tutor);
    $this->actingAs($customer, 'web');
    $this->postJson('/api/v1/reports', ['resource_type' => 'message', 'resource_id' => 99999, 'reason_code' => 'spam'], ['Idempotency-Key' => (string) Str::uuid()])->assertNotFound();
    $this->postJson('/api/v1/reports', ['resource_type' => 'program', 'resource_id' => 1, 'reason_code' => 'other'])->assertUnprocessable();
    $key = ['Idempotency-Key' => (string) Str::uuid()];
    $firstPayload = null;
    for ($i = 0; $i < 10; $i++) {
        $payload = ['resource_type' => 'message', 'resource_id' => MessageSendFixtures::seed($chat, $customer), 'reason_code' => 'spam'];
        $this->postJson('/api/v1/reports', $payload, $i === 0 ? $key : ['Idempotency-Key' => (string) Str::uuid()])->assertCreated();
        $firstPayload ??= $payload;
    }
    $this->postJson('/api/v1/reports', $firstPayload, $key)->assertOk();
    $payload['resource_id'] = MessageSendFixtures::seed($chat, $customer);
    $this->postJson('/api/v1/reports', $payload, ['Idempotency-Key' => (string) Str::uuid()])->assertTooManyRequests()->assertHeader('Retry-After');
    $this->assertDatabaseCount('reports', 10);
});

it('rolls back Report decisions and evidence reads if the audit write fails', function () {
    $customer = TestActors::customer();
    $admin = TestActors::admin();
    $tutor = ProgramApiFixtures::eligibleTutor();
    $program = ProgramApiFixtures::draft($tutor, $customer);
    $report = DB::table('reports')->insertGetId(['reporter_id' => $customer->id, 'resource_type' => 'program', 'resource_id' => $program, 'reason' => 'spam', 'status' => 'open', 'assigned_admin_id' => $admin->id, 'created_at' => now(), 'updated_at' => now()]);
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL fault injection.');
    }
    DB::unprepared("CREATE FUNCTION h04_audit_fail() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN RAISE EXCEPTION 'H04 audit failure'; END; \$\$");
    DB::unprepared('CREATE TRIGGER h04_audit_fail BEFORE INSERT ON audit_logs FOR EACH ROW EXECUTE FUNCTION h04_audit_fail()');
    try {
        $this->actingAs($admin, 'web')->postJson("/api/v1/admin/reports/{$report}/resolve", ['assignment_version' => 0, 'resolution_note' => 'Decision'])->assertInternalServerError()->assertJsonPath('code', 'internal_error');
        $this->getJson("/api/v1/admin/reports/{$report}/evidence")->assertInternalServerError();
        $this->assertDatabaseHas('reports', ['id' => $report, 'status' => 'open', 'handled_at' => null]);
        $this->assertDatabaseCount('notification_outbox', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    } finally {
        DB::unprepared('DROP TRIGGER IF EXISTS h04_audit_fail ON audit_logs');
        DB::unprepared('DROP FUNCTION IF EXISTS h04_audit_fail()');
    }
});

it('keeps open and recent Reports while purging closed Reports older than 24 months with audit', function () {
    $admin = TestActors::admin();
    $customer = TestActors::customer();
    config(['notification_outbox.purge_actor_id' => $admin->id]);
    $ids = [];
    foreach (['open', 'resolved', 'dismissed'] as $i => $status) {
        $ids[] = DB::table('reports')->insertGetId(['reporter_id' => $customer->id, 'resource_type' => 'program', 'resource_id' => $i + 1, 'reason' => 'spam', 'status' => $status,
            'handled_at' => $status === 'open' ? null : now('UTC')->subMonthsNoOverflow($status === 'resolved' ? 25 : 23),
            'created_at' => now('UTC')->subMonthsNoOverflow(25), 'updated_at' => now()]);
    }
    $this->artisan('outbox:purge', ['--execute' => true])->assertSuccessful();
    $this->assertDatabaseHas('reports', ['id' => $ids[0], 'status' => 'open']);
    $this->assertDatabaseMissing('reports', ['id' => $ids[1]]);
    $this->assertDatabaseHas('reports', ['id' => $ids[2]]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'report.purge']);
});

it('enforces the daily Report quota independently from the hourly limit', function () {
    $this->freezeTime();
    $customer = TestActors::customer();
    $tutor = ProgramApiFixtures::eligibleTutor();
    $chat = MessageSendFixtures::conversation($customer, $tutor);
    $message = MessageSendFixtures::seed($chat, $customer);
    for ($i = 0; $i < 30; $i++) {
        DB::table('reports')->insert(['reporter_id' => $customer->id, 'resource_type' => 'program', 'resource_id' => $i + 1, 'reason' => 'spam', 'status' => 'resolved', 'created_at' => now('UTC')->subHours(2), 'updated_at' => now('UTC'), 'handled_at' => now('UTC')]);
    }
    $this->actingAs($customer, 'web')->postJson('/api/v1/reports', ['resource_type' => 'message', 'resource_id' => $message, 'reason_code' => 'spam'], ['Idempotency-Key' => (string) Str::uuid()])->assertTooManyRequests()->assertHeader('Retry-After');
    $this->assertDatabaseCount('reports', 30);
});

it('refuses ambiguous legacy Report rows before changing schema', function (?string $status, bool $duplicate) {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL migration preflight.');
    }
    DB::statement('CREATE TEMP TABLE reports (id bigint, reporter_id bigint, resource_type varchar(40), resource_id bigint, status varchar(20))');
    try {
        DB::table('reports')->insert(['id' => 1, 'reporter_id' => 1, 'resource_type' => 'program', 'resource_id' => 1, 'status' => $status]);
        if ($duplicate) {
            DB::table('reports')->insert(['id' => 2, 'reporter_id' => 1, 'resource_type' => 'program', 'resource_id' => 1, 'status' => 'reviewing']);
        }
        $migration = require database_path('migrations/2026_11_02_052431_add_h04_review_report_controls.php');
        expect(fn () => $migration->up())->toThrow(RuntimeException::class, $duplicate ? 'Duplicate open Reports require data review.' : 'Unmapped Report status requires data review.');
    } finally {
        DB::statement('DROP TABLE pg_temp.reports');
    }
})->with(['unknown' => ['unexpected', false], 'null' => [null, false], 'duplicate' => ['pending', true]]);

it('separates coordinator summaries from evidence and supports scoped lists and dismiss decisions', function () {
    $customer = TestActors::customer();
    $tutor = ProgramApiFixtures::eligibleTutor();
    $program = ProgramApiFixtures::draft($tutor, $customer, ['status' => 'completed']);
    ProgramApiFixtures::addMember($program, $customer, 'customer');
    $key = fn () => ['Idempotency-Key' => (string) Str::uuid()];
    $this->actingAs($customer, 'web');
    $review = $this->postJson("/api/v1/programs/{$program}/review", ['rating' => 4], $key())->assertCreated()->json('data.id');
    $report = $this->postJson('/api/v1/reports', ['resource_type' => 'review', 'resource_id' => $review, 'reason_code' => 'spam', 'description' => 'PRIVATE REPORT DETAILS'], $key())->assertCreated()->json('data.id');
    $this->getJson('/api/v1/me/reports')->assertOk()->assertJsonPath('data.0.id', $report);
    $this->getJson("/api/v1/me/reports/{$report}")->assertOk();
    $this->postJson("/api/v1/admin/reviews/{$review}/hide", ['expected_version' => 1, 'reason' => 'Not authorized'])->assertForbidden();
    $coordinator = TestActors::admin(['admin.reports.assign']);
    $response = $this->actingAs($coordinator, 'web')->getJson('/api/v1/admin/reports?scope=unassigned')->assertOk()->assertJsonPath('data.0.id', $report);
    expect($response->getContent())->not->toContain('PRIVATE REPORT DETAILS', 'description', 'reason_code');
    $this->getJson("/api/v1/admin/reports/{$report}/evidence")->assertForbidden();
    $reviewer = TestActors::admin();
    $this->putJson("/api/v1/admin/reports/{$report}/assignment", ['assigned_admin_id' => $reviewer->id, 'assignment_version' => 0, 'reason' => 'Assign'])->assertOk();
    $this->actingAs($reviewer, 'web')->getJson('/api/v1/admin/reports')->assertOk()->assertJsonPath('data.0.id', $report)->assertJsonMissingPath('data.0.evidence');
    $this->getJson('/api/v1/admin/reviews')->assertOk()->assertJsonPath('data.0.id', $review);
    $this->getJson("/api/v1/admin/reviews/{$review}")->assertOk()->assertJsonPath('data.comment', null);
    $this->getJson("/api/v1/admin/reports/{$report}/evidence")->assertOk()->assertJsonPath('data.resource.rating', 4);
    $this->getJson('/api/v1/admin/audit-logs?resource_type=report')->assertOk()->assertJsonPath('data.0.resource_type', 'report');
    $this->postJson("/api/v1/admin/reports/{$report}/dismiss", ['assignment_version' => 1, 'resolution_note' => 'No policy violation'])->assertOk()->assertJsonPath('data.status', 'dismissed');
    $this->assertDatabaseHas('notification_outbox', ['type' => 'report_dismissed', 'resource_id' => $report]);
    $this->getJson("/api/v1/admin/reports/{$report}/evidence")->assertForbidden();
});

it('does not expose private Listing or Profile content after the resource loses its public gates', function (string $type) {
    $customer = TestActors::customer();
    $tutor = ProgramApiFixtures::eligibleTutor();
    $admin = TestActors::admin();
    $id = $type === 'listing' ? ProgramApiFixtures::listing($tutor) : $tutor->tutorProfile->id;
    if ($type === 'listing') {
        DB::table('service_listings')->where('id', $id)->update(['status' => 'active']);
    }
    $report = $this->actingAs($customer, 'web')->postJson('/api/v1/reports', ['resource_type' => $type, 'resource_id' => $id, 'reason_code' => 'spam'], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated()->json('data.id');
    DB::table('reports')->where('id', $report)->update(['assigned_admin_id' => $admin->id]);
    $response = $this->actingAs($admin, 'web')->getJson("/api/v1/admin/reports/{$report}/evidence")->assertOk()->assertJsonPath('data.resource.id', $id);
    expect($response->getContent())->not->toContain('object_key', 'date_of_birth', 'email');
    if ($type === 'listing') {
        DB::table('service_listings')->where('id', $id)->update(['status' => 'inactive', 'description' => 'NOW PRIVATE']);
    } else {
        DB::table('tutor_profiles')->where('id', $id)->update(['status' => 'suspended', 'bio' => 'NOW PRIVATE']);
    }
    $response = $this->getJson("/api/v1/admin/reports/{$report}/evidence")->assertOk()->assertJsonPath('data.code', 'evidence_unavailable');
    expect($response->getContent())->not->toContain('NOW PRIVATE');
})->with(['listing', 'tutor_profile']);

it('does not disclose a Review whose Tutor is no longer publicly eligible to an unrelated reporter', function () {
    $customer = TestActors::customer();
    $tutor = ProgramApiFixtures::eligibleTutor();
    $program = ProgramApiFixtures::draft($tutor, $customer, ['status' => 'completed']);
    ProgramApiFixtures::addMember($program, $customer, 'customer');
    $review = $this->actingAs($customer, 'web')->postJson("/api/v1/programs/{$program}/review", ['rating' => 5], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated()->json('data.id');
    DB::table('users')->where('id', $tutor->id)->update(['status' => 'suspended']);
    $this->actingAs(TestActors::customer(), 'web')->postJson('/api/v1/reports', ['resource_type' => 'review', 'resource_id' => $review, 'reason_code' => 'spam'], ['Idempotency-Key' => (string) Str::uuid()])->assertNotFound();
    $this->assertDatabaseCount('reports', 0);
});
