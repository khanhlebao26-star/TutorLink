<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\ProgramApiFixtures;
use Tests\Support\TestActors;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('resolves the pending Invitation once without activating or changing its snapshot', function (string $action, string $status) {
    $this->freezeTime();
    $tutor = ProgramApiFixtures::eligibleTutor();
    $customer = TestActors::customer();
    $program = ProgramApiFixtures::draft($tutor, $customer);
    $invitation = ProgramApiFixtures::invitation($program, $tutor, $customer, now('UTC')->addHours(72)->toDateTimeString());
    $before = DB::table('program_invitations')->find($invitation);
    $actor = $action === 'reject' ? $customer : $tutor;
    $recipient = $action === 'reject' ? $tutor : $customer;
    $reason = 'Offer no longer fits.';

    $this->actingAs($actor, 'web')->postJson("/api/v1/invitations/{$invitation}/{$action}", ['reason' => $reason])
        ->assertOk()->assertJsonPath('data.status', $status)
        ->assertJsonPath('data.accepted_at', null);

    $this->assertDatabaseHas('programs', ['id' => $program, 'status' => 'draft', 'version' => 1]);
    $this->assertDatabaseHas('program_invitations', ['id' => $invitation, 'status' => $status, 'offer_snapshot' => $before->offer_snapshot]);
    $this->assertDatabaseCount('program_members', 0);
    $this->assertDatabaseCount('audit_logs', 1);
    $audit = DB::table('audit_logs')->sole();
    expect($audit->action)->toBe("invitation.{$action}")
        ->and((int) $audit->actor_id)->toBe($actor->id)
        ->and($audit->reason)->toBe($reason);
    $this->assertDatabaseHas('notification_outbox', ['user_id' => $recipient->id, 'type' => "invitation_{$status}", 'resource_id' => $invitation]);
    $this->assertDatabaseCount('notifications', 0);

    $this->postJson("/api/v1/invitations/{$invitation}/{$action}", ['reason' => 'Changed retry'])
        ->assertConflict()->assertJsonPath('code', 'invitation_not_pending');
    $this->assertDatabaseCount('audit_logs', 1);
    $this->assertDatabaseCount('notification_outbox', 1);
    $this->actingAs($customer, 'web')->getJson("/api/v1/programs/{$program}")->assertNotFound();
})->with(['reject' => ['reject', 'rejected'], 'cancel' => ['cancel', 'cancelled']]);

it('denies unrelated accounts and the opposite party without exposing the Invitation', function (string $action, string $actorType) {
    $tutor = ProgramApiFixtures::eligibleTutor();
    $customer = TestActors::customer();
    $program = ProgramApiFixtures::draft($tutor, $customer);
    $invitation = ProgramApiFixtures::invitation($program, $tutor, $customer, now('UTC')->addHour()->toDateTimeString());
    $actor = match ($actorType) {
        'customer' => TestActors::customer(), 'tutor' => TestActors::tutor(),
        'admin' => TestActors::admin(), 'opposite' => $action === 'reject' ? $tutor : $customer,
    };

    $this->actingAs($actor, 'web')->postJson("/api/v1/invitations/{$invitation}/{$action}")
        ->assertNotFound()->assertJsonPath('code', 'invitation_not_found');

    $this->assertDatabaseHas('program_invitations', ['id' => $invitation, 'status' => 'pending']);
    $this->assertDatabaseCount('program_members', 0);
    $this->assertDatabaseCount('audit_logs', 0);
    $this->assertDatabaseCount('notification_outbox', 0);
})->with(['reject', 'cancel'])->with(['customer', 'tutor', 'admin', 'opposite']);

it('requires authentication for Invitation decisions', function (string $action) {
    $this->postJson("/api/v1/invitations/1/{$action}")->assertUnauthorized();
})->with(['reject', 'cancel']);

it('validates decision payloads without writing any state', function (array $payload, string $field) {
    $tutor = ProgramApiFixtures::eligibleTutor();
    $customer = TestActors::customer();
    $program = ProgramApiFixtures::draft($tutor, $customer);
    $invitation = ProgramApiFixtures::invitation($program, $tutor, $customer, now('UTC')->addHour()->toDateTimeString());

    $this->actingAs($customer, 'web')->postJson("/api/v1/invitations/{$invitation}/reject", $payload)
        ->assertUnprocessable()->assertJsonValidationErrors($field);

    $this->assertDatabaseHas('program_invitations', ['id' => $invitation, 'status' => 'pending']);
    $this->assertDatabaseCount('audit_logs', 0);
    $this->assertDatabaseCount('notification_outbox', 0);
})->with([
    'long reason' => [['reason' => str_repeat('x', 2001)], 'reason'],
    'non-string reason' => [['reason' => ['nested']], 'reason'],
    'unsupported field' => [['status' => 'accepted'], 'status'],
]);

it('refuses expired and terminal Invitations without side effects', function (string $action, string $status, bool $expired) {
    $this->freezeTime();
    $tutor = ProgramApiFixtures::eligibleTutor();
    $customer = TestActors::customer();
    $program = ProgramApiFixtures::draft($tutor, $customer);
    $invitation = ProgramApiFixtures::invitation($program, $tutor, $customer, ($expired ? now('UTC') : now('UTC')->addHour())->toDateTimeString());
    DB::table('program_invitations')->where('id', $invitation)->update(['status' => $status]);
    $actor = $action === 'reject' ? $customer : $tutor;

    $this->actingAs($actor, 'web')->postJson("/api/v1/invitations/{$invitation}/{$action}")
        ->assertConflict()->assertJsonPath('code', $expired ? 'invitation_expired' : 'invitation_not_pending');

    $this->assertDatabaseHas('program_invitations', ['id' => $invitation, 'status' => $status]);
    $this->assertDatabaseCount('audit_logs', 0);
    $this->assertDatabaseCount('notification_outbox', 0);
})->with(['reject', 'cancel'])->with([
    'expiry boundary' => ['pending', true], 'accepted' => ['accepted', false],
    'rejected' => ['rejected', false], 'cancelled' => ['cancelled', false], 'expired' => ['expired', false],
]);

it('denies a suspended decision actor', function (string $action) {
    $tutor = ProgramApiFixtures::eligibleTutor();
    $customer = TestActors::customer();
    $program = ProgramApiFixtures::draft($tutor, $customer);
    $invitation = ProgramApiFixtures::invitation($program, $tutor, $customer, now('UTC')->addHour()->toDateTimeString());
    $actor = $action === 'reject' ? $customer : $tutor;
    DB::table('users')->where('id', $actor->id)->update(['status' => 'suspended']);

    $this->actingAs($actor->fresh(), 'web')->postJson("/api/v1/invitations/{$invitation}/{$action}")->assertForbidden();

    $this->assertDatabaseHas('program_invitations', ['id' => $invitation, 'status' => 'pending']);
    $this->assertDatabaseCount('notification_outbox', 0);
})->with(['reject', 'cancel']);

it('allows the Tutor to edit and send a new offer after a decision while preserving the old snapshot', function (string $action) {
    $tutor = ProgramApiFixtures::eligibleTutor();
    $customer = TestActors::customer();
    $program = ProgramApiFixtures::draft($tutor, $customer);
    $invitation = ProgramApiFixtures::invitation($program, $tutor, $customer, now('UTC')->addHour()->toDateTimeString());
    $snapshot = DB::table('program_invitations')->where('id', $invitation)->value('offer_snapshot');
    $this->actingAs($action === 'reject' ? $customer : $tutor, 'web')
        ->postJson("/api/v1/invitations/{$invitation}/{$action}")->assertOk();

    $this->actingAs($tutor, 'web')->patchJson("/api/v1/programs/{$program}", ['expected_version' => 1, 'title' => 'Revised offer'])
        ->assertOk()->assertJsonPath('data.version', 2);
    $this->postJson("/api/v1/programs/{$program}/invitations", ['expected_version' => 2], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertCreated()->assertJsonPath('data.program_version', 2)->assertJsonPath('data.offer_snapshot.offer.title', 'Revised offer');

    $this->assertDatabaseHas('program_invitations', ['id' => $invitation, 'offer_snapshot' => $snapshot]);
    $this->assertDatabaseCount('program_invitations', 2);
    $this->assertDatabaseCount('program_members', 0);
})->with(['reject', 'cancel']);

it('rolls back Invitation, membership and audit if the outbox write fails', function (string $action) {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL fault injection requires PostgreSQL.');
    }
    $tutor = ProgramApiFixtures::eligibleTutor();
    $customer = TestActors::customer();
    $program = ProgramApiFixtures::draft($tutor, $customer);
    $invitation = ProgramApiFixtures::invitation($program, $tutor, $customer, now('UTC')->addHour()->toDateTimeString());
    DB::unprepared("CREATE FUNCTION h03_reject_outbox() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN RAISE EXCEPTION 'H03 simulated outbox failure'; END; \$\$");
    DB::unprepared('CREATE TRIGGER h03_reject_outbox BEFORE INSERT ON notification_outbox FOR EACH ROW EXECUTE FUNCTION h03_reject_outbox()');

    try {
        $this->actingAs($action === 'cancel' ? $tutor : $customer, 'web')
            ->post("/api/v1/invitations/{$invitation}/{$action}", [], ['Accept' => 'application/json', 'Idempotency-Key' => (string) Str::uuid()])
            ->assertInternalServerError();
        $this->assertDatabaseHas('program_invitations', ['id' => $invitation, 'status' => 'pending', 'responded_at' => null, 'accepted_at' => null]);
        $this->assertDatabaseHas('programs', ['id' => $program, 'status' => 'draft', 'activated_at' => null]);
        $this->assertDatabaseCount('program_members', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('notification_outbox', 0);
        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('idempotency_receipts', 0);
    } finally {
        DB::unprepared('DROP TRIGGER IF EXISTS h03_reject_outbox ON notification_outbox');
        DB::unprepared('DROP FUNCTION IF EXISTS h03_reject_outbox()');
    }
})->with(['reject', 'cancel', 'accept']);
