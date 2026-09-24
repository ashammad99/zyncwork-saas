<?php

use Illuminate\Support\Facades\Route;

// TaskEmployee web routes
use App\Features\TaskEmployee\Controllers\EmployeeTaskController;


Route::middleware(['auth', 'role:employee'])
    ->prefix('employee/tasks')
    ->name('employee.tasks.')
    ->group(function () {

        // List assigned tasks
        Route::get('/', [EmployeeTaskController::class, 'index'])
            ->name('index');

        // View a specific task
        Route::get('/{task}', [EmployeeTaskController::class, 'show'])
            ->name('show');

        // Update task status (Todo → In Progress → In Review)
        Route::patch('/{task}/status', [EmployeeTaskController::class, 'updateStatus'])
            ->name('updateStatus');
    });
