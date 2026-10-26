<?php

use App\Features\Notification\User\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;

// Notification/User web routes
Route::middleware(['auth', 'role:employee,manager'])->group(function () {
    Route::get('/notification', [NotificationController::class, 'index'])->name('notification.index');
    Route::post('/notification/read-all', [NotificationController::class, 'readAll'])->name('notification.read-all');
    Route::get('/notification/{id}', [NotificationController::class, 'read'])->name('notification.read');
});
