<?php

use App\Features\Report\Controllers\BurndownController;
use Illuminate\Support\Facades\Route;

// Report web routes
Route::middleware(['auth', 'role:admin,manager'])->group(function () {
    Route::get('/reports/burndown', [BurndownController::class, 'index'])
        ->name('reports.burndown');
});
