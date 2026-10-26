<?php

use App\Features\Dashboard\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

// Dashboard web routes
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');
});
