<?php

namespace Tests\Feature;

use App\Jobs\BroadcastConversationChanged;
use App\Support\RealtimeSessionChannels;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Support\InteractsWithRealtimeSessions;
use Tests\Support\MessageSendFixtures;
use Tests\Support\ProgramApiFixtures;
use Tests\Support\TestActors;
use Tests\TestCase;

class RealtimeSessionAccessTest extends TestCase
{
    use InteractsWithRealtimeSessions;
    use RefreshDatabase;

    public function test_logout_revokes_only_current_session_and_stale_requests_cannot_reissue(): void
    {
        $this->configureRealtimeTests();

        $customer = TestActors::customer();
        $tutor = ProgramApiFixtures::eligibleTutor();
        $chat = MessageSendFixtures::conversation($customer, $tutor);

        $first = $this->loginBrowser($customer);
        $firstGrant = $this->issueChannel($first, $chat);
        $oldCookies = $first;

        $oldSession = DB::table('sessions')
            ->where('user_id', $customer->id)
            ->first();

        $second = $this->loginBrowser($customer);
        $secondGrant = $this->issueChannel($second, $chat);

        $this->assertNotSame(
            $firstGrant['channel'],
            $secondGrant['channel'],
        );

        $this->browserRequest(
            $first,
            'POST',
            '/api/v1/auth/logout',
        )->assertNoContent();

        $this->authorizeChannel($second, $secondGrant['channel'])
            ->assertOk();

        $this->assertSame(
            1,
            DB::table('realtime_sessions')
                ->where('user_id', $customer->id)
                ->whereNotNull('revoked_at')
                ->count(),
        );

        /*
         * Simulate an old in-flight request writing its session row
         * after logout. The realtime tombstone must remain authoritative.
         */
        DB::table('sessions')->updateOrInsert(
            ['id' => $oldSession->id],
            (array) $oldSession,
        );

        $this->browserRequest(
            $oldCookies,
            'POST',
            '/api/v1/conversations/'.$chat.'/realtime',
        )->assertUnauthorized()->assertJsonPath('code', 'session_revoked');

        $this->assertDatabaseCount('realtime_sessions', 2);

        $driver = Mockery::mock(Broadcaster::class);

        $driver->shouldReceive('broadcast')
            ->once()
            ->with(
                ['private-'.$secondGrant['channel']],
                'conversation.messages_changed',
                ['conversation_id' => $chat],
            );

        Broadcast::shouldReceive('connection')->once()->andReturn($driver);

        /*
         * This also represents a job queued before logout but executed after it.
         */
        (new BroadcastConversationChanged($chat))
            ->handle(app(RealtimeSessionChannels::class));
    }

    public function test_account_suspend_revokes_every_session_and_restore_does_not_restore_tokens(): void
    {
        $this->assertSuspendAndRestore(false);
    }

    public function test_profile_suspend_revokes_every_session_and_restore_does_not_restore_tokens(): void
    {
        $this->assertSuspendAndRestore(true);
    }

    public function test_suspend_transaction_rollback_preserves_realtime_access(): void
    {
        $this->configureRealtimeTests();

        $customer = TestActors::customer();
        $tutor = ProgramApiFixtures::eligibleTutor();
        $chat = MessageSendFixtures::conversation($customer, $tutor);

        $cookies = $this->loginBrowser($customer);
        $grant = $this->issueChannel($cookies, $chat);
        $admin = $this->loginBrowser(TestActors::admin());

        DB::beginTransaction();

        try {
            $this->browserRequest(
                $admin,
                'POST',
                '/api/v1/admin/users/'.$customer->id.'/suspend',
                ['reason' => 'Realtime rollback test.'],
            )->assertOk();

            $this->assertSame(
                1,
                DB::table('realtime_sessions')
                    ->whereNotNull('revoked_at')
                    ->count(),
            );
        } finally {
            DB::rollBack();
        }

        $this->assertDatabaseHas('users', [
            'id' => $customer->id,
            'status' => 'active',
        ]);

        $this->assertSame(
            0,
            DB::table('realtime_sessions')
                ->whereNotNull('revoked_at')
                ->count(),
        );

        $this->authorizeChannel($cookies, $grant['channel'])
            ->assertOk();
    }

    private function assertSuspendAndRestore(bool $profile): void
    {
        $this->configureRealtimeTests();

        $customer = TestActors::customer();
        $tutor = ProgramApiFixtures::eligibleTutor();
        $target = $profile ? $tutor : $customer;
        $chat = MessageSendFixtures::conversation($customer, $tutor);

        $first = $this->loginBrowser($target);
        $second = $this->loginBrowser($target);

        $firstGrant = $this->issueChannel($first, $chat);
        $secondGrant = $this->issueChannel($second, $chat);

        $membersBefore = DB::table('conversation_members')
            ->where('conversation_id', $chat)
            ->orderBy('user_id')
            ->get()->toJson();

        $admin = $this->loginBrowser(TestActors::admin());

        $path = $profile
            ? '/api/v1/admin/tutor-profiles/'.$tutor->tutorProfile->id
            : '/api/v1/admin/users/'.$target->id;

        $this->browserRequest(
            $admin,
            'POST',
            $path.'/suspend',
            ['reason' => 'Realtime session revocation test.'],
        )->assertOk();

        $this->assertSame(
            2,
            DB::table('realtime_sessions')
                ->where('user_id', $target->id)
                ->whereNotNull('revoked_at')
                ->count(),
        );

        $this->assertDatabaseMissing('sessions', [
            'user_id' => $target->id,
        ]);

        $this->assertDatabaseHas('conversations', [
            'id' => $chat,
            'status' => 'active',
        ]);

        $this->assertSame(
            $membersBefore,
            DB::table('conversation_members')
                ->where('conversation_id', $chat)
                ->orderBy('user_id')
                ->get()->toJson(),
        );

        if ($profile) {
            $this->browserRequest(
                $admin,
                'POST',
                $path.'/restore',
                ['reason' => 'Restore Tutor profile.'],
            )->assertOk();
        }

        $this->browserRequest(
            $admin,
            'POST',
            '/api/v1/admin/users/'.$target->id.'/restore',
            ['reason' => 'Restore account after realtime test.'],
        )->assertOk();

        $this->authorizeChannel($first, $firstGrant['channel'])
            ->assertUnauthorized();

        $this->authorizeChannel($second, $secondGrant['channel'])
            ->assertUnauthorized();

        $fresh = $this->loginBrowser($target->fresh());
        $freshGrant = $this->issueChannel($fresh, $chat);

        $this->assertNotSame($firstGrant['channel'], $freshGrant['channel']);
        $this->assertNotSame($secondGrant['channel'], $freshGrant['channel']);

        $this->authorizeChannel($fresh, $firstGrant['channel'])
            ->assertForbidden();

        $this->authorizeChannel($fresh, $freshGrant['channel'])
            ->assertOk();

        $this->assertSame(
            2,
            DB::table('realtime_sessions')
                ->where('user_id', $target->id)
                ->whereNotNull('revoked_at')
                ->count(),
        );
    }
}
