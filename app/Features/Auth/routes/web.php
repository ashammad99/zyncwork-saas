<?php

use App\Features\Auth\Controllers\ForgotPasswordController;
use App\Features\Auth\Controllers\LoginController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    // frontend
    Route::get('/login', [LoginController::class, 'index'])->name('login'); // login page
    Route::get('forgot-password', [ForgotPasswordController::class, 'index'])->name('auth.forgot-password.index');
    Route::get('reset-password/{token}', [ForgotPasswordController::class, 'showResetForm'])->name('password.reset');

    // backend
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:login')->name('auth.login.login');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'send'])->name('auth.forgot-password.send');
    Route::post('reset-password', [ForgotPasswordController::class, 'reset'])->name('password.update');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
});
