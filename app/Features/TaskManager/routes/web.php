<?php

use App\Features\TaskManager\Controllers\ManagerTaskController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:manager'])
    ->prefix('manager/tasks')
    ->name('manager.tasks.')
    ->group(function () {

        Route::get('/',            [ManagerTaskController::class, 'index'])->name('index');
        Route::get('/create',      [ManagerTaskController::class, 'create'])->name('create');
        Route::post('/',           [ManagerTaskController::class, 'store'])->name('store');
        Route::get('/{task}',      [ManagerTaskController::class, 'show'])->name('show');
        Route::get('/{task}/edit', [ManagerTaskController::class, 'edit'])->name('edit');
        Route::put('/{task}',      [ManagerTaskController::class, 'update'])->name('update');
        Route::delete('/{task}',   [ManagerTaskController::class, 'destroy'])->name('destroy');

        // Status transition — separate endpoint for clarity
        Route::patch('/{task}/status', [ManagerTaskController::class, 'updateStatus'])->name('updateStatus');

        // AJAX: fetch tasks for dependency dropdown
        Route::get('/dependency-options/{projectId}', [ManagerTaskController::class, 'dependencyOptions'])
            ->name('dependencyOptions');
        Route::patch('/{task}/assign', [ManagerTaskController::class, 'assign'])->name('assign');

    });
Route::middleware(['auth', 'role:manager'])->group(function () {
    Route::get('/manager/team', [ManagerTaskController::class, 'team'])
        ->name('manager.team.index');
});
