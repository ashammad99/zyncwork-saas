<?php

use App\Features\Notification\Admin\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

// Notification/Admin web routes
Route::middleware('auth', 'role:admin')->group(function () {
    Route::get('/notification', [NotificationController::class, 'index'])->name('notifications.index');
    Route::get('/notification/{id}', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('/notification/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
});
