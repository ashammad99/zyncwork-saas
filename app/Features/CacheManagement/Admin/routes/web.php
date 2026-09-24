<?php

use Illuminate\Support\Facades\Route;
use App\Features\CacheManagement\Admin\Controllers\CacheController;

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/cache', [CacheController::class, 'index'])->name('cache.index');
    Route::post('/cache/clear', [CacheController::class, 'clear'])->name('cache.clear');
});
