<?php

use App\Features\Onboarding\Controllers\EmailVerificationController;
use App\Features\Onboarding\Controllers\InvitationController;
use App\Features\Onboarding\Controllers\SignupController;
use Illuminate\Support\Facades\Route;

// Company signup: a new tenant and its first admin (SignupService).
Route::middleware('guest')->prefix('auth')->group(function () {
    Route::get('/register', [SignupController::class, 'index'])->name('register');
    Route::post('/register', [SignupController::class, 'store'])
        ->middleware('throttle:'.config('onboarding.signup.per_hour').',60')
        ->name('auth.register.store');
});

// Email verification. EnsureEmailIsVerified allows only these (and logout) until it's done.
Route::prefix('email')->name('verification.')->group(function () {
    Route::get('/verify', [EmailVerificationController::class, 'notice'])->middleware('auth')->name('notice');
    // Signed link; works without signing in (see EmailVerificationController::verify).
    Route::get('/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verify');
    Route::post('/verification-notification', [EmailVerificationController::class, 'send'])
        ->middleware(['auth', 'throttle:6,1'])
        ->name('send');
});

// Accepting an invitation (guest; the emailed token is the credential).
Route::prefix('invitations')->name('invitations.')->middleware('throttle:20,1')->group(function () {
    Route::get('/{token}', [InvitationController::class, 'show'])->name('show');
    Route::post('/{token}', [InvitationController::class, 'accept'])->name('accept');
});
