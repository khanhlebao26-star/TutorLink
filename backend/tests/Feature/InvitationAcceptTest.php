<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\TestActors;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * @return array{customer: User, tutor: User, program_id: int, invitation_id: int, listing_id: ?int}
 */
function makeInvitationAcceptFixture(bool $withListing = false): array
{
    $customer = TestActors::customer();
    $tutor = TestActors::tutor();
    $now = now('UTC');

    DB::table('tutor_profiles')
        ->where('id', $tutor->tutorProfile->id)
        ->update(['status' => 'approved']);

    $fileId = DB::table('files')->insertGetId([
        'owner_id' => $tutor->id,
        'disk' => 'local',
        'object_key' => 'tests/'.Str::uuid().'.pdf',
        'original_name' => 'identity.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 1024,
        'visibility' => 'private',
        'checksum_sha256' => null,
        'scan_status' => 'clean',
        'purpose' => 'verification_document',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('verification_documents')->insert([
        'tutor_profile_id' => $tutor->tutorProfile->id,
        'file_id' => $fileId,
        'document_type' => 'identity_card',
        'status' => 'approved',
        'reviewed_by' => null,
        'reviewed_at' => $now,
        'expires_at' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $listingId = null;

    if ($withListing) {
        $categoryId = DB::table('categories')->insertGetId([
            'name' => 'Languages',
            'slug' => 'languages-'.Str::lower(Str::random(8)),
            'status' => 'active',
            'sort_order' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $subcategoryId = DB::table('subcategories')->insertGetId([
            'category_id' => $categoryId,
            'name' => 'English',
            'slug' => 'english',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $specializationId = DB::table('specializations')->insertGetId([
            'subcategory_id' => $subcategoryId,
            'name' => 'Conversation',
            'slug' => 'conversation',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $listingId = DB::table('service_listings')->insertGetId([
            'tutor_profile_id' => $tutor->tutorProfile->id,
            'specialization_id' => $specializationId,
            'slug' => 'english-conversation-'.Str::lower(Str::random(8)),
            'title' => 'English conversation',
            'description' => 'Practice speaking.',
            'delivery_mode' => 'online',
            'city' => null,
            'district' => null,
            'price_from_minor' => 300000,
            'currency' => 'VND',
            'price_unit' => 'session',
            'status' => 'inactive',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    $programId = DB::table('programs')->insertGetId([
        'owner_tutor_profile_id' => $tutor->tutorProfile->id,
        'source_listing_id' => $listingId,
        'customer_id' => $customer->id,
        'version' => 2,
        'title' => 'English lessons',
        'goal' => 'Build confidence.',
        'level' => 'Intermediate',
        'delivery_mode' => 'online',
        'agreed_fee_minor' => 300000,
        'currency' => 'VND',
        'fee_unit' => 'session',
        'location_text' => null,
        'meeting_url' => 'https://example.test/lesson',
        'schedule_note' => 'Weekdays',
        'timezone' => 'Asia/Ho_Chi_Minh',
        'planned_session_count' => 8,
        'status' => 'draft',
        'offer_snapshot' => null,
        'snapshot_locked_at' => null,
        'activated_at' => null,
        'ends_at' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $offerSnapshot = [
        'program_id' => $programId,
        'target_customer_id' => $customer->id,
        'source_listing_id' => $listingId,
        'offer' => [
            'title' => 'English lessons',
            'delivery_mode' => 'online',
            'agreed_fee_minor' => 300000,
            'currency' => 'VND',
            'fee_unit' => 'session',
        ],
    ];
    $invitationId = DB::table('program_invitations')->insertGetId([
        'program_id' => $programId,
        'program_version' => 2,
        'invitee_user_id' => $customer->id,
        'invited_by' => $tutor->id,
        'status' => 'pending',
        'offer_snapshot' => json_encode($offerSnapshot, JSON_THROW_ON_ERROR),
        'idempotency_key' => (string) Str::uuid(),
        'expires_at' => $now->copy()->addHours(72),
        'responded_at' => null,
        'accepted_at' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return [
        'customer' => $customer,
        'tutor' => $tutor,
        'program_id' => $programId,
        'invitation_id' => $invitationId,
        'listing_id' => $listingId,
    ];
}

function postAcceptInvitation(
    TestCase $testCase,
    User $customer,
    int $invitationId,
    ?string $idempotencyKey = null,
    string $body = '{}',
): TestResponse {
    $testCase->actingAs($customer, 'web');

    return $testCase->call(
        'POST',
        "/api/v1/invitations/{$invitationId}/accept",
        [],
        [],
        [],
        [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_IDEMPOTENCY_KEY' => $idempotencyKey ?? (string) Str::uuid(),
        ],
        $body,
    );
}

/** @return array<string, mixed> */
function makeSecondInvitationForAcceptanceFixture(array $fixture): array
{
    $customer = $fixture['customer'];
    $tutor = $fixture['tutor'];
    $now = now('UTC');
    $programId = DB::table('programs')->insertGetId([
        'owner_tutor_profile_id' => $tutor->tutorProfile->id,
        'source_listing_id' => null,
        'customer_id' => $customer->id,
        'version' => 1,
        'title' => 'Second English offer',
        'goal' => null,
        'level' => null,
        'delivery_mode' => 'online',
        'agreed_fee_minor' => 300000,
        'currency' => 'VND',
        'fee_unit' => 'session',
        'location_text' => null,
        'meeting_url' => null,
        'schedule_note' => null,
        'timezone' => 'Asia/Ho_Chi_Minh',
        'planned_session_count' => null,
        'status' => 'draft',
        'offer_snapshot' => null,
        'snapshot_locked_at' => null,
        'activated_at' => null,
        'ends_at' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
    $invitationId = DB::table('program_invitations')->insertGetId([
        'program_id' => $programId,
        'program_version' => 1,
        'invitee_user_id' => $customer->id,
        'invited_by' => $tutor->id,
        'status' => 'pending',
        'offer_snapshot' => json_encode([
            'program_id' => $programId,
            'target_customer_id' => $customer->id,
            'source_listing_id' => null,
            'offer' => ['title' => 'Second English offer'],
        ], JSON_THROW_ON_ERROR),
        'idempotency_key' => (string) Str::uuid(),
        'expires_at' => $now->copy()->addHours(72),
        'responded_at' => null,
        'accepted_at' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    return ['program_id' => $programId, 'invitation_id' => $invitationId];
}

it('activates the Program once and replays the same response for an idempotent retry', function () {
    $fixture = makeInvitationAcceptFixture();
    $key = (string) Str::uuid();
    $first = postAcceptInvitation($this, $fixture['customer'], $fixture['invitation_id'], $key);
    $first->assertOk()
        ->assertJsonPath('data.program.status', 'active')
        ->assertJsonPath('data.invitation.status', 'accepted')
        ->assertJsonPath('data.invitation.offer_snapshot.target_customer_id', $fixture['customer']->id);

    $retry = postAcceptInvitation($this, $fixture['customer'], $fixture['invitation_id'], $key);
    $retry->assertOk()->assertExactJson($first->json());

    expect($first->headers->get('X-Request-ID'))->toMatch('/^[0-9a-f-]{36}$/');
    $this->assertDatabaseHas('programs', ['id' => $fixture['program_id'], 'status' => 'active']);
    $this->assertDatabaseHas('program_invitations', ['id' => $fixture['invitation_id'], 'status' => 'accepted']);
    $this->assertDatabaseCount('program_members', 2);
    $this->assertDatabaseCount('audit_logs', 1);
    $this->assertDatabaseCount('idempotency_receipts', 1);
    $this->assertDatabaseHas('notification_outbox', [
        'user_id' => $fixture['tutor']->id,
        'type' => 'invitation_accepted',
        'event_key' => "invitation:{$fixture['invitation_id']}:accepted",
    ]);
    $outbox = DB::table('notification_outbox')
        ->where('event_key', "invitation:{$fixture['invitation_id']}:accepted")
        ->first();
    expect(json_decode($outbox->payload, true, 512, JSON_THROW_ON_ERROR))->toBe([
        'deep_link' => "/programs/{$fixture['program_id']}",
        'program_id' => $fixture['program_id'],
    ]);
});

it('returns 404 without mutations when another Customer accepts the Invitation', function () {
    $fixture = makeInvitationAcceptFixture();
    $outsider = TestActors::customer();
    $response = postAcceptInvitation($this, $outsider, $fixture['invitation_id']);
    $response->assertNotFound()->assertJsonPath('code', 'invitation_not_found');

    $this->assertDatabaseHas('programs', ['id' => $fixture['program_id'], 'status' => 'draft']);
    $this->assertDatabaseHas('program_invitations', ['id' => $fixture['invitation_id'], 'status' => 'pending']);
    $this->assertDatabaseCount('program_members', 0);
    $this->assertDatabaseCount('audit_logs', 0);
    $this->assertDatabaseCount('notification_outbox', 0);
});

it('returns 409 at the exact expiry time without activating the Program', function () {
    $this->travelTo('2026-10-27 12:00:00 UTC');
    $fixture = makeInvitationAcceptFixture();
    DB::table('program_invitations')
        ->where('id', $fixture['invitation_id'])
        ->update(['expires_at' => now('UTC')]);
    $response = postAcceptInvitation($this, $fixture['customer'], $fixture['invitation_id']);
    $response->assertConflict()->assertJsonPath('code', 'invitation_expired');

    $this->assertDatabaseHas('programs', ['id' => $fixture['program_id'], 'status' => 'draft']);
    $this->assertDatabaseHas('program_invitations', ['id' => $fixture['invitation_id'], 'status' => 'pending']);
    $this->assertDatabaseCount('program_members', 0);
    $this->assertDatabaseCount('audit_logs', 0);
    $this->assertDatabaseCount('notification_outbox', 0);
});

it('accepts an offer after its source Listing becomes inactive when no hold exists', function () {
    $fixture = makeInvitationAcceptFixture(withListing: true);
    $response = postAcceptInvitation($this, $fixture['customer'], $fixture['invitation_id']);
    $response->assertOk()->assertJsonPath('data.program.status', 'active');

    $this->assertDatabaseHas('service_listings', ['id' => $fixture['listing_id'], 'status' => 'inactive']);
    $this->assertDatabaseHas('program_invitations', ['id' => $fixture['invitation_id'], 'status' => 'accepted']);
    $this->assertDatabaseCount('program_members', 2);
});

it('returns 409 when the Program version no longer matches the Invitation', function () {
    $fixture = makeInvitationAcceptFixture();
    DB::table('programs')
        ->where('id', $fixture['program_id'])
        ->update(['version' => 3]);
    $response = postAcceptInvitation($this, $fixture['customer'], $fixture['invitation_id']);
    $response->assertConflict()->assertJsonPath('code', 'invitation_version_stale');

    $this->assertDatabaseHas('programs', ['id' => $fixture['program_id'], 'status' => 'draft']);
    $this->assertDatabaseHas('program_invitations', ['id' => $fixture['invitation_id'], 'status' => 'pending']);
    $this->assertDatabaseCount('program_members', 0);
    $this->assertDatabaseCount('audit_logs', 0);
    $this->assertDatabaseCount('notification_outbox', 0);
});

it('returns 409 when the required identity document expires at the current instant', function () {
    $this->travelTo('2026-10-27 12:00:00 UTC');
    $fixture = makeInvitationAcceptFixture();
    DB::table('verification_documents')
        ->where('tutor_profile_id', $fixture['tutor']->tutorProfile->id)
        ->update(['expires_at' => now('UTC')]);
    $response = postAcceptInvitation($this, $fixture['customer'], $fixture['invitation_id']);
    $response->assertConflict()->assertJsonPath('code', 'tutor_ineligible');

    $this->assertDatabaseHas('programs', ['id' => $fixture['program_id'], 'status' => 'draft']);
    $this->assertDatabaseCount('program_members', 0);
    $this->assertDatabaseCount('audit_logs', 0);
    $this->assertDatabaseCount('notification_outbox', 0);
});

it('returns 409 when the stored snapshot names a different Program', function () {
    $fixture = makeInvitationAcceptFixture();
    $row = DB::table('program_invitations')->where('id', $fixture['invitation_id'])->first();
    $snapshot = json_decode($row->offer_snapshot, true, 512, JSON_THROW_ON_ERROR);
    $snapshot['program_id'] = $fixture['program_id'] + 100;
    DB::table('program_invitations')
        ->where('id', $fixture['invitation_id'])
        ->update(['offer_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR)]);
    $response = postAcceptInvitation($this, $fixture['customer'], $fixture['invitation_id']);
    $response->assertConflict()->assertJsonPath('code', 'invitation_snapshot_invalid');

    $this->assertDatabaseHas('programs', ['id' => $fixture['program_id'], 'status' => 'draft']);
    $this->assertDatabaseCount('program_members', 0);
    $this->assertDatabaseCount('audit_logs', 0);
    $this->assertDatabaseCount('notification_outbox', 0);
});

it('returns 409 while the source Listing has an active moderation hold', function () {
    $fixture = makeInvitationAcceptFixture(withListing: true);
    $admin = TestActors::admin();
    DB::table('listing_moderation_holds')->insert([
        'listing_id' => $fixture['listing_id'],
        'hidden_by_admin_id' => $admin->id,
        'hidden_at' => now('UTC'),
        'hide_reason' => 'Review in progress.',
        'hide_reason_code' => 'policy_review',
        'reactivate_on_restore' => false,
        'restored_by_admin_id' => null,
        'restored_at' => null,
        'restore_reason' => null,
        'restore_reason_code' => null,
    ]);
    $response = postAcceptInvitation($this, $fixture['customer'], $fixture['invitation_id']);
    $response->assertConflict()->assertJsonPath('code', 'listing_moderation_hold');

    $this->assertDatabaseHas('service_listings', ['id' => $fixture['listing_id'], 'status' => 'inactive']);
    $this->assertDatabaseHas('programs', ['id' => $fixture['program_id'], 'status' => 'draft']);
    $this->assertDatabaseHas('program_invitations', ['id' => $fixture['invitation_id'], 'status' => 'pending']);
    $this->assertDatabaseCount('program_members', 0);
    $this->assertDatabaseCount('audit_logs', 0);
    $this->assertDatabaseCount('notification_outbox', 0);
});

it('returns 409 when an Idempotency-Key is reused for a different Invitation', function () {
    $fixture = makeInvitationAcceptFixture();
    $secondInvitation = makeSecondInvitationForAcceptanceFixture($fixture);
    $key = (string) Str::uuid();
    postAcceptInvitation($this, $fixture['customer'], $fixture['invitation_id'], $key)->assertOk();

    $response = postAcceptInvitation($this, $fixture['customer'], $secondInvitation['invitation_id'], $key);
    $response->assertConflict()->assertJsonPath('code', 'idempotency_key_reused');

    $this->assertDatabaseHas('programs', ['id' => $secondInvitation['program_id'], 'status' => 'draft']);
    $this->assertDatabaseCount('program_members', 2);
    $this->assertDatabaseCount('audit_logs', 1);
    $this->assertDatabaseCount('idempotency_receipts', 1);
    $this->assertDatabaseCount('notification_outbox', 1);
});

it('returns 422 without mutating state when the request has an unsupported body', function () {
    $fixture = makeInvitationAcceptFixture();
    $response = postAcceptInvitation(
        $this,
        $fixture['customer'],
        $fixture['invitation_id'],
        body: '{"force":true}',
    );
    $response->assertUnprocessable()->assertJsonPath('code', 'invalid_request_body');

    $this->assertDatabaseHas('programs', ['id' => $fixture['program_id'], 'status' => 'draft']);
    $this->assertDatabaseCount('program_members', 0);
    $this->assertDatabaseCount('idempotency_receipts', 0);
});

it('returns 422 when Idempotency-Key is not a UUID', function () {
    $fixture = makeInvitationAcceptFixture();
    $response = postAcceptInvitation(
        $this,
        $fixture['customer'],
        $fixture['invitation_id'],
        'invalid-key',
    );
    $response->assertUnprocessable()->assertJsonPath('code', 'invalid_idempotency_key');

    $this->assertDatabaseHas('programs', ['id' => $fixture['program_id'], 'status' => 'draft']);
    $this->assertDatabaseCount('program_members', 0);
    $this->assertDatabaseCount('idempotency_receipts', 0);
});

it('returns 401 with the API error envelope when no Customer is authenticated', function () {
    $response = $this->call(
        'POST',
        '/api/v1/invitations/1/accept',
        [],
        [],
        [],
        ['HTTP_ACCEPT' => 'application/json'],
        '{}',
    );

    $response->assertUnauthorized()
        ->assertJsonPath('code', 'unauthenticated')
        ->assertJsonPath('request_id', $response->headers->get('X-Request-ID'));
});
