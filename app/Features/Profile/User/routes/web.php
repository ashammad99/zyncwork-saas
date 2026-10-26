<?php

use App\Features\Profile\Shared\Controllers\ProfileImageController;
use App\Features\Profile\User\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:employee,manager'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/profile/password', [ProfileController::class, 'passwordIndex'])->name('profile.password.index');
    Route::put('/profile/password', [ProfileController::class, 'passwordUpdate'])->name('profile.password.update');

    // Modular Profile Image Delivery
});
// Role-agnostic avatar serving — any authenticated user
Route::middleware('auth')->group(function () {
    Route::get('/profile-image/{filename}', [ProfileImageController::class, 'serve'])
        ->name('profile-image');
});
