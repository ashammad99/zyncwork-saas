<?php

use App\Features\ActivityLog\Admin\Controllers\ActivityLogController;
use Illuminate\Support\Facades\Route;

// ActivityLog/Admin web routes
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('admin.activity-logs.index');
});
