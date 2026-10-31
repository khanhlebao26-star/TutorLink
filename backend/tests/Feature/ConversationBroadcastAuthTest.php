<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\InteractsWithRealtimeSessions;
use Tests\Support\MessageSendFixtures;
use Tests\Support\ProgramApiFixtures;
use Tests\Support\TestActors;
use Tests\TestCase;

class ConversationBroadcastAuthTest extends TestCase
{
    use InteractsWithRealtimeSessions;
    use RefreshDatabase;

    public function test_authentication_is_required(): void
    {
        $this->configureRealtimeTests();
        $cookies = [];

        $this->authorizeChannel(
            $cookies,
            'conversations.1.sessions.'.str_repeat('a', 64),
        )->assertUnauthorized();
    }

    public function test_members_receive_a_valid_session_bound_signature(): void
    {
        $this->configureRealtimeTests();

        $customer = TestActors::customer();
        $tutor = ProgramApiFixtures::eligibleTutor();
        $chat = MessageSendFixtures::conversation($customer, $tutor);

        foreach ([$customer, $tutor] as $actor) {
            $cookies = $this->loginBrowser($actor);
            $grant = $this->issueChannel($cookies, $chat);

            $expected = 'test-reverb-key:'.hash_hmac(
                'sha256',
                '123.456:private-'.$grant['channel'],
                'test-reverb-secret',
            );

            $this->authorizeChannel($cookies, $grant['channel'])
                ->assertOk()
                ->assertJsonPath('auth', $expected);
        }
    }

    public function test_other_sessions_outsider_and_admin_cannot_use_the_token(): void
    {
        $this->configureRealtimeTests();

        $customer = TestActors::customer();
        $tutor = ProgramApiFixtures::eligibleTutor();
        $chat = MessageSendFixtures::conversation($customer, $tutor);

        $first = $this->loginBrowser($customer);
        $passwordHash = $customer->fresh()->getAuthPassword();
        $second = $this->loginBrowser($customer);

        $this->assertSame($passwordHash, $customer->fresh()->getAuthPassword());
        $this->assertSame(
            2,
            DB::table('sessions')->where('user_id', $customer->id)->count(),
        );

        $outsider = $this->loginBrowser(TestActors::customer());
        $admin = $this->loginBrowser(TestActors::admin());

        $grant = $this->issueChannel($first, $chat);

        $this->authorizeChannel($second, $grant['channel'])
            ->assertForbidden();

        $this->authorizeChannel($outsider, $grant['channel'])
            ->assertForbidden();

        $this->authorizeChannel($admin, $grant['channel'])
            ->assertForbidden();

        $this->browserRequest(
            $outsider,
            'POST',
            '/api/v1/conversations/'.$chat.'/realtime',
        )->assertNotFound();
    }

    public function test_cancelled_chat_and_suspended_peer_remain_readable(): void
    {
        $this->configureRealtimeTests();

        $customer = TestActors::customer();
        $tutor = ProgramApiFixtures::eligibleTutor();
        $chat = MessageSendFixtures::conversation($customer, $tutor);
        $cookies = $this->loginBrowser($customer);

        DB::table('conversations')->where('id', $chat)->update([
            'status' => 'cancelled',
            'ended_at' => now(),
        ]);

        DB::table('users')->where('id', $tutor->id)
            ->update(['status' => 'suspended']);

        $grant = $this->issueChannel($cookies, $chat);

        $this->authorizeChannel($cookies, $grant['channel'])
            ->assertOk();
    }

    public function test_membership_removal_denies_an_existing_token(): void
    {
        $this->configureRealtimeTests();

        $customer = TestActors::customer();
        $tutor = ProgramApiFixtures::eligibleTutor();
        $chat = MessageSendFixtures::conversation($customer, $tutor);
        $cookies = $this->loginBrowser($customer);
        $grant = $this->issueChannel($cookies, $chat);

        DB::table('conversation_members')
            ->where('conversation_id', $chat)
            ->where('user_id', $customer->id)
            ->delete();

        $this->authorizeChannel($cookies, $grant['channel'])
            ->assertForbidden();
    }

    public function test_expired_grant_is_denied_and_renewal_rotates_its_token(): void
    {
        $this->configureRealtimeTests();

        $customer = TestActors::customer();
        $tutor = ProgramApiFixtures::eligibleTutor();
        $chat = MessageSendFixtures::conversation($customer, $tutor);
        $cookies = $this->loginBrowser($customer);
        $old = $this->issueChannel($cookies, $chat);

        DB::table('realtime_sessions')->update([
            'expires_at' => now()->subMinute(),
        ]);

        $this->authorizeChannel($cookies, $old['channel'])
            ->assertForbidden();

        $new = $this->issueChannel($cookies, $chat);

        $this->assertNotSame($old['channel'], $new['channel']);

        $this->authorizeChannel($cookies, $old['channel'])
            ->assertForbidden();

        $this->authorizeChannel($cookies, $new['channel'])
            ->assertOk();
    }

    public function test_invalid_channel_socket_and_client_selected_session_are_rejected(): void
    {
        $this->configureRealtimeTests();

        $customer = TestActors::customer();
        $tutor = ProgramApiFixtures::eligibleTutor();
        $chat = MessageSendFixtures::conversation($customer, $tutor);
        $cookies = $this->loginBrowser($customer);
        $grant = $this->issueChannel($cookies, $chat);

        $this->browserRequest($cookies, 'POST', '/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => 'private-conversations.'.$chat,
        ])->assertUnprocessable();

        $this->authorizeChannel($cookies, $grant['channel'], 'invalid')
            ->assertUnprocessable();

        $this->browserRequest(
            $cookies,
            'POST',
            '/api/v1/conversations/'.$chat.'/realtime',
            ['session_id' => 'client-selected'],
        )->assertUnprocessable();
    }
}
