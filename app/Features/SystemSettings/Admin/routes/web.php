<?php

use Illuminate\Support\Facades\Route;
use App\Features\SystemSettings\Admin\Controllers\SettingsController;

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::patch('/settings', [SettingsController::class, 'update'])->name('settings.update');

});
