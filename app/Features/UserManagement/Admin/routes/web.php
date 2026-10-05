<?php

use Illuminate\Support\Facades\Route;
use App\Features\UserManagement\Admin\Controllers\InvitationController;
use App\Features\UserManagement\Admin\Controllers\UserController;

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');

    // New users join only by invitation (Onboarding\Services\InvitationService).
    Route::post('/invitations', [InvitationController::class, 'store'])->middleware('throttle:invitations')->name('invitations.store');
    Route::post('/invitations/{invitation}/resend', [InvitationController::class, 'resend'])->middleware('throttle:invitations')->name('invitations.resend');
    Route::delete('/invitations/{invitation}', [InvitationController::class, 'destroy'])->name('invitations.destroy');
});
