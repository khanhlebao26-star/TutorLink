<?php

namespace App\Actions\Fortify;

use App\Actions\Auth\RevokeUserSessions;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

class UpdateUserPassword implements UpdatesUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and update the user's password.
     *
     * @param  array<string, string>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        $request = request();
        $guard = Auth::guard('web');

        abort_unless(
            $request->hasSession()
            && $guard->check()
            && (string) $guard->id() === (string) $user->getKey(),
            401,
            'A valid browser session is required.',
        );

        Validator::make($input, [
            'current_password' => [
                'required',
                'string',
                'current_password:web',
            ],
            'password' => $this->passwordRules(),
        ], [
            'current_password.current_password' => __('The provided password does not match your current password.'),
        ])->validateWithBag('updatePassword');

        $currentSessionId = $request->session()->getId();

        DB::transaction(function () use ($user, $input, $currentSessionId): void {
            $user->forceFill([
                'password_hash' => $input['password'],
                'remember_token' => str()->random(60),
            ])->save();

            app(RevokeUserSessions::class)->handle(
                $user,
                $currentSessionId,
            );
        });

        $guard->login($user, false);
        $request->session()->regenerate(true);

        Cookie::queue(Cookie::forget(
            $guard->getRecallerName(),
            config('session.path', '/'),
            config('session.domain'),
        ));
    }
}
