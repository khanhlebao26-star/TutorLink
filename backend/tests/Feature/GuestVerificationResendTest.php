<?php

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Support\TestActors;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);
it('resends verification before login with a neutral response and rate limit', function () {
    Notification::fake();
    $user = TestActors::customer(['email_verified_at' => null]);
    $valid = $this->postJson('/api/v1/auth/email/verification-notification', ['email' => $user->email])->assertOk();
    Notification::assertSentTo($user, VerifyEmail::class);
    $missing = $this->postJson('/api/v1/auth/email/verification-notification', ['email' => 'absent@example.test'])->assertOk();
    expect($valid->json())->toBe($missing->json());
    for ($i = 0; $i < 4; $i++) {
        $this->postJson('/api/v1/auth/email/verification-notification', ['email' => 'absent@example.test'])->assertOk();
    }
    $this->postJson('/api/v1/auth/email/verification-notification', ['email' => $user->email])->assertTooManyRequests()->assertHeader('Retry-After');
});
