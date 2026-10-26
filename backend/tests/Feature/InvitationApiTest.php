<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\ProgramApiFixtures;
use Tests\Support\TestActors;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function sendInvitationRequest(TestCase $testCase, User $actor, int $programId, array $payload, string $key): TestResponse
{
    $testCase->actingAs($actor, 'web');

    return $testCase->call(
        'POST',
        "/api/v1/programs/{$programId}/invitations",
        [],
        [],
        [],
        [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_IDEMPOTENCY_KEY' => $key,
        ],
        json_encode($payload, JSON_THROW_ON_ERROR),
    );
}

it('sends an immutable snapshot and replays the same idempotent response', function () {
    $tutor = ProgramApiFixtures::eligibleTutor();
    $customer = TestActors::customer();
    $programId = ProgramApiFixtures::draft($tutor, $customer);
    $key = (string) Str::uuid();

    $response = sendInvitationRequest($this, $tutor, $programId, ['expected_version' => 1], $key);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.program_version', 1)
        ->assertJsonPath('data.offer_snapshot.program_id', $programId)
        ->assertJsonPath('data.offer_snapshot.target_customer_id', $customer->id)
        ->assertJsonPath('data.offer_snapshot.offer.title', 'English lessons');
    $invitationId = $response->json('data.id');
    $this->assertDatabaseHas('programs', [
        'id' => $programId,
        'version' => 1,
    ]);
    expect(DB::table('programs')->where('id', $programId)->value('snapshot_locked_at'))->not->toBeNull();
    $this->assertDatabaseHas('audit_logs', ['action' => 'invitation.send', 'resource_id' => $invitationId]);
    $this->assertDatabaseHas('notification_outbox', [
        'user_id' => $customer->id,
        'type' => 'invitation_received',
        'event_key' => "invitation:{$invitationId}:received",
    ]);

    sendInvitationRequest($this, $tutor, $programId, ['expected_version' => 1], $key)
        ->assertCreated()
        ->assertJsonPath('data.id', $invitationId);
    sendInvitationRequest($this, $tutor, $programId, ['expected_version' => 2], $key)
        ->assertConflict()
        ->assertJsonPath('code', 'idempotency_key_reused');

    $this->assertDatabaseCount('program_invitations', 1);
    $this->assertDatabaseCount('notification_outbox', 1);
    $this->assertDatabaseCount('audit_logs', 1);
});

it('returns effective expiry in scoped Invitation reads without writing it', function () {
    $tutor = ProgramApiFixtures::eligibleTutor();
    $customer = TestActors::customer();
    $otherCustomer = TestActors::customer();
    $programId = ProgramApiFixtures::draft($tutor, $customer);
    $invitationId = ProgramApiFixtures::invitation($programId, $tutor, $customer, now('UTC')->subSecond()->toISOString());

    $this->actingAs($customer, 'web');
    $this->getJson("/api/v1/programs/{$programId}")->assertNotFound();
    $this->getJson('/api/v1/invitations')
        ->assertOk()
        ->assertJsonPath('data.0.id', $invitationId)
        ->assertJsonPath('data.0.status', 'expired');
    $this->getJson("/api/v1/invitations/{$invitationId}")
        ->assertOk()
        ->assertJsonPath('data.status', 'expired')
        ->assertJsonPath('data.program_id', $programId);

    $this->actingAs($otherCustomer, 'web');
    $this->getJson('/api/v1/invitations')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson("/api/v1/invitations/{$invitationId}")
        ->assertNotFound()
        ->assertJsonPath('code', 'invitation_not_found');

    $this->actingAs($tutor, 'web');
    $this->getJson('/api/v1/invitations')
        ->assertOk()
        ->assertJsonPath('data.0.id', $invitationId)
        ->assertJsonPath('data.0.status', 'expired');

    $this->assertDatabaseHas('program_invitations', ['id' => $invitationId, 'status' => 'pending']);
    $this->assertDatabaseCount('audit_logs', 0);
    $this->assertDatabaseCount('notification_outbox', 0);
});

it('expires an old pending Invitation atomically before sending a replacement', function () {
    $tutor = ProgramApiFixtures::eligibleTutor();
    $customer = TestActors::customer();
    $programId = ProgramApiFixtures::draft($tutor, $customer);
    $oldInvitationId = ProgramApiFixtures::invitation(
        $programId,
        $tutor,
        $customer,
        now('UTC')->subSecond()->toISOString(),
    );

    $response = sendInvitationRequest($this, $tutor, $programId, ['expected_version' => 1], (string) Str::uuid());

    $response->assertCreated();
    $newInvitationId = $response->json('data.id');
    $this->assertNotSame($oldInvitationId, $newInvitationId);
    $this->assertDatabaseHas('program_invitations', ['id' => $oldInvitationId, 'status' => 'expired']);
    $this->assertDatabaseHas('program_invitations', ['id' => $newInvitationId, 'status' => 'pending']);
    $this->assertDatabaseHas('notification_outbox', [
        'user_id' => $tutor->id,
        'type' => 'invitation_expired',
        'event_key' => "invitation:{$oldInvitationId}:expired",
    ]);
    $this->assertDatabaseHas('notification_outbox', [
        'user_id' => $customer->id,
        'type' => 'invitation_received',
        'event_key' => "invitation:{$newInvitationId}:received",
    ]);
});

it('rolls back lazy expiry when the replacement request has a stale version', function () {
    $tutor = ProgramApiFixtures::eligibleTutor();
    $customer = TestActors::customer();
    $programId = ProgramApiFixtures::draft($tutor, $customer);
    $invitationId = ProgramApiFixtures::invitation($programId, $tutor, $customer, now('UTC')->subSecond()->toISOString());

    sendInvitationRequest($this, $tutor, $programId, ['expected_version' => 2], (string) Str::uuid())
        ->assertConflict()
        ->assertJsonPath('code', 'stale_program_version');

    $this->assertDatabaseHas('program_invitations', ['id' => $invitationId, 'status' => 'pending']);
    $this->assertDatabaseCount('notification_outbox', 0);
    $this->assertDatabaseCount('audit_logs', 0);
});

it('rejects a send while an unexpired Invitation locks the Draft', function () {
    $tutor = ProgramApiFixtures::eligibleTutor();
    $customer = TestActors::customer();
    $programId = ProgramApiFixtures::draft($tutor, $customer);
    ProgramApiFixtures::invitation($programId, $tutor, $customer, now('UTC')->addHour()->toISOString());

    sendInvitationRequest($this, $tutor, $programId, ['expected_version' => 1], (string) Str::uuid())
        ->assertConflict()
        ->assertJsonPath('code', 'program_offer_locked');

    $this->assertDatabaseCount('program_invitations', 1);
    $this->assertDatabaseCount('notification_outbox', 0);
});

it('snapshots the allow-listed source Listing context at send time', function () {
    $tutor = ProgramApiFixtures::eligibleTutor();
    $customer = TestActors::customer();
    $listingId = ProgramApiFixtures::listing($tutor);
    $programId = ProgramApiFixtures::draft($tutor, $customer, ['source_listing_id' => $listingId]);

    $response = sendInvitationRequest($this, $tutor, $programId, ['expected_version' => 1], (string) Str::uuid());

    $response->assertCreated()
        ->assertJsonPath('data.offer_snapshot.source_listing.id', $listingId)
        ->assertJsonPath('data.offer_snapshot.source_listing.title', 'English conversation')
        ->assertJsonPath('data.offer_snapshot.source_listing.price_from_minor', 250000);
});

it('rejects sending when the source Listing has a moderation hold', function () {
    $tutor = ProgramApiFixtures::eligibleTutor();
    $customer = TestActors::customer();
    $listingId = ProgramApiFixtures::listing($tutor);
    $programId = ProgramApiFixtures::draft($tutor, $customer, ['source_listing_id' => $listingId]);
    $now = now('UTC');
    DB::table('listing_moderation_holds')->insert([
        'listing_id' => $listingId,
        'hidden_by_admin_id' => TestActors::admin()->id,
        'hidden_at' => $now,
        'hide_reason' => 'Review required.',
        'hide_reason_code' => 'policy_review',
        'reactivate_on_restore' => false,
        'restored_by_admin_id' => null,
        'restored_at' => null,
        'restore_reason' => null,
        'restore_reason_code' => null,
    ]);

    sendInvitationRequest($this, $tutor, $programId, ['expected_version' => 1], (string) Str::uuid())
        ->assertConflict()
        ->assertJsonPath('code', 'listing_moderation_hold');

    $this->assertDatabaseCount('program_invitations', 0);
    $this->assertDatabaseCount('notification_outbox', 0);
});
