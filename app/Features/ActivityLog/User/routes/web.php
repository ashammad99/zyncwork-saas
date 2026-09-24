<?php

use App\Features\ActivityLog\User\Controllers\ActivityLogController;
use Illuminate\Support\Facades\Route;

// ActivityLog/User web routes
Route::middleware(['auth', 'role:employee'])->group(function () {

    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('user.activity-logs.index');
});
