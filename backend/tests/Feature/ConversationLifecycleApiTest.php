<?php

use App\Actions\Messaging\SyncConversationLifecycle;
use App\Actions\Programs\TransitionProgram;
use App\Models\Conversation;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\MessageSendFixtures as Fixtures;
use Tests\Support\ProgramApiFixtures;
use Tests\Support\TestActors;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo(
        CarbonImmutable::parse('2026-10-30T03:00:00.123456Z')
    );

    $this->customer = TestActors::customer();
    $this->tutor = ProgramApiFixtures::eligibleTutor();

    $this->chat = Fixtures::conversation(
        $this->customer,
        $this->tutor,
    );

    $this->messageId = Fixtures::seed(
        $this->chat,
        $this->customer,
    );

    $this->membersBefore = DB::table('conversation_members')
        ->where('conversation_id', $this->chat)
        ->orderBy('user_id')
        ->get()
        ->toJson();

    $this->programId = ProgramApiFixtures::draft(
        $this->tutor,
        $this->customer,
        ['status' => 'active'],
    );

    ProgramApiFixtures::addMember(
        $this->programId,
        $this->tutor,
        'tutor',
    );

    ProgramApiFixtures::addMember(
        $this->programId,
        $this->customer,
        'customer',
    );
});

afterEach(function (): void {
    $this->travelBack();
});

test('completion closes only when the pair has no ongoing relationship', function (?string $otherStatus, ?int $invitationSeconds, string $expectedStatus): void {
    if ($otherStatus !== null) {
        $otherProgram = ProgramApiFixtures::draft(
            $this->tutor,
            $this->customer,
            ['status' => $otherStatus],
        );

        if (in_array($otherStatus, ['active', 'paused'], true)) {
            ProgramApiFixtures::addMember(
                $otherProgram,
                $this->tutor,
                'tutor',
            );

            ProgramApiFixtures::addMember(
                $otherProgram,
                $this->customer,
                'customer',
            );
        }
    }

    if ($invitationSeconds !== null) {
        $draft = ProgramApiFixtures::draft(
            $this->tutor,
            $this->customer,
        );

        ProgramApiFixtures::invitation(
            $draft,
            $this->tutor,
            $this->customer,
            now('UTC')
                ->addSeconds($invitationSeconds)
                ->format('Y-m-d H:i:s.uP'),
        );
    }

    $this->actingAs($this->tutor)
        ->postJson(
            '/api/v1/programs/'.$this->programId.'/complete'
        )
        ->assertOk();

    $this->assertDatabaseHas('programs', [
        'id' => $this->programId,
        'status' => 'completed',
    ]);

    $this->assertDatabaseHas('conversations', [
        'id' => $this->chat,
        'status' => $expectedStatus,
    ]);

    expect(
        Conversation::findOrFail($this->chat)->ended_at !== null
    )->toBe($expectedStatus === 'cancelled');

    expect(
        DB::table('conversation_members')
            ->where('conversation_id', $this->chat)
            ->orderBy('user_id')
            ->get()
            ->toJson()
    )->toBe($this->membersBefore);

    $this->assertDatabaseCount('messages', 1);
    $this->assertDatabaseHas('messages', [
        'id' => $this->messageId,
        'body' => 'Existing text',
    ]);
})->with([
    'no ongoing relationship' => [null, null, 'cancelled'],
    'another active Program' => ['active', null, 'active'],
    'another paused Program' => ['paused', null, 'active'],
    'another Draft only' => ['draft', null, 'cancelled'],
    'another completed Program' => ['completed', null, 'cancelled'],
    'another cancelled Program' => ['cancelled', null, 'cancelled'],
    'unexpired pending Invitation' => [null, 60, 'active'],
    'Invitation at expiry boundary' => [null, 0, 'cancelled'],
    'expired pending Invitation' => [null, -1, 'cancelled'],
]);

test('only an eligible contact request reopens the same conversation', function (): void {
    $this->actingAs($this->tutor)
        ->postJson(
            '/api/v1/programs/'.$this->programId.'/complete'
        )
        ->assertOk();

    $historyUrl = '/api/v1/conversations/'
        .$this->chat.'/messages';

    $this->actingAs($this->customer)
        ->getJson($historyUrl)
        ->assertOk();

    $this->assertDatabaseHas('conversations', [
        'id' => $this->chat,
        'status' => 'cancelled',
    ]);

    $this->postJson($historyUrl, Fixtures::payload())
        ->assertConflict();

    $this->tutor->update(['status' => 'suspended']);

    $this->postJson('/api/v1/conversations', [
        'peer_user_id' => $this->tutor->id,
    ])->assertConflict();

    $this->assertDatabaseHas('conversations', [
        'id' => $this->chat,
        'status' => 'cancelled',
    ]);

    $this->tutor->update(['status' => 'active']);

    $this->postJson('/api/v1/conversations', [
        'peer_user_id' => $this->tutor->id,
    ])
        ->assertOk()
        ->assertJsonPath('data.id', $this->chat);

    $this->assertDatabaseHas('conversations', [
        'id' => $this->chat,
        'status' => 'active',
        'ended_at' => null,
        'context_listing_id' => null,
    ]);

    expect(
        DB::table('conversation_members')
            ->where('conversation_id', $this->chat)
            ->orderBy('user_id')
            ->get()
            ->toJson()
    )->toBe($this->membersBefore);

    $this->assertDatabaseCount('messages', 1);
});

test('lazy invitation expiry respects remaining active programs', function (bool $hasActiveProgram): void {
    ProgramApiFixtures::draft(
        $this->tutor,
        $this->customer,
        ['status' => 'completed'],
    );

    if (! $hasActiveProgram) {
        DB::table('programs')
            ->where('id', $this->programId)
            ->update(['status' => 'completed']);
    }

    $draft = ProgramApiFixtures::draft(
        $this->tutor,
        $this->customer,
    );

    $invitation = ProgramApiFixtures::invitation(
        $draft,
        $this->tutor,
        $this->customer,
        now('UTC')->subSecond()->format('Y-m-d H:i:s.uP'),
    );

    $this->actingAs($this->tutor)
        ->patchJson('/api/v1/programs/'.$draft, [
            'expected_version' => 1,
            'title' => 'Updated after invitation expiry',
        ])
        ->assertOk();

    $this->assertDatabaseHas('program_invitations', [
        'id' => $invitation,
        'status' => 'expired',
    ]);

    $this->assertDatabaseHas('conversations', [
        'id' => $this->chat,
        'status' => $hasActiveProgram ? 'active' : 'cancelled',
    ]);

    expect(
        Conversation::findOrFail($this->chat)->ended_at !== null
    )->toBe(! $hasActiveProgram);

    expect(
        DB::table('conversation_members')
            ->where('conversation_id', $this->chat)
            ->orderBy('user_id')
            ->get()
            ->toJson()
    )->toBe($this->membersBefore);

    $this->assertDatabaseCount('messages', 1);
    $this->assertDatabaseHas('messages', [
        'id' => $this->messageId,
        'body' => 'Existing text',
    ]);
})->with([
    'no active Program remains' => [false],
    'another active Program remains' => [true],
]);

test('replacing an expired invitation does not close the conversation', function (): void {
    DB::table('programs')
        ->where('id', $this->programId)
        ->update(['status' => 'completed']);

    $draft = ProgramApiFixtures::draft(
        $this->tutor,
        $this->customer,
    );

    $oldInvitation = ProgramApiFixtures::invitation(
        $draft,
        $this->tutor,
        $this->customer,
        now('UTC')->subSecond()->format('Y-m-d H:i:s.uP'),
    );

    $this->actingAs($this->tutor)
        ->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/programs/'.$draft.'/invitations', [
            'expected_version' => 1,
        ])
        ->assertCreated();

    $this->assertDatabaseHas('program_invitations', [
        'id' => $oldInvitation,
        'status' => 'expired',
    ]);

    $newInvitation = DB::table('program_invitations')
        ->where('program_id', $draft)
        ->where('status', 'pending')
        ->first();

    expect($newInvitation)->not->toBeNull();
    expect((int) $newInvitation->id)->not->toBe($oldInvitation);

    expect(
        CarbonImmutable::parse($newInvitation->expires_at)
            ->greaterThan(now('UTC'))
    )->toBeTrue();

    $this->assertDatabaseHas('conversations', [
        'id' => $this->chat,
        'status' => 'active',
        'ended_at' => null,
    ]);

    expect(
        DB::table('conversation_members')
            ->where('conversation_id', $this->chat)
            ->orderBy('user_id')
            ->get()
            ->toJson()
    )->toBe($this->membersBefore);

    $this->assertDatabaseCount('messages', 1);
});

test('failure after conversation closure rolls back all lifecycle writes', function (): void {
    $chatBefore = (array) DB::table('conversations')
        ->where('id', $this->chat)
        ->first();

    $auditCount = DB::table('audit_logs')->count();
    $outboxCount = DB::table('notification_outbox')->count();

    $realSync = new SyncConversationLifecycle;

    $this->mock(SyncConversationLifecycle::class)
        ->shouldReceive('execute')
        ->once()
        ->andReturnUsing(function (int $tutorProfileId, int $customerId) use ($realSync): bool {
            expect(
                $realSync->execute($tutorProfileId, $customerId)
            )->toBeTrue();

            expect(
                DB::table('conversations')
                    ->where('id', $this->chat)
                    ->value('status')
            )->toBe('cancelled');

            throw new RuntimeException(
                'Injected failure after conversation closure.'
            );
        });

    expect(fn () => app(TransitionProgram::class)->execute(
        $this->tutor,
        $this->programId,
        'complete',
        (string) Str::uuid(),
    ))->toThrow(
        RuntimeException::class,
        'Injected failure after conversation closure.',
    );

    $this->assertDatabaseHas('programs', [
        'id' => $this->programId,
        'status' => 'active',
        'ends_at' => null,
    ]);

    expect(
        (array) DB::table('conversations')
            ->where('id', $this->chat)
            ->first()
    )->toBe($chatBefore);

    expect(
        DB::table('conversation_members')
            ->where('conversation_id', $this->chat)
            ->orderBy('user_id')
            ->get()
            ->toJson()
    )->toBe($this->membersBefore);

    $this->assertDatabaseCount('audit_logs', $auditCount);
    $this->assertDatabaseCount('notification_outbox', $outboxCount);
    $this->assertDatabaseCount('messages', 1);

    $this->assertDatabaseHas('messages', [
        'id' => $this->messageId,
        'body' => 'Existing text',
    ]);
});

test('admin suspension preserves conversation state and read cursors', function (string $target, string $chatStatus): void {
    DB::table('conversations')
        ->where('id', $this->chat)
        ->update([
            'status' => $chatStatus,
            'ended_at' => $chatStatus === 'cancelled'
                ? now('UTC')
                : null,
        ]);

    DB::table('conversation_members')
        ->where('conversation_id', $this->chat)
        ->where('user_id', $this->customer->id)
        ->update([
            'last_read_message_id' => $this->messageId,
            'last_read_at' => now('UTC'),
        ]);

    $chatBefore = (array) DB::table('conversations')
        ->where('id', $this->chat)
        ->first();

    $membersBefore = DB::table('conversation_members')
        ->where('conversation_id', $this->chat)
        ->orderBy('user_id')
        ->get()
        ->toJson();

    $messageBefore = (array) DB::table('messages')
        ->where('id', $this->messageId)
        ->first();

    $url = $target === 'account'
        ? '/api/v1/admin/users/'.$this->tutor->id.'/suspend'
        : '/api/v1/admin/tutor-profiles/'
        .$this->tutor->tutorProfile->id.'/suspend';

    $this->actingAs(TestActors::admin())
        ->postJson($url, [
            'reason' => 'K03.9 lifecycle suspension verification.',
        ])
        ->assertOk();

    $this->assertDatabaseHas('users', [
        'id' => $this->tutor->id,
        'status' => 'suspended',
    ]);

    if ($target === 'profile') {
        $this->assertDatabaseHas('tutor_profiles', [
            'id' => $this->tutor->tutorProfile->id,
            'status' => 'suspended',
        ]);
    }

    expect(
        (array) DB::table('conversations')
            ->where('id', $this->chat)
            ->first()
    )->toBe($chatBefore);

    expect(
        DB::table('conversation_members')
            ->where('conversation_id', $this->chat)
            ->orderBy('user_id')
            ->get()
            ->toJson()
    )->toBe($membersBefore);

    expect(
        (array) DB::table('messages')
            ->where('id', $this->messageId)
            ->first()
    )->toBe($messageBefore);

    $historyUrl = '/api/v1/conversations/'
        .$this->chat.'/messages';

    $this->actingAs($this->customer->fresh())
        ->getJson($historyUrl)
        ->assertOk();

    $this->actingAs($this->tutor->fresh())
        ->getJson($historyUrl)
        ->assertForbidden();
})->with([
    'account suspension, active chat' => ['account', 'active'],
    'account suspension, cancelled chat' => ['account', 'cancelled'],
    'profile suspension, active chat' => ['profile', 'active'],
    'profile suspension, cancelled chat' => ['profile', 'cancelled'],
]);

test('cancelling a draft before any completed program keeps the chat active', function (): void {
    $customer = TestActors::customer();

    $chat = Fixtures::conversation(
        $customer,
        $this->tutor,
    );

    $draft = ProgramApiFixtures::draft(
        $this->tutor,
        $customer,
    );

    $this->actingAs($this->tutor)
        ->postJson('/api/v1/programs/'.$draft.'/cancel')
        ->assertOk();

    $this->assertDatabaseHas('programs', [
        'id' => $draft,
        'status' => 'cancelled',
    ]);

    $this->assertDatabaseHas('conversations', [
        'id' => $chat,
        'status' => 'active',
        'ended_at' => null,
    ]);

    expect(
        DB::table('programs')
            ->where(
                'owner_tutor_profile_id',
                $this->tutor->tutorProfile->id,
            )
            ->where('customer_id', $customer->id)
            ->where('status', 'completed')
            ->exists()
    )->toBeFalse();
});

test('program completion does not create a missing conversation', function (): void {
    $customer = TestActors::customer();

    $program = ProgramApiFixtures::draft(
        $this->tutor,
        $customer,
        ['status' => 'active'],
    );

    ProgramApiFixtures::addMember(
        $program,
        $this->tutor,
        'tutor',
    );

    ProgramApiFixtures::addMember(
        $program,
        $customer,
        'customer',
    );

    $conversationCount = DB::table('conversations')->count();
    $memberCount = DB::table('conversation_members')->count();

    $this->actingAs($this->tutor)
        ->postJson('/api/v1/programs/'.$program.'/complete')
        ->assertOk();

    $this->assertDatabaseHas('programs', [
        'id' => $program,
        'status' => 'completed',
    ]);

    $this->assertDatabaseCount(
        'conversations',
        $conversationCount,
    );

    $this->assertDatabaseCount(
        'conversation_members',
        $memberCount,
    );

    $this->assertDatabaseHas('conversations', [
        'id' => $this->chat,
        'status' => 'active',
    ]);
});
