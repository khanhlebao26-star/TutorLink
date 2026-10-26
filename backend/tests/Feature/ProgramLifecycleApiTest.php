<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
    $now = now('UTC');
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
