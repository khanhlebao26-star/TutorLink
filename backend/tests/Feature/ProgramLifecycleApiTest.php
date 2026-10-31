<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\ProgramApiFixtures;
use Tests\Support\TestActors;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('records distinct lifecycle events without changing the offer version', function () {
    $tutor = ProgramApiFixtures::eligibleTutor();
    $customer = TestActors::customer();
    $programId = ProgramApiFixtures::draft($tutor, $customer, ['status' => 'active']);
    ProgramApiFixtures::addMember($programId, $tutor, 'tutor');
    ProgramApiFixtures::addMember($programId, $customer, 'customer');

    $this->actingAs($tutor, 'web');
    $this->postJson("/api/v1/programs/{$programId}/pause")
        ->assertOk()
        ->assertJsonPath('data.status', 'paused')
        ->assertJsonPath('data.allowed_actions', ['resume', 'complete', 'cancel']);
    $this->postJson("/api/v1/programs/{$programId}/resume")
        ->assertOk()
        ->assertJsonPath('data.status', 'active');
    $this->postJson("/api/v1/programs/{$programId}/pause")
        ->assertOk()
        ->assertJsonPath('data.status', 'paused');

    $this->assertDatabaseHas('programs', ['id' => $programId, 'status' => 'paused', 'version' => 1]);
    $this->assertDatabaseCount('audit_logs', 3);
    $this->assertDatabaseCount('notification_outbox', 3);
    expect(DB::table('notification_outbox')->pluck('event_key')->unique())->toHaveCount(3);
});

it('returns Session and Task blockers without completing the Program', function () {
    $tutor = ProgramApiFixtures::eligibleTutor();
    $customer = TestActors::customer();
    $programId = ProgramApiFixtures::draft($tutor, $customer, ['status' => 'active']);
    ProgramApiFixtures::addMember($programId, $tutor, 'tutor');
    ProgramApiFixtures::addMember($programId, $customer, 'customer');
    $now = now('UTC')->toImmutable();
    $programSessionId = DB::table('program_sessions')->insertGetId([
        'program_id' => $programId,
        'program_version' => 1,
        'sequence_number' => 1,
        'title' => 'Lesson 1',
        'planned_starts_at' => $now->addHour(),
        'planned_ends_at' => $now->addHours(2),
        'timezone' => 'Asia/Ho_Chi_Minh',
        'delivery_mode' => 'online',
        'location_text' => null,
        'meeting_url' => null,
        'status' => 'scheduled',
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $taskId = DB::table('tasks')->insertGetId([
        'program_id' => $programId,
        'title' => 'Practice',
        'description' => null,
        'due_at' => null,
        'status' => 'submitted',
        'assigned_to' => $customer->id,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $this->actingAs($tutor, 'web')
        ->postJson("/api/v1/programs/{$programId}/complete")
        ->assertConflict()
        ->assertJsonPath('code', 'program_completion_blocked')
        ->assertJsonPath('errors.blockers.sessions.0.id', $programSessionId)
        ->assertJsonPath('errors.blockers.sessions.0.status', 'scheduled')
        ->assertJsonPath('errors.blockers.sessions.0.required_action', 'complete_or_cancel_session')
        ->assertJsonPath('errors.blockers.tasks.0.id', $taskId)
        ->assertJsonPath('errors.blockers.tasks.0.status', 'submitted')
        ->assertJsonPath('errors.blockers.tasks.0.required_action', 'complete_or_cancel_task');
    $this->postJson("/api/v1/programs/{$programId}/cancel")
        ->assertConflict()
        ->assertJsonPath('code', 'program_cancellation_blocked');

    $this->assertDatabaseHas('programs', ['id' => $programId, 'status' => 'active']);
    $this->assertDatabaseCount('audit_logs', 0);
    $this->assertDatabaseCount('notification_outbox', 0);
});

it('returns 404 when another Tutor attempts a Program lifecycle action', function () {
    $owner = ProgramApiFixtures::eligibleTutor();
    $customer = TestActors::customer();
    $programId = ProgramApiFixtures::draft($owner, $customer, ['status' => 'active']);
    ProgramApiFixtures::addMember($programId, $owner, 'tutor');
    ProgramApiFixtures::addMember($programId, $customer, 'customer');
    $otherTutor = TestActors::tutor();

    $this->actingAs($otherTutor, 'web')
        ->postJson("/api/v1/programs/{$programId}/pause")
        ->assertNotFound()
        ->assertJsonPath('code', 'program_not_found');

    $this->assertDatabaseHas('programs', ['id' => $programId, 'status' => 'active']);
    $this->assertDatabaseCount('audit_logs', 0);
});

it('rejects a transition outside the allowed state without side effects', function () {
    $tutor = ProgramApiFixtures::eligibleTutor();
    $customer = TestActors::customer();
    $programId = ProgramApiFixtures::draft($tutor, $customer);

    $this->actingAs($tutor, 'web')
        ->postJson("/api/v1/programs/{$programId}/pause")
        ->assertConflict()
        ->assertJsonPath('code', 'program_transition_invalid');

    $this->assertDatabaseHas('programs', ['id' => $programId, 'status' => 'draft']);
    $this->assertDatabaseCount('audit_logs', 0);
    $this->assertDatabaseCount('notification_outbox', 0);
});

it('cancels a Draft and its unexpired Invitation in one transaction', function () {
    $tutor = ProgramApiFixtures::eligibleTutor();
    $customer = TestActors::customer();
    $programId = ProgramApiFixtures::draft($tutor, $customer);
    $invitationId = ProgramApiFixtures::invitation($programId, $tutor, $customer, now('UTC')->addHour()->toISOString());

    $this->actingAs($tutor, 'web')
        ->postJson("/api/v1/programs/{$programId}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.allowed_actions', []);

    $this->assertDatabaseHas('program_invitations', ['id' => $invitationId, 'status' => 'cancelled']);
    $this->assertDatabaseHas('programs', ['id' => $programId, 'status' => 'cancelled']);
    $this->assertDatabaseHas('notification_outbox', [
        'user_id' => $customer->id,
        'type' => 'invitation_cancelled',
        'event_key' => "invitation:{$invitationId}:cancelled",
    ]);
    $this->assertDatabaseCount('audit_logs', 2);
});

it('completes a Program when Tasks are terminal and writes the member notification', function () {
    $tutor = ProgramApiFixtures::eligibleTutor();
    $customer = TestActors::customer();
    $programId = ProgramApiFixtures::draft($tutor, $customer, ['status' => 'paused']);
    ProgramApiFixtures::addMember($programId, $tutor, 'tutor');
    ProgramApiFixtures::addMember($programId, $customer, 'customer');
    DB::table('tasks')->insert([
        'program_id' => $programId,
        'title' => 'Practice',
        'description' => null,
        'due_at' => null,
        'status' => 'completed',
        'assigned_to' => $customer->id,
        'created_at' => now('UTC'),
        'updated_at' => now('UTC'),
    ]);

    $this->actingAs($tutor, 'web')
        ->postJson("/api/v1/programs/{$programId}/complete")
        ->assertOk()
        ->assertJsonPath('data.status', 'completed')
        ->assertJsonPath('data.allowed_actions', []);

    $this->assertDatabaseHas('programs', ['id' => $programId, 'status' => 'completed']);
    $this->assertDatabaseHas('notification_outbox', [
        'user_id' => $customer->id,
        'type' => 'program_completed',
        'resource_id' => $programId,
    ]);
});

it('reports every nonterminal Task as a blocker and preserves child state', function (string $action, string $status) {
    $tutor = ProgramApiFixtures::eligibleTutor();
    $customer = TestActors::customer();
    $program = ProgramApiFixtures::draft($tutor, $customer, ['status' => 'active']);
    ProgramApiFixtures::addMember($program, $tutor, 'tutor');
    ProgramApiFixtures::addMember($program, $customer, 'customer');
    $task = DB::table('tasks')->insertGetId([
        'program_id' => $program, 'assigned_to' => $customer->id, 'title' => 'Pending work',
        'status' => $status, 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $terminal = in_array($status, ['completed', 'cancelled'], true);

    $response = $this->actingAs($tutor, 'web')->postJson("/api/v1/programs/{$program}/{$action}");

    if ($terminal) {
        $response->assertOk()->assertJsonPath('data.status', $action === 'complete' ? 'completed' : 'cancelled');
    } else {
        $response->assertConflict()->assertJsonPath('code', $action === 'complete' ? 'program_completion_blocked' : 'program_cancellation_blocked')
            ->assertJsonPath('errors.blockers.sessions', [])
            ->assertJsonPath('errors.blockers.tasks', [['id' => $task, 'status' => $status, 'required_action' => 'complete_or_cancel_task']]);
        $this->assertDatabaseHas('programs', ['id' => $program, 'status' => 'active']);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('notification_outbox', 0);
    }
    $this->assertDatabaseHas('tasks', ['id' => $task, 'status' => $status, 'version' => 1]);
    $this->assertDatabaseCount('program_members', 2);
})->with(['complete', 'cancel'])->with(['todo', 'in_progress', 'submitted', 'completed', 'cancelled']);

it('grants access only after accept and completes a paused Program only after explicit Task feedback', function () {
    $tutor = ProgramApiFixtures::eligibleTutor();
    $customer = TestActors::customer();
    $program = ProgramApiFixtures::draft($tutor, $customer);
    $invitation = ProgramApiFixtures::invitation($program, $tutor, $customer, now('UTC')->addHours(72)->toDateTimeString());
    $key = fn (): array => ['Idempotency-Key' => (string) Str::uuid()];
    $this->actingAs($customer, 'web')->getJson("/api/v1/programs/{$program}")->assertNotFound();
    $this->getJson("/api/v1/programs/{$program}/tasks")->assertNotFound();
    $this->post("/api/v1/invitations/{$invitation}/accept", [], ['Accept' => 'application/json', ...$key()])->assertOk();
    $this->getJson("/api/v1/programs/{$program}")->assertOk();
    $this->actingAs($tutor, 'web');
    $task = $this->postJson("/api/v1/programs/{$program}/tasks", ['title' => 'Write a paragraph', 'assigned_to' => $customer->id])
        ->assertCreated()->json('data.id');
    $this->postJson("/api/v1/programs/{$program}/pause")->assertOk();
    $this->postJson("/api/v1/programs/{$program}/tasks", ['title' => 'Blocked', 'assigned_to' => $customer->id])->assertConflict();
    $this->postJson("/api/v1/programs/{$program}/complete")->assertConflict()->assertJsonPath('errors.blockers.tasks.0.status', 'todo');
    $this->actingAs($customer, 'web')->postJson("/api/v1/tasks/{$task}/start", ['expected_version' => 1])->assertOk();
    $this->putJson("/api/v1/tasks/{$task}/submission", ['expected_submission_version' => 0, 'content' => 'First answer'], $key())->assertCreated();
    $this->actingAs($tutor, 'web')->postJson("/api/v1/programs/{$program}/complete")->assertConflict()->assertJsonPath('errors.blockers.tasks.0.status', 'submitted');
    $this->postJson("/api/v1/tasks/{$task}/submission/request-revision", ['expected_submission_version' => 1, 'feedback' => 'Add an example.'], $key())->assertOk();
    $this->postJson("/api/v1/programs/{$program}/complete")->assertConflict()->assertJsonPath('errors.blockers.tasks.0.status', 'in_progress');
    $this->actingAs($customer, 'web')->putJson("/api/v1/tasks/{$task}/submission", ['expected_submission_version' => 1, 'content' => 'Answer with example'], $key())->assertCreated();
    $this->actingAs($tutor, 'web')->postJson("/api/v1/tasks/{$task}/submission/review", ['expected_submission_version' => 2, 'feedback' => 'Accepted.'], $key())->assertOk();

    $this->postJson("/api/v1/programs/{$program}/complete")->assertOk()->assertJsonPath('data.status', 'completed');

    $this->assertDatabaseHas('tasks', ['id' => $task, 'status' => 'completed']);
    $this->assertDatabaseCount('task_submissions', 2);
    $this->assertDatabaseHas('task_submissions', ['task_id' => $task, 'submission_version' => 1, 'status' => 'revision_requested', 'feedback' => 'Add an example.']);
    $this->assertDatabaseHas('task_submissions', ['task_id' => $task, 'submission_version' => 2, 'status' => 'accepted']);
    $this->assertDatabaseCount('program_members', 2);
    $this->postJson("/api/v1/programs/{$program}/resume")->assertConflict();
    $this->postJson("/api/v1/programs/{$program}/tasks", ['title' => 'Blocked', 'assigned_to' => $customer->id])->assertConflict();
    $this->actingAs($customer, 'web')->getJson("/api/v1/programs/{$program}")->assertOk();
    $this->getJson("/api/v1/tasks/{$task}/submission/versions")->assertOk()->assertJsonCount(2, 'data');
});
