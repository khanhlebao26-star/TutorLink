<?php

namespace App\Jobs;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Password;

class SendAccountEmail implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(public int $userId, public string $email, public ?string $token = null, public ?int $expiresAt = null)
    {
        $this->onConnection(config('queue.default'))->onQueue('emails')->afterCommit();
        $this->expiresAt ??= now()->addMinutes($token === null ? (int) config('auth.verification.expire', 60) : (int) config('auth.passwords.users.expire', 60))->timestamp;
    }

    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(): void
    {
        $user = User::find($this->userId);
        if (! $user || $user->email !== $this->email || $user->status !== 'active' || $user->suspended_at !== null || now()->timestamp >= $this->expiresAt) {
            return;
        }
        if ($this->token !== null) {
            if (Password::broker()->tokenExists($user, $this->token)) {
                $user->notify(new ResetPassword($this->token));
            }
        } elseif (! $user->hasVerifiedEmail()) {
            $user->notify(new VerifyEmail);
        }
    }
}
