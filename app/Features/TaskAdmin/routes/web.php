<?php

use App\Features\TaskAdmin\Controllers\AdminTaskController;
use Illuminate\Support\Facades\Route;

// TaskAdmin web routes


/*
|--------------------------------------------------------------------------
| Admin — Task Routes
|--------------------------------------------------------------------------
| All routes are protected by auth + verified + role:Super Admin
| (middleware is applied inside AdminTaskController constructor)
*/

Route::prefix('admin/tasks')->name('admin.tasks.')->group(function () {
    Route::get('/', [AdminTaskController::class, 'index'])->name('index');
    Route::post('/', [AdminTaskController::class, 'store'])->name('store');
    Route::get('/{task}', [AdminTaskController::class, 'show'])->name('show');
    Route::put('/{task}', [AdminTaskController::class, 'update'])->name('update');
    Route::patch('/{task}/status', [AdminTaskController::class, 'updateStatus'])->name('update-status');
    Route::patch('/{task}/assign', [AdminTaskController::class, 'assign'])->name('assign');
    Route::delete('/{task}', [AdminTaskController::class, 'destroy'])->name('destroy');
});
