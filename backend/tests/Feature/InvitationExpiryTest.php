<?php

use App\Actions\Invitations\ResolveInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\MessageSendFixtures;
use Tests\Support\ProgramApiFixtures;
use Tests\Support\TestActors;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('expires due Invitations once even for suspended parties and leaves future or terminal offers unchanged', function () {
    $this->freezeTime();
    $tutor = ProgramApiFixtures::eligibleTutor();
    $customer = TestActors::customer();
    $offers = [];
    foreach (['past', 'boundary', 'future', 'terminal'] as $case) {
        $program = ProgramApiFixtures::draft($tutor, $customer);
        $expiry = match ($case) {
            'past', 'terminal' => now('UTC')->subMinute(), 'boundary' => now('UTC'), 'future' => now('UTC')->addSecond(),
        };
        $offers[$case] = ProgramApiFixtures::invitation($program, $tutor, $customer, $expiry->toDateTimeString());
    }
    DB::table('program_invitations')->where('id', $offers['terminal'])->update(['status' => 'rejected']);
    DB::table('users')->whereIn('id', [$customer->id, $tutor->id])->update(['status' => 'suspended']);
    $before = DB::table('program_invitations')->where('id', $offers['past'])->first();

    $this->artisan('invitations:expire')->expectsOutput('Expired 2 Invitation(s); 0 failed.')->assertSuccessful();
    $this->artisan('invitations:expire')->expectsOutput('Expired 0 Invitation(s); 0 failed.')->assertSuccessful();

    foreach (['past', 'boundary'] as $case) {
        $this->assertDatabaseHas('program_invitations', ['id' => $offers[$case], 'status' => 'expired', 'accepted_at' => null]);
        $this->assertDatabaseHas('audit_logs', ['resource_id' => $offers[$case], 'action' => 'invitation.expire', 'actor_id' => null]);
        $this->assertDatabaseHas('notification_outbox', ['resource_id' => $offers[$case], 'user_id' => $tutor->id, 'type' => 'invitation_expired']);
    }
    $after = DB::table('program_invitations')->where('id', $offers['past'])->first();
    expect($after->offer_snapshot)->toBe($before->offer_snapshot)
        ->and($after->expires_at)->toBe($before->expires_at)
        ->and($after->responded_at)->toBe($before->expires_at);
    $this->assertDatabaseHas('program_invitations', ['id' => $offers['future'], 'status' => 'pending']);
    $this->assertDatabaseHas('program_invitations', ['id' => $offers['terminal'], 'status' => 'rejected']);
    $this->assertDatabaseCount('program_members', 0);
    $this->assertDatabaseCount('audit_logs', 2);
    $this->assertDatabaseCount('notification_outbox', 2);
    $this->assertDatabaseCount('notifications', 0);
});

it('synchronizes chat at the end of expiry or a decision while preserving messages and members', function (string $action, bool $ongoing) {
    $tutor = ProgramApiFixtures::eligibleTutor();
    $customer = TestActors::customer();
    $chat = MessageSendFixtures::conversation($customer, $tutor);
    $message = MessageSendFixtures::seed($chat, $customer);
    ProgramApiFixtures::draft($tutor, $customer, ['status' => 'completed']);
    if ($ongoing) {
        ProgramApiFixtures::draft($tutor, $customer, ['status' => 'paused']);
    }
    $program = ProgramApiFixtures::draft($tutor, $customer);
    $invitation = ProgramApiFixtures::invitation($program, $tutor, $customer,
        ($action === 'expire' ? now('UTC')->subMinute() : now('UTC')->addHour())->toDateTimeString());

    if ($action === 'expire') {
        $this->artisan('invitations:expire')->assertSuccessful();
    } else {
        $this->actingAs($action === 'reject' ? $customer : $tutor, 'web')
            ->postJson("/api/v1/invitations/{$invitation}/{$action}")->assertOk();
    }

    $this->assertDatabaseHas('conversations', ['id' => $chat, 'status' => $ongoing ? 'active' : 'cancelled']);
    $this->assertDatabaseHas('messages', ['id' => $message, 'body' => 'Existing text']);
    $this->assertDatabaseCount('conversation_members', 2);
    $this->assertDatabaseHas('programs', ['id' => $program, 'status' => 'draft']);
})->with(['reject', 'cancel', 'expire'])->with([false, true]);

it('rolls back failed expiry and retries it while continuing other offers', function () {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL fault injection requires PostgreSQL.');
    }
    $tutor = ProgramApiFixtures::eligibleTutor();
    $customer = TestActors::customer();
    $chat = MessageSendFixtures::conversation($customer, $tutor);
    ProgramApiFixtures::draft($tutor, $customer, ['status' => 'completed']);
    $failedProgram = ProgramApiFixtures::draft($tutor, $customer);
    $failed = ProgramApiFixtures::invitation($failedProgram, $tutor, $customer, now('UTC')->subMinute()->toDateTimeString());
    $otherProgram = ProgramApiFixtures::draft($tutor, $customer);
    $other = ProgramApiFixtures::invitation($otherProgram, $tutor, $customer, now('UTC')->subMinute()->toDateTimeString());
    DB::unprepared("CREATE FUNCTION h03_expiry_failure() RETURNS trigger LANGUAGE plpgsql AS \$\$ BEGIN IF NEW.resource_id = {$failed} THEN RAISE EXCEPTION 'H03 simulated expiry failure'; END IF; RETURN NEW; END; \$\$");
    DB::unprepared('CREATE TRIGGER h03_expiry_failure BEFORE INSERT ON notification_outbox FOR EACH ROW EXECUTE FUNCTION h03_expiry_failure()');

    try {
        $this->artisan('invitations:expire')->expectsOutput('Expired 1 Invitation(s); 1 failed.')->assertFailed();
        $this->assertDatabaseHas('program_invitations', ['id' => $failed, 'status' => 'pending', 'responded_at' => null]);
        $this->assertDatabaseHas('program_invitations', ['id' => $other, 'status' => 'expired']);
        $this->assertDatabaseMissing('audit_logs', ['resource_id' => $failed, 'action' => 'invitation.expire']);
        $this->assertDatabaseCount('notification_outbox', 1);
    } finally {
        DB::unprepared('DROP TRIGGER IF EXISTS h03_expiry_failure ON notification_outbox');
        DB::unprepared('DROP FUNCTION IF EXISTS h03_expiry_failure()');
    }

    $this->artisan('invitations:expire')->expectsOutput('Expired 1 Invitation(s); 0 failed.')->assertSuccessful();
    $this->assertDatabaseCount('audit_logs', 2);
    $this->assertDatabaseCount('notification_outbox', 2);
    $this->assertDatabaseCount('program_members', 0);
    $this->assertDatabaseHas('conversations', ['id' => $chat, 'status' => 'cancelled']);
});

it('expires all batches and ignores a removed or already resolved candidate', function () {
    $this->freezeTime();
    $tutor = ProgramApiFixtures::eligibleTutor();
    $customer = TestActors::customer();
    for ($index = 0; $index < 101; $index++) {
        $program = ProgramApiFixtures::draft($tutor, $customer);
        ProgramApiFixtures::invitation($program, $tutor, $customer, now('UTC')->subMinute()->toDateTimeString());
    }

    $this->artisan('invitations:expire')->expectsOutput('Expired 101 Invitation(s); 0 failed.')->assertSuccessful();

    expect(DB::table('program_invitations')->where('status', 'expired')->count())->toBe(101);
    $this->assertDatabaseCount('audit_logs', 101);
    $this->assertDatabaseCount('notification_outbox', 101);
    expect(app(ResolveInvitation::class)->expire(999999, (string) Str::uuid()))->toBeFalse();
    expect(app(ResolveInvitation::class)->expire((int) DB::table('program_invitations')->value('id'), (string) Str::uuid()))->toBeFalse();
    $this->assertDatabaseCount('audit_logs', 101);
});
