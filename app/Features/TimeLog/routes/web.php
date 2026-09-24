<?php

use App\Features\TimeLog\Controllers\AdminTimeLogController;
use App\Features\TimeLog\Controllers\TimeLogController;
use App\Features\TimeLog\Controllers\TimeLogExportController;
use App\Features\TimeLog\Controllers\TimesheetController;
use Illuminate\Support\Facades\Route;


// ── Timesheet views (employee + manager) ───────────────────────────────────
Route::middleware(['auth'])->group(function () {

    // Employee: personal weekly timesheet
    Route::get('timesheets', [TimesheetController::class, 'index'])
        ->name('timesheets.index');

    // Manager: list team members' timesheets
    Route::get('manager/timesheets', [TimesheetController::class, 'managerIndex'])
        ->middleware('role:manager')
        ->name('manager.timesheets.index');

    // Manager: export CSV — STATIC, must precede {user}
    Route::get('manager/timesheets/export', [TimeLogExportController::class, 'manager'])
        ->middleware('role:manager')
        ->name('manager.timesheets.export');

    // Manager: export PDF — STATIC, must precede {user}
    Route::get('manager/timesheets/export-pdf', [TimeLogExportController::class, 'managerPdf'])
        ->middleware('role:manager')
        ->name('manager.timesheets.exportPdf');

});

// ── Admin time-log oversight ───────────────────────────────────────────────
Route::middleware(['auth', 'role:admin'])
    ->prefix('admin/timesheets')
    ->name('admin.timesheets.')
    ->group(function () {
// admin group:
        Route::get('/export-pdf', [TimeLogExportController::class, 'adminPdf'])->name('exportPdf');


        // All time logs, filterable
        Route::get('/', [AdminTimeLogController::class, 'index'])
            ->name('index');
// Export CSV (respects current filters)
        Route::get('/export', [\App\Features\TimeLog\Controllers\TimeLogExportController::class, 'admin'])
            ->name('export');
        // Approval queue — STATIC routes must come before {user}
        Route::get('/pending', [AdminTimeLogController::class, 'pending'])
            ->name('pending');
        // Aggregate reports
        Route::get('/reports', [AdminTimeLogController::class, 'reports'])
            ->name('reports');

        // Bulk approve selected entries
        Route::post('/bulk-approve', [AdminTimeLogController::class, 'bulkApprove'])
            ->name('bulkApprove');

        // Admin: view a specific user's weekly timesheet — LAST (dynamic segment)

    });

// ── Task-scoped time-log actions ───────────────────────────────────────────
Route::middleware(['auth'])->group(function () {
    Route::post('tasks/{task}/time-logs/start', [TimeLogController::class, 'start'])
        ->name('time-logs.start');

    Route::patch('tasks/{task}/time-logs/{timeLog}/stop', [TimeLogController::class, 'stop'])
        ->name('time-logs.stop');

    Route::post('tasks/{task}/time-logs/manual', [TimeLogController::class, 'storeManual'])
        ->name('time-logs.manual');

    Route::delete('tasks/{task}/time-logs/{timeLog}', [TimeLogController::class, 'destroy'])
        ->name('time-logs.destroy');

    Route::patch('tasks/{task}/time-logs/{timeLog}/approve', [TimeLogController::class, 'approve'])
        ->name('time-logs.approve');

    Route::patch('tasks/{task}/time-logs/{timeLog}/reject', [TimeLogController::class, 'reject'])
        ->name('time-logs.reject');

    Route::get('tasks/{task}/time-logs/active', [TimeLogController::class, 'activeTimer'])
        ->name('time-logs.active');
    Route::post('manager/timesheets/{timeLog}/approve', [TimeLogController::class, 'managerApprove'])
        ->name('manager.timesheets.approve');

    Route::post('manager/timesheets/{timeLog}/reject', [TimeLogController::class, 'managerReject'])
        ->name('manager.timesheets.reject');
});


