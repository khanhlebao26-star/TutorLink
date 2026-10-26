<?php

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\TestActors;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function makeProgramApiTutor(): User
{
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

    return $tutor->fresh('tutorProfile');
}

function makeProgramApiPayload(int $customerId, array $overrides = []): array
{
    return array_replace([
        'customer_id' => $customerId,
        'title' => 'English lessons',
        'goal' => 'Build confidence.',
        'level' => 'Intermediate',
        'delivery_mode' => 'online',
        'agreed_fee_minor' => 300000,
        'fee_unit' => 'session',
        'planned_session_count' => 8,
    ], $overrides);
}

/** @return array{program_id: int, tutor: User, customer: User} */
function makeProgramApiDraft(User $tutor, User $customer, array $overrides = []): array
{
    $now = now('UTC');
    $id = DB::table('programs')->insertGetId(array_replace([
        'owner_tutor_profile_id' => $tutor->tutorProfile->id,
        'source_listing_id' => null,
        'customer_id' => $customer->id,
        'version' => 1,
        'title' => 'English lessons',
        'goal' => 'Build confidence.',
        'level' => 'Intermediate',
        'delivery_mode' => 'online',
        'agreed_fee_minor' => 300000,
        'currency' => 'VND',
        'fee_unit' => 'session',
        'location_text' => null,
        'meeting_url' => null,
        'schedule_note' => null,
        'timezone' => 'Asia/Ho_Chi_Minh',
        'planned_session_count' => 8,
        'status' => 'draft',
        'offer_snapshot' => null,
        'snapshot_locked_at' => null,
        'activated_at' => null,
        'ends_at' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ], $overrides));

    return ['program_id' => $id, 'tutor' => $tutor, 'customer' => $customer];
}

function addPendingProgramApiInvitation(array $fixture, CarbonInterface $expiresAt): int
{
    $now = now('UTC');

    return DB::table('program_invitations')->insertGetId([
        'program_id' => $fixture['program_id'],
        'program_version' => 1,
        'invitee_user_id' => $fixture['customer']->id,
        'invited_by' => $fixture['tutor']->id,
        'status' => 'pending',
        'offer_snapshot' => json_encode([
            'program_id' => $fixture['program_id'],
            'target_customer_id' => $fixture['customer']->id,
            'source_listing_id' => null,
            'offer' => ['title' => 'English lessons'],
        ], JSON_THROW_ON_ERROR),
        'idempotency_key' => (string) Str::uuid(),
        'expires_at' => $expiresAt,
        'responded_at' => null,
        'accepted_at' => null,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
}

function makeProgramApiListing(User $tutor): int
{
    $now = now('UTC');
    $suffix = Str::lower(Str::random(8));
    $categoryId = DB::table('categories')->insertGetId([
        'name' => 'Languages',
        'slug' => 'languages-'.$suffix,
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

    return DB::table('service_listings')->insertGetId([
        'tutor_profile_id' => $tutor->tutorProfile->id,
        'specialization_id' => $specializationId,
        'slug' => 'english-conversation-'.$suffix,
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

function postProgramApiRequest(
    TestCase $testCase,
    User $actor,
    string $method,
    string $path,
    array $payload,
): TestResponse {
    $testCase->actingAs($actor, 'web');

    return $testCase->call(
        $method,
        $path,
        [],
        [],
        [],
        ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
        json_encode($payload, JSON_THROW_ON_ERROR),
    );
}

it('creates a Draft for an active Customer and records an audit entry', function () {
    $tutor = makeProgramApiTutor();
    $customer = TestActors::customer();

    $response = postProgramApiRequest(
        $this,
        $tutor,
        'POST',
        '/api/v1/programs',
        makeProgramApiPayload($customer->id),
    );

    $response->assertCreated()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.version', 1)
        ->assertJsonPath('data.customer_id', $customer->id)
        ->assertJsonPath('data.currency', 'VND')
        ->assertJsonPath('data.timezone', 'Asia/Ho_Chi_Minh')
        ->assertJsonPath('data.allowed_actions', ['edit', 'send_invitation', 'cancel']);

    $this->assertDatabaseHas('programs', [
        'id' => $response->json('data.id'),
        'owner_tutor_profile_id' => $tutor->tutorProfile->id,
        'customer_id' => $customer->id,
        'status' => 'draft',
        'version' => 1,
    ]);
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'program.create',
        'resource_id' => $response->json('data.id'),
    ]);
    $this->assertDatabaseCount('program_members', 0);
});

it('denies Program creation to a Customer', function () {
    $customer = TestActors::customer();
    $target = TestActors::customer();

    $response = postProgramApiRequest(
        $this,
        $customer,
        'POST',
        '/api/v1/programs',
        makeProgramApiPayload($target->id),
    );

    $response->assertForbidden()->assertJsonPath('code', 'tutor_required');
    $this->assertDatabaseCount('programs', 0);
});

it('rejects unsupported offer values and fields without creating a Program', function () {
    $tutor = makeProgramApiTutor();
    $customer = TestActors::customer();
    $payload = makeProgramApiPayload($customer->id, [
        'currency' => 'USD',
        'status' => 'active',
    ]);

    $response = postProgramApiRequest($this, $tutor, 'POST', '/api/v1/programs', $payload);

    $response->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors(['currency', 'status']);
    $this->assertDatabaseCount('programs', 0);
});

it('rejects a source Listing under moderation hold', function () {
    $tutor = makeProgramApiTutor();
    $customer = TestActors::customer();
    $admin = TestActors::admin();
    $listingId = makeProgramApiListing($tutor);
    DB::table('listing_moderation_holds')->insert([
        'listing_id' => $listingId,
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

    $response = postProgramApiRequest(
        $this,
        $tutor,
        'POST',
        '/api/v1/programs',
        makeProgramApiPayload($customer->id, ['source_listing_id' => $listingId]),
    );

    $response->assertConflict()->assertJsonPath('code', 'listing_moderation_hold');
    $this->assertDatabaseCount('programs', 0);
    $this->assertDatabaseCount('audit_logs', 0);
});

it('lists and shows only Programs visible to the actor', function () {
    $tutor = makeProgramApiTutor();
    $customer = TestActors::customer();
    $otherCustomer = TestActors::customer();
    $owned = makeProgramApiDraft($tutor, $customer);
    makeProgramApiDraft($tutor, $otherCustomer, ['title' => 'Another offer']);

    $this->actingAs($tutor, 'web');
    $list = $this->getJson('/api/v1/programs?per_page=1');
    $list->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.per_page', 1)
        ->assertJsonPath('data.0.allowed_actions', ['edit', 'send_invitation', 'cancel']);

    $this->getJson("/api/v1/programs/{$owned['program_id']}")
        ->assertOk()
        ->assertJsonPath('data.id', $owned['program_id']);

    $outsider = TestActors::tutor();
    $this->actingAs($outsider, 'web');
    $this->getJson("/api/v1/programs/{$owned['program_id']}")
        ->assertNotFound()
        ->assertJsonPath('code', 'program_not_found');
});

it('lets a Customer read a Program only after becoming an active member', function () {
    $tutor = makeProgramApiTutor();
    $customer = TestActors::customer();
    $fixture = makeProgramApiDraft($tutor, $customer, ['status' => 'active']);
    addPendingProgramApiInvitation($fixture, now('UTC')->addHours(2));

    $this->actingAs($customer, 'web');
    $this->getJson("/api/v1/programs/{$fixture['program_id']}")->assertNotFound();

    DB::table('program_members')->insert([
        [
            'program_id' => $fixture['program_id'],
            'user_id' => $customer->id,
            'member_role' => 'customer',
            'status' => 'active',
            'joined_at' => now('UTC'),
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ],
        [
            'program_id' => $fixture['program_id'],
            'user_id' => $tutor->id,
            'member_role' => 'tutor',
            'status' => 'active',
            'joined_at' => now('UTC'),
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ],
    ]);

    $this->getJson("/api/v1/programs/{$fixture['program_id']}")
        ->assertOk()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonPath('data.allowed_actions', []);
});

it('updates a Draft once and increments its version', function () {
    $tutor = makeProgramApiTutor();
    $customer = TestActors::customer();
    $fixture = makeProgramApiDraft($tutor, $customer);

    $response = postProgramApiRequest(
        $this,
        $tutor,
        'PATCH',
        "/api/v1/programs/{$fixture['program_id']}",
        ['expected_version' => 1, 'title' => 'Updated English lessons'],
    );

    $response->assertOk()
        ->assertJsonPath('data.title', 'Updated English lessons')
        ->assertJsonPath('data.version', 2)
        ->assertJsonPath('data.status', 'draft');
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'program.update',
        'resource_id' => $fixture['program_id'],
    ]);
});

it('rejects stale versions and unsupported mutation fields without changing the Draft', function () {
    $tutor = makeProgramApiTutor();
    $customer = TestActors::customer();
    $fixture = makeProgramApiDraft($tutor, $customer);

    $this->actingAs($tutor, 'web');
    postProgramApiRequest(
        $this,
        $tutor,
        'PATCH',
        "/api/v1/programs/{$fixture['program_id']}",
        ['expected_version' => 0, 'title' => 'Stale'],
    )->assertUnprocessable();

    postProgramApiRequest(
        $this,
        $tutor,
        'PATCH',
        "/api/v1/programs/{$fixture['program_id']}",
        ['expected_version' => 2, 'title' => 'Stale'],
    )->assertConflict()->assertJsonPath('code', 'stale_program_version');

    postProgramApiRequest(
        $this,
        $tutor,
        'PATCH',
        "/api/v1/programs/{$fixture['program_id']}",
        ['expected_version' => 1, 'status' => 'active'],
    )->assertUnprocessable()->assertJsonValidationErrors(['status']);

    $this->assertDatabaseHas('programs', [
        'id' => $fixture['program_id'],
        'title' => 'English lessons',
        'version' => 1,
        'status' => 'draft',
    ]);
    $this->assertDatabaseCount('audit_logs', 0);
});

it('keeps a Draft locked while an Invitation is pending and unexpired', function () {
    $tutor = makeProgramApiTutor();
    $customer = TestActors::customer();
    $fixture = makeProgramApiDraft($tutor, $customer);
    addPendingProgramApiInvitation($fixture, now('UTC')->addHour());

    $response = postProgramApiRequest(
        $this,
        $tutor,
        'PATCH',
        "/api/v1/programs/{$fixture['program_id']}",
        ['expected_version' => 1, 'title' => 'Blocked edit'],
    );

    $response->assertConflict()->assertJsonPath('code', 'program_offer_locked');
    $this->assertDatabaseHas('programs', [
        'id' => $fixture['program_id'],
        'title' => 'English lessons',
        'version' => 1,
    ]);
    $this->assertDatabaseCount('audit_logs', 0);
});

it('expires an overdue Invitation and edits the Draft in one transaction', function () {
    $this->travelTo('2026-10-27 12:00:00 UTC');
    $tutor = makeProgramApiTutor();
    $customer = TestActors::customer();
    $fixture = makeProgramApiDraft($tutor, $customer);
    DB::table('programs')->where('id', $fixture['program_id'])->update([
        'offer_snapshot' => json_encode(['program_id' => $fixture['program_id']], JSON_THROW_ON_ERROR),
        'snapshot_locked_at' => now('UTC')->subDay(),
    ]);
    $invitationId = addPendingProgramApiInvitation($fixture, now('UTC')->subSecond());

    $response = postProgramApiRequest(
        $this,
        $tutor,
        'PATCH',
        "/api/v1/programs/{$fixture['program_id']}",
        ['expected_version' => 1, 'title' => 'After expiry'],
    );

    $response->assertOk()->assertJsonPath('data.title', 'After expiry')->assertJsonPath('data.version', 2);
    $this->assertDatabaseHas('program_invitations', ['id' => $invitationId, 'status' => 'expired']);
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'invitation.expire',
        'resource_id' => $invitationId,
    ]);
    $this->assertDatabaseHas('notification_outbox', [
        'user_id' => $tutor->id,
        'type' => 'invitation_expired',
        'event_key' => "invitation:{$invitationId}:expired",
    ]);
    $this->assertDatabaseHas('programs', [
        'id' => $fixture['program_id'],
        'offer_snapshot' => null,
        'snapshot_locked_at' => null,
    ]);
    $this->assertDatabaseCount('audit_logs', 2);
});

it('rolls back auto-expiry when the PATCH version is stale', function () {
    $this->travelTo('2026-10-27 12:00:00 UTC');
    $tutor = makeProgramApiTutor();
    $customer = TestActors::customer();
    $fixture = makeProgramApiDraft($tutor, $customer);
    $invitationId = addPendingProgramApiInvitation($fixture, now('UTC')->subSecond());

    $response = postProgramApiRequest(
        $this,
        $tutor,
        'PATCH',
        "/api/v1/programs/{$fixture['program_id']}",
        ['expected_version' => 2, 'title' => 'Stale edit'],
    );

    $response->assertConflict()->assertJsonPath('code', 'stale_program_version');
    $this->assertDatabaseHas('program_invitations', ['id' => $invitationId, 'status' => 'pending']);
    $this->assertDatabaseHas('programs', [
        'id' => $fixture['program_id'],
        'title' => 'English lessons',
        'version' => 1,
    ]);
    $this->assertDatabaseCount('audit_logs', 0);
    $this->assertDatabaseCount('notification_outbox', 0);
});

it('returns 403 when an active Program member attempts to edit the Draft', function () {
    $tutor = makeProgramApiTutor();
    $customer = TestActors::customer();
    $fixture = makeProgramApiDraft($tutor, $customer, ['status' => 'active']);
    DB::table('program_members')->insert([
        'program_id' => $fixture['program_id'],
        'user_id' => $customer->id,
        'member_role' => 'customer',
        'status' => 'active',
        'joined_at' => now('UTC'),
        'created_at' => now('UTC'),
        'updated_at' => now('UTC'),
    ]);

    $response = postProgramApiRequest(
        $this,
        $customer,
        'PATCH',
        "/api/v1/programs/{$fixture['program_id']}",
        ['expected_version' => 1, 'title' => 'No'],
    );

    $response->assertForbidden()->assertJsonPath('code', 'program_update_forbidden');
});

it('rejects editing a Program that is no longer a Draft', function () {
    $tutor = makeProgramApiTutor();
    $customer = TestActors::customer();
    $fixture = makeProgramApiDraft($tutor, $customer, ['status' => 'active']);

    $response = postProgramApiRequest(
        $this,
        $tutor,
        'PATCH',
        "/api/v1/programs/{$fixture['program_id']}",
        ['expected_version' => 1, 'title' => 'No'],
    );

    $response->assertConflict()->assertJsonPath('code', 'program_not_draft');
});

it('returns an empty list to an active Customer without Program membership', function () {
    $customer = TestActors::customer();
    $this->actingAs($customer, 'web');

    $this->getJson('/api/v1/programs')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('denies Program listing to an Admin', function () {
    $this->actingAs(TestActors::admin(), 'web');

    $this->getJson('/api/v1/programs')
        ->assertForbidden()
        ->assertJsonPath('code', 'program_list_forbidden');
});
