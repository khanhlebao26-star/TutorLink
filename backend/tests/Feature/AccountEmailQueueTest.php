<?php

namespace Tests\Feature;

use App\Jobs\SendAccountEmail;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AccountEmailQueueTest extends TestCase
{
    use DatabaseTruncation;

    public function test_account_emails_are_queued_without_sending_in_http(): void
    {
        Queue::fake();
        Notification::fake();
        $user = User::factory()->unverified()->create();
        $user->sendEmailVerificationNotification();
        $user->sendPasswordResetNotification('secret-token');
        Queue::assertPushedOn('emails', SendAccountEmail::class);
        Queue::assertPushed(SendAccountEmail::class, 2);
        Notification::assertNothingSent();
        $this->assertInstanceOf(ShouldBeEncrypted::class, new SendAccountEmail($user->id, $user->email));
    }

    public function test_worker_drops_expired_tokens_and_changed_accounts(): void
    {
        Notification::fake();
        $user = User::factory()->unverified()->create();
        (new SendAccountEmail($user->id, $user->email))->handle();
        Notification::assertSentTo($user, VerifyEmail::class);
        $token = Password::createToken($user);
        (new SendAccountEmail($user->id, $user->email, $token))->handle();
        Notification::assertSentTo($user, ResetPassword::class);
        Notification::fake();
        (new SendAccountEmail($user->id, $user->email, $token, now()->subSecond()->timestamp))->handle();
        (new SendAccountEmail($user->id, 'old@example.com'))->handle();
        Password::deleteToken($user);
        (new SendAccountEmail($user->id, $user->email, $token))->handle();
        $user->forceFill(['email_verified_at' => now()])->save();
        (new SendAccountEmail($user->id, $user->email))->handle();
        Notification::assertNothingSent();
    }

    public function test_queue_waits_for_commit_and_encrypts_reset_token(): void
    {
        config(['queue.default' => 'database']);
        $user = User::factory()->unverified()->create();
        DB::beginTransaction();
        $user->sendPasswordResetNotification('sensitive-reset-token');
        $this->assertDatabaseCount('jobs', 0);
        DB::commit();
        $this->assertDatabaseCount('jobs', 1);
        $payload = DB::table('jobs')->first();
        $this->assertSame('emails', $payload->queue);
        $this->assertStringNotContainsString('sensitive-reset-token', $payload->payload);
        DB::table('jobs')->delete();
        DB::beginTransaction();
        $user->sendEmailVerificationNotification();
        DB::rollBack();
        $this->assertDatabaseCount('jobs', 0);
    }
}
