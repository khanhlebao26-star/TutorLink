<?php

use App\Http\Controllers\Admin\AssignedTutorProfileController;
use App\Http\Controllers\Api\V1\FileController;
use App\Http\Controllers\BroadcastAuthController;
use App\Http\Middleware\EnsureActiveApiActor;
use App\Http\Middleware\EnsureAdminOperator;
use App\Http\Middleware\EnsureAdminPanelEnabled;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/broadcasting/auth', BroadcastAuthController::class)
    ->middleware(EnsureActiveApiActor::class)
    ->name('broadcasting.auth');

Route::middleware([EnsureAdminPanelEnabled::class, EnsureAdminOperator::class])->prefix('admin-business')->name('admin.')->group(function (): void {
    Route::get('/assigned-profiles', [AssignedTutorProfileController::class, 'index'])->name('assigned-profiles.index');
    Route::get('/assigned-profiles/{tutorProfile}', [AssignedTutorProfileController::class, 'show'])->name('assigned-profiles.show');
    Route::post('/assigned-profiles/{tutorProfile}', [AssignedTutorProfileController::class, 'update'])->name('assigned-profiles.update');
    Route::get('/documents/{file}', [FileController::class, 'download'])->name('documents.download');
});
