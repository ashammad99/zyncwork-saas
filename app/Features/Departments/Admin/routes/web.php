<?php

use Illuminate\Support\Facades\Route;

// Departments/Admin web routes
use App\Features\Departments\Admin\Controllers\DepartmentController;

Route::middleware(['auth', 'role:admin'])
    ->group(function () {
        Route::get('/departments', [DepartmentController::class, 'index'])->name('admin.departments.index');
        Route::post('/departments', [DepartmentController::class, 'store'])->name('admin.departments.store');
        Route::put('/departments/{department}', [DepartmentController::class, 'update'])->name('admin.departments.update');
        Route::delete('/departments/{department}', [DepartmentController::class, 'destroy'])->name('admin.departments.destroy');
    });
