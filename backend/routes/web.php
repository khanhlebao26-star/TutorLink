<?php

use App\Http\Controllers\BroadcastAuthController;
use App\Http\Middleware\EnsureActiveApiActor;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/broadcasting/auth', BroadcastAuthController::class)
    ->middleware(EnsureActiveApiActor::class)
    ->name('broadcasting.auth');
