<?php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\FileController;
use App\Http\Controllers\Api\V1\TutorProfileController;
use App\Http\Middleware\EnsureActiveAccount;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register'])
            ->name('auth.register');

        Route::post('/login', [AuthController::class, 'login'])
            ->name('auth.login');

        Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
            ->name('auth.forgot-password');

        Route::post('/reset-password', [AuthController::class, 'resetPassword'])
            ->name('auth.reset-password');

        Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verification.verify');

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout'])
                ->name('auth.logout');

            Route::post('/email/verification-notification', [
                AuthController::class,
                'resendVerification',
            ])->middleware('throttle:6,1')
                ->name('verification.send');
        });
    });

    Route::middleware(['auth:sanctum', EnsureActiveAccount::class])->group(function () {
        Route::prefix('admin')->group(function () {
            Route::get('/tutor-profiles', [AdminController::class, 'index'])
                ->name('admin.tutor-profiles.index');
            Route::get('/tutor-profiles/{tutorProfile}', [AdminController::class, 'show'])
                ->name('admin.tutor-profiles.show');
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
        });

        Route::get('/me', [AuthController::class, 'me'])
            ->name('auth.me');

        Route::patch('/me', [AuthController::class, 'updateMe'])
            ->name('auth.update-me');

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

    Route::get('/catalog/categories', [CatalogController::class, 'categories'])
        ->name('catalog.categories');
    Route::get('/catalog/specializations', [CatalogController::class, 'specializations'])
        ->name('catalog.specializations');
});
