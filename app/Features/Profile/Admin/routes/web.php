<?php

use Illuminate\Support\Facades\Route;

use App\Features\Profile\Admin\Controllers\ProfileController;

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/profile/password', [ProfileController::class, 'passwordIndex'])->name('profile.password.index');
    Route::put('/profile/password', [ProfileController::class, 'passwordUpdate'])->name('profile.password.update');

    // Modular Profile Image Delivery
    Route::get('/profile-image/{filename}', [\App\Features\Profile\Shared\Controllers\ProfileImageController::class, 'serve'])->name('profile-image');
});
