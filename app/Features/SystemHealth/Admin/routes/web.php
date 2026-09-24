<?php

use Illuminate\Support\Facades\Route;
use App\Features\SystemHealth\Admin\Controllers\HealthController;

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/system/health', [HealthController::class, 'index'])->name('system.health');
});
