<?php

use Illuminate\Support\Facades\Route;
use App\Features\Report\Controllers\BurndownController;

// Report web routes
Route::middleware(['auth', 'role:admin,manager'])->group(function () {
    Route::get('/reports/burndown', [BurndownController::class, 'index'])
        ->name('reports.burndown');
});
