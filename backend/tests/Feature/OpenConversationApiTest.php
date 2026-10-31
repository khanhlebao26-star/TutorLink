<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Support\ProgramApiFixtures;
use Tests\Support\TestActors;
use Tests\TestCase;

class OpenConversationApiTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private User $tutor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->customer = TestActors::customer();
        $this->tutor = ProgramApiFixtures::eligibleTutor();
    }

    public function test_creates_one_conversation_with_two_members_and_minimal_peer_data(): void
    {
        $requestId = (string) Str::uuid();

        $response = $this->actingAs($this->customer)->postJson(
            '/api/v1/conversations',
            ['peer_user_id' => $this->tutor->id],
            ['X-Request-ID' => $requestId],
        );

        $response
            ->assertCreated()
            ->assertHeader('X-Request-ID', $requestId)
            ->assertJsonPath('data.peer.id', $this->tutor->id)
            ->assertJsonPath('data.peer.display_name', $this->tutor->full_name)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.context_listing_id', null);

        $this->assertEqualsCanonicalizing(
            ['id', 'status', 'peer', 'context_listing_id'],
            array_keys($response->json('data')),
        );

        $this->assertEqualsCanonicalizing(
            ['id', 'display_name'],
            array_keys($response->json('data.peer')),
        );

        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('conversation_members', 2);
    }

    public function test_reverse_direction_reuses_the_same_pair_and_does_not_duplicate_members(): void
    {
        $first = $this->actingAs($this->customer)
            ->postJson('/api/v1/conversations', [
                'peer_user_id' => $this->tutor->id,
            ])
            ->assertCreated();

        $this->actingAs($this->tutor)
            ->postJson('/api/v1/conversations', [
                'peer_user_id' => $this->customer->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $first->json('data.id'))
            ->assertJsonPath('data.peer.id', $this->customer->id);

        $this->assertDatabaseCount('conversations', 1);
        $this->assertDatabaseCount('conversation_members', 2);
    }

    public function test_tutor_can_initiate_a_new_conversation(): void
    {
        $this->actingAs($this->tutor)
            ->postJson('/api/v1/conversations', [
                'peer_user_id' => $this->customer->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.peer.id', $this->customer->id);
    }

    public function test_context_is_frozen_on_reuse_and_reopen_and_history_is_preserved(): void
    {
        $firstListing = $this->publicListing($this->tutor);
        $secondListing = $this->publicListing($this->tutor);

        $id = $this->actingAs($this->customer)
            ->postJson('/api/v1/conversations', [
                'peer_user_id' => $this->tutor->id,
                'context_listing_id' => $firstListing,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->postJson('/api/v1/conversations', [
            'peer_user_id' => $this->tutor->id,
            'context_listing_id' => $secondListing,
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.context_listing_id', $firstListing);

        $messageId = DB::table('messages')->insertGetId([
            'conversation_id' => $id,
            'sender_id' => $this->customer->id,
            'client_message_id' => Str::uuid(),
            'message_type' => 'text',
            'body' => 'Existing history',
        ]);

        DB::table('conversation_members')
            ->where('conversation_id', $id)
            ->where('user_id', $this->customer->id)
            ->update(['last_read_message_id' => $messageId]);

        Conversation::findOrFail($id)->update([
            'status' => 'cancelled',
            'ended_at' => now(),
        ]);

        $this->postJson('/api/v1/conversations', [
            'peer_user_id' => $this->tutor->id,
            'context_listing_id' => $secondListing,
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.context_listing_id', $firstListing);

        $this->assertDatabaseHas('messages', [
            'id' => $messageId,
            'body' => 'Existing history',
        ]);

        $this->assertDatabaseHas('conversation_members', [
            'conversation_id' => $id,
            'user_id' => $this->customer->id,
            'last_read_message_id' => $messageId,
        ]);

        $this->assertDatabaseHas('conversations', [
            'id' => $id,
            'ended_at' => null,
        ]);

        $this->assertDatabaseCount('conversation_members', 2);
    }

    public function test_initial_null_context_stays_null_on_later_requests(): void
    {
        $id = $this->actingAs($this->customer)
            ->postJson('/api/v1/conversations', [
                'peer_user_id' => $this->tutor->id,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->postJson('/api/v1/conversations', [
            'peer_user_id' => $this->tutor->id,
            'context_listing_id' => $this->publicListing($this->tutor),
        ])
            ->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.context_listing_id', null);
    }

    public function test_reuse_still_validates_supplied_listing_ownership_and_visibility(): void
    {
        $id = $this->actingAs($this->customer)
            ->postJson('/api/v1/conversations', [
                'peer_user_id' => $this->tutor->id,
            ])
            ->assertCreated()
            ->json('data.id');

        $otherTutor = ProgramApiFixtures::eligibleTutor();
        $foreignListing = $this->publicListing($otherTutor);
        $inactiveListing = ProgramApiFixtures::listing($this->tutor);

        foreach ([$foreignListing, $inactiveListing, 999999999] as $listingId) {
            $this->postJson('/api/v1/conversations', [
                'peer_user_id' => $this->tutor->id,
                'context_listing_id' => $listingId,
            ])
                ->assertUnprocessable()
                ->assertJsonPath('code', 'context_listing_invalid');
        }

        $this->assertDatabaseCount('conversations', 1);

        $this->assertDatabaseHas('conversations', [
            'id' => $id,
            'context_listing_id' => null,
        ]);
    }

    public function test_hidden_catalog_or_moderation_hold_blocks_listing_context(): void
    {
        $listingId = $this->publicListing($this->tutor);

        $specializationId = DB::table('service_listings')
            ->where('id', $listingId)
            ->value('specialization_id');

        DB::table('specializations')
            ->where('id', $specializationId)
            ->update(['status' => 'inactive']);

        $this->actingAs($this->customer)
            ->postJson('/api/v1/conversations', [
                'peer_user_id' => $this->tutor->id,
                'context_listing_id' => $listingId,
            ])
            ->assertUnprocessable();

        DB::table('specializations')
            ->where('id', $specializationId)
            ->update(['status' => 'active']);

        $admin = TestActors::admin();

        DB::table('listing_moderation_holds')->insert([
            'listing_id' => $listingId,
            'hidden_by_admin_id' => $admin->id,
            'hidden_at' => now(),
            'hide_reason' => 'Test moderation',
            'hide_reason_code' => 'policy_violation',
        ]);

        $this->postJson('/api/v1/conversations', [
            'peer_user_id' => $this->tutor->id,
            'context_listing_id' => $listingId,
        ])
            ->assertConflict()
            ->assertJsonPath('code', 'listing_moderation_hold');

        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_authentication_actor_role_and_active_state_are_enforced(): void
    {
        $this->postJson('/api/v1/conversations', [
            'peer_user_id' => $this->tutor->id,
        ])->assertUnauthorized();

        $admin = TestActors::admin();

        $this->actingAs($admin)
            ->postJson('/api/v1/conversations', [
                'peer_user_id' => $this->tutor->id,
            ])
            ->assertForbidden();

        $this->customer->update(['status' => 'suspended']);

        $this->actingAs($this->customer)
            ->postJson('/api/v1/conversations', [
                'peer_user_id' => $this->tutor->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_self_same_role_admin_and_missing_peers_are_rejected(): void
    {
        $otherCustomer = TestActors::customer();
        $admin = TestActors::admin();

        foreach ([
            $this->customer->id,
            $otherCustomer->id,
            $admin->id,
            999999999,
        ] as $peerId) {
            $this->actingAs($this->customer)
                ->postJson('/api/v1/conversations', [
                    'peer_user_id' => $peerId,
                ])
                ->assertUnprocessable();
        }

        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_unknown_fields_and_invalid_ids_are_rejected(): void
    {
        foreach (['sender_id', 'direct_key', 'status'] as $field) {
            $this->actingAs($this->customer)
                ->postJson('/api/v1/conversations', [
                    'peer_user_id' => $this->tutor->id,
                    $field => 'forged',
                ])
                ->assertUnprocessable()
                ->assertJsonPath('code', 'validation_failed');
        }

        $this->postJson('/api/v1/conversations', [
            'peer_user_id' => 0,
        ])->assertUnprocessable();

        $this->postJson('/api/v1/conversations', [
            'peer_user_id' => $this->tutor->id,
            'context_listing_id' => [],
        ])->assertUnprocessable();

        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_unverified_unapproved_or_expired_tutors_cannot_open_contact(): void
    {
        $this->tutor->forceFill([
            'email_verified_at' => null,
        ])->save();

        $this->actingAs($this->customer)
            ->postJson('/api/v1/conversations', [
                'peer_user_id' => $this->tutor->id,
            ])
            ->assertForbidden();

        $this->tutor->forceFill([
            'email_verified_at' => now(),
        ])->save();

        $this->tutor->tutorProfile->update([
            'status' => 'submitted',
        ]);

        $this->postJson('/api/v1/conversations', [
            'peer_user_id' => $this->tutor->id,
        ])->assertForbidden();

        $this->tutor->tutorProfile->update([
            'status' => 'approved',
        ]);

        DB::table('verification_documents')
            ->where('tutor_profile_id', $this->tutor->tutorProfile->id)
            ->update(['expires_at' => now()->subMinute()]);

        $this->postJson('/api/v1/conversations', [
            'peer_user_id' => $this->tutor->id,
        ])->assertForbidden();

        $this->assertDatabaseCount('conversations', 0);
    }

    public function test_reopen_rechecks_eligibility_and_does_not_change_a_cancelled_chat_on_failure(): void
    {
        $id = $this->actingAs($this->customer)
            ->postJson('/api/v1/conversations', [
                'peer_user_id' => $this->tutor->id,
            ])
            ->assertCreated()
            ->json('data.id');

        Conversation::findOrFail($id)->update([
            'status' => 'cancelled',
            'ended_at' => now(),
        ]);

        $this->tutor->update(['status' => 'suspended']);

        $this->postJson('/api/v1/conversations', [
            'peer_user_id' => $this->tutor->id,
        ])->assertConflict();

        $this->assertDatabaseHas('conversations', [
            'id' => $id,
            'status' => 'cancelled',
        ]);

        $this->assertDatabaseCount('conversation_members', 2);
    }

    public function test_existing_invalid_membership_is_not_silently_repaired(): void
    {
        $id = $this->actingAs($this->customer)
            ->postJson('/api/v1/conversations', [
                'peer_user_id' => $this->tutor->id,
            ])
            ->assertCreated()
            ->json('data.id');

        DB::table('conversation_members')
            ->where('conversation_id', $id)
            ->where('user_id', $this->tutor->id)
            ->delete();

        $this->postJson('/api/v1/conversations', [
            'peer_user_id' => $this->tutor->id,
        ])
            ->assertConflict()
            ->assertJsonPath('code', 'conversation_membership_conflict');

        $this->assertDatabaseCount('conversation_members', 1);
    }

    public function test_member_write_failure_rolls_back_the_entire_conversation(): void
    {
        DB::listen(function (QueryExecuted $query): void {
            if (
                str_starts_with(
                    strtolower($query->sql),
                    'insert into "conversation_members"',
                )
            ) {
                throw new RuntimeException(
                    'Injected member insert failure.',
                );
            }
        });

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($this->customer)
                ->postJson('/api/v1/conversations', [
                    'peer_user_id' => $this->tutor->id,
                ]);

            $this->fail('Expected member insert failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame(
                'Injected member insert failure.',
                $exception->getMessage(),
            );
        }

        $this->assertDatabaseCount('conversations', 0);
        $this->assertDatabaseCount('conversation_members', 0);
    }

    private function publicListing(User $tutor): int
    {
        $id = ProgramApiFixtures::listing($tutor);

        DB::table('service_listings')
            ->where('id', $id)
            ->update(['status' => 'active']);

        return $id;
    }
}
