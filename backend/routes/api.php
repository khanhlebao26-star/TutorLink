<?php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\AvailabilityRuleController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\ConversationRealtimeController;
use App\Http\Controllers\Api\V1\FileController;
use App\Http\Controllers\Api\V1\InvitationAcceptController;
use App\Http\Controllers\Api\V1\InvitationController;
use App\Http\Controllers\Api\V1\ListingController;
use App\Http\Controllers\Api\V1\NotificationOutboxController;
use App\Http\Controllers\Api\V1\PersonalAccessTokenController;
use App\Http\Controllers\Api\V1\ProgramController;
use App\Http\Controllers\Api\V1\PublicTutorController;
use App\Http\Controllers\Api\V1\PublicTutorReviewController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\TutorProfileController;
use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsureActiveApiActor;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::middleware(EnsureActiveApiActor::class)->group(function () {
        Route::get('/programs', [ProgramController::class, 'index'])
            ->name('programs.index');
        Route::post('/programs', [ProgramController::class, 'store'])
            ->name('programs.store');
        Route::get('/programs/{program}', [ProgramController::class, 'show'])
            ->whereNumber('program')
            ->name('programs.show');
        Route::patch('/programs/{program}', [ProgramController::class, 'update'])
            ->whereNumber('program')
            ->name('programs.update');
        Route::post('/programs/{program}/pause', [ProgramController::class, 'pause'])
            ->whereNumber('program')
            ->name('programs.pause');
        Route::post('/programs/{program}/resume', [ProgramController::class, 'resume'])
            ->whereNumber('program')
            ->name('programs.resume');
        Route::post('/programs/{program}/complete', [ProgramController::class, 'complete'])
            ->whereNumber('program')
            ->name('programs.complete');
        Route::post('/programs/{program}/cancel', [ProgramController::class, 'cancel'])
            ->whereNumber('program')
            ->name('programs.cancel');
        Route::post('/programs/{program}/invitations', [InvitationController::class, 'store'])
            ->whereNumber('program')
            ->name('programs.invitations.store');
        Route::get('/invitations', [InvitationController::class, 'index'])
            ->name('invitations.index');
        Route::get('/invitations/{invitation}', [InvitationController::class, 'show'])
            ->whereNumber('invitation')
            ->name('invitations.show');
        Route::post('/invitations/{invitation}/accept', InvitationAcceptController::class)
            ->whereNumber('invitation')
            ->name('invitations.accept');
        Route::post('/invitations/{invitation}/reject', [InvitationController::class, 'reject'])
            ->whereNumber('invitation')
            ->name('invitations.reject');
        Route::post('/invitations/{invitation}/cancel', [InvitationController::class, 'cancel'])
            ->whereNumber('invitation')
            ->name('invitations.cancel');
        Route::get('/programs/{program}/tasks', [TaskController::class, 'index'])->whereNumber('program');
        Route::post('/programs/{program}/tasks', [TaskController::class, 'store'])->whereNumber('program');
        Route::get('/tasks/{task}', [TaskController::class, 'show'])->whereNumber('task');
        Route::patch('/tasks/{task}', [TaskController::class, 'update'])->whereNumber('task');
        Route::post('/tasks/{task}/start', [TaskController::class, 'start'])->whereNumber('task');
        Route::post('/tasks/{task}/cancel', [TaskController::class, 'cancel'])->whereNumber('task');
        Route::get('/tasks/{task}/submission', [TaskController::class, 'submission'])->whereNumber('task');
        Route::put('/tasks/{task}/submission', [TaskController::class, 'submit'])->whereNumber('task');
        Route::get('/tasks/{task}/submission/versions', [TaskController::class, 'versions'])->whereNumber('task');
        Route::post('/tasks/{task}/submission/review', [TaskController::class, 'review'])->whereNumber('task');
        Route::post('/tasks/{task}/submission/request-revision', [TaskController::class, 'requestRevision'])->whereNumber('task');
        Route::post('/conversations', [ConversationController::class, 'store'])
            ->name('conversations.store');
        Route::get('/conversations', [ConversationController::class, 'index'])
            ->name('conversations.index');

        Route::get('/conversations/{conversation}', [ConversationController::class, 'show'])
            ->whereNumber('conversation')
            ->name('conversations.show');

        Route::get('/conversations/{conversation}/messages', [ConversationController::class, 'messages'])
            ->whereNumber('conversation')
            ->name('conversations.messages.index');
        Route::post('/conversations/{conversation}/messages', [ConversationController::class, 'sendMessage'])
            ->whereNumber('conversation')
            ->name('conversations.messages.store');
        Route::patch('/conversations/{conversation}/read', [ConversationController::class, 'markRead'])
            ->whereNumber('conversation')
            ->name('conversations.read');

        Route::post(
            '/conversations/{conversation}/realtime',
            ConversationRealtimeController::class,
        )
            ->whereNumber('conversation')
            ->name('conversations.realtime');
    });

    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])
            ->middleware('throttle:register')
            ->name('auth.register');

        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:login')
            ->name('auth.login');

        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
            ->name('auth.forgot-password');

        Route::post('/reset-password', [AuthController::class, 'resetPassword'])
            ->name('auth.reset-password');

        Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verification.verify');

        Route::middleware('auth:web')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout'])
                ->name('auth.logout');

            Route::post('/email/verification-notification', [
                AuthController::class,
                'resendVerification',
            ])->middleware('throttle:6,1')
                ->name('verification.send');
        });
    });

    Route::get('/listings', [ListingController::class, 'index']);
    Route::get('/listings/{slug}', [ListingController::class, 'show']);

    Route::get('/tutors/{slug}', [
        PublicTutorController::class,
        'show',
    ])->where('slug', '[a-z0-9-]+');

    Route::get('/tutors/{slug}/reviews', [PublicTutorReviewController::class, 'index'])
        ->where('slug', '[a-z0-9-]+');

    Route::middleware(['auth:web', EnsureActiveAccount::class])->group(function () {
        Route::get('/me/availability-rules', [
            AvailabilityRuleController::class,
            'index',
        ]);

        Route::put('/me/availability-rules', [
            AvailabilityRuleController::class,
            'replace',
        ]);

        Route::prefix('admin')->middleware(EnsureActiveApiActor::class)->group(function () {
            Route::get('/tutor-profiles', [AdminController::class, 'index'])
                ->name('admin.tutor-profiles.index');
            Route::get('/tutor-profiles/{tutorProfile}', [AdminController::class, 'show'])
                ->name('admin.tutor-profiles.show');
            Route::put('/tutor-profiles/{tutorProfile}/assignment', [AdminController::class, 'assign'])
                ->name('admin.tutor-profiles.assignment');
            Route::post('/verification-documents/{document}/approve', [AdminController::class, 'approveDocument'])
                ->name('admin.verification-documents.approve');
            Route::post('/verification-documents/{document}/reject', [AdminController::class, 'rejectDocument'])
                ->name('admin.verification-documents.reject');
            Route::post('/tutor-profiles/{tutorProfile}/approve', [AdminController::class, 'approve'])
                ->name('admin.tutor-profiles.approve');
            Route::post('/tutor-profiles/{tutorProfile}/request-changes', [AdminController::class, 'requestChanges'])
                ->name('admin.tutor-profiles.request-changes');
            Route::post('/tutor-profiles/{tutorProfile}/reject', [AdminController::class, 'reject'])
                ->name('admin.tutor-profiles.reject');
            Route::post('/tutor-profiles/{tutorProfile}/suspend', [AdminController::class, 'suspendProfile'])
                ->name('admin.tutor-profiles.suspend');
            Route::post('/tutor-profiles/{tutorProfile}/restore', [AdminController::class, 'restoreProfile'])
                ->name('admin.tutor-profiles.restore');
            Route::post('/users/{user}/suspend', [AdminController::class, 'suspendAccount'])
                ->name('admin.users.suspend');
            Route::post('/users/{user}/restore', [AdminController::class, 'restoreAccount'])
                ->name('admin.users.restore');
            Route::get('/notification-outbox', [
                NotificationOutboxController::class,
                'index',
            ])->name('admin.notification-outbox.index');

            Route::post('/notification-outbox/{outbox}/replay', [
                NotificationOutboxController::class,
                'replay',
            ])->whereUuid('outbox')
                ->name('admin.notification-outbox.replay');
        });

        Route::patch('/me', [AuthController::class, 'updateMe'])
            ->name('auth.update-me');

        Route::put('/me/password', [AuthController::class, 'updatePassword'])
            ->name('auth.update-password');

        Route::post('/files', [FileController::class, 'store'])
            ->name('files.store');
        Route::get('/files/{file}', [FileController::class, 'show'])
            ->name('files.show');
        Route::post('/files/{file}/complete', [FileController::class, 'complete'])
            ->name('files.complete');
        Route::post('/files/{file}/links', [FileController::class, 'link'])
            ->name('files.link');
        Route::get('/files/{file}/download', [FileController::class, 'download'])
            ->name('files.download');
        Route::delete('/files/{file}', [FileController::class, 'destroy'])
            ->name('files.destroy');

        Route::get('/tutor/profile', [TutorProfileController::class, 'show'])
            ->name('tutor.profile.show');
        Route::put('/tutor/profile', [TutorProfileController::class, 'store'])
            ->name('tutor.profile.store');
        Route::post('/tutor/profile/submit', [TutorProfileController::class, 'submit'])
            ->name('tutor.profile.submit');
        Route::put('/tutor/profile/avatar', [TutorProfileController::class, 'attachAvatar'])
            ->name('tutor.profile.avatar');
        Route::post('/tutor/profile/verification-documents', [
            TutorProfileController::class,
            'createVerificationDocument',
        ])->name('tutor.profile.verification-documents');
    });

    Route::middleware([
        'auth:sanctum',
        EnsureActiveAccount::class,
        'abilities:account:read',
    ])->get('/me', [AuthController::class, 'me'])
        ->name('auth.me');

    Route::middleware(['auth:sanctum', EnsureActiveAccount::class])->group(function () {
        Route::get('/me/listings', [ListingController::class, 'myIndex'])
            ->middleware('abilities:my-listings:read');

        Route::get('/me/listings/{listing}', [ListingController::class, 'myShow'])
            ->middleware('abilities:my-listings:read');

        Route::post('/me/listings', [ListingController::class, 'store'])
            ->middleware('abilities:my-listings:write');

        Route::patch('/me/listings/{listing}', [ListingController::class, 'update'])
            ->middleware('abilities:my-listings:write');

        Route::delete('/me/listings/{listing}', [ListingController::class, 'destroy'])
            ->middleware('abilities:my-listings:write');
    });

    Route::get('/catalog/categories', [CatalogController::class, 'categories'])
        ->name('catalog.categories');
    Route::get('/catalog/specializations', [CatalogController::class, 'specializations'])
        ->name('catalog.specializations');

    Route::middleware(['auth:web', EnsureActiveAccount::class])->group(function () {
        Route::post('/me/tokens', [PersonalAccessTokenController::class, 'store']);
        Route::get('/me/tokens', [PersonalAccessTokenController::class, 'index']);
        Route::delete('/me/tokens/{tokenId}', [PersonalAccessTokenController::class, 'destroy'])
            ->whereNumber('tokenId');
    });

});
