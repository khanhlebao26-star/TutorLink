<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\FileController;
use App\Http\Controllers\Api\V1\TutorProfileController;
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
            ->middleware(['auth:sanctum', 'signed', 'throttle:6,1'])
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

    Route::middleware('auth:sanctum')->group(function () {
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
    });

    Route::get('/catalog/categories', [CatalogController::class, 'categories'])
        ->name('catalog.categories');
    Route::get('/catalog/specializations', [CatalogController::class, 'specializations'])
        ->name('catalog.specializations');
});
