<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\UpdateMeRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::create([
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'password_hash' => $data['password'],
            'role' => $data['role'],
            'status' => 'active',
            'date_of_birth' => $data['date_of_birth'],
            'terms_version' => $data['terms_version'] ?? null,
        ]);

        event(new Registered($user));

        if ($request->hasSession()) {
            Auth::login($user);
            $request->session()->regenerate();
        }

        return (new UserResource($user))
            ->response()
            ->setStatusCode(201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password_hash)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        if (in_array($user->status, ['suspended', 'cancelled'], true)) {
            return response()->json(['message' => 'This account is not available.'], 403);
        }

        if ($request->hasSession()) {
            Auth::login($user, $data['remember'] ?? false);
            $request->session()->regenerate();
        }
        $user->load(['customerProfile', 'tutorProfile']);

        return (new UserResource($user))->response();
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(status: 204);
    }

    public function me(Request $request): UserResource
    {
        /** @var User $user */
        $user = $request->user()->load(['customerProfile', 'tutorProfile']);

        return new UserResource($user);
    }

    public function updateMe(UpdateMeRequest $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();
        $user->fill($request->validated());
        $user->save();

        return new UserResource($user->load(['customerProfile', 'tutorProfile']));
    }

    public function verifyEmail(Request $request, int $id, string $hash): JsonResponse
    {
        /** @var User $user */
        $user = User::findOrFail($id);

        abort_unless(
            hash_equals(sha1($user->getEmailForVerification()), $hash),
            403,
            'Invalid verification link.',
        );

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        return (new UserResource($user))->response();
    }

    public function resendVerification(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->hasVerifiedEmail()) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json(['message' => 'Verification notification sent.']);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $status = Password::sendResetLink($request->validated());

        if ($status === Password::RESET_THROTTLED) {
            $seconds = (int) config('auth.passwords.users.throttle', 60);

            return response()
                ->json([
                    'message' => "Please wait {$seconds} seconds before requesting another password reset email.",
                    'retry_after' => $seconds,
                ], 429)
                ->header('Retry-After', (string) $seconds);
        }

        return response()->json(['message' => 'If the email exists, a reset link has been sent.']);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $data = $request->validated();

        $status = Password::reset(
            $data,
            function (User $user) use ($data): void {
                $user->forceFill([
                    'password_hash' => $data['password'],
                    'remember_token' => str()->random(60),
                ])->save();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return response()->json([
                'message' => $status === Password::INVALID_TOKEN
                    ? 'The password reset token is invalid or expired.'
                    : __($status),
            ], 422);
        }

        return response()->json(['message' => __($status)]);
    }
}
