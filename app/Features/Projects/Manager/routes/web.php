<?php

// routes/web.php — add inside your manager middleware group
// Middleware: ['auth', 'role:Department Manager']


use App\Features\Projects\Manager\Controllers\ProjectController;

Route::middleware(['auth'])
    ->group(function () {

        // ... your existing manager routes ...

        Route::resource('projects', ProjectController::class);  // ← add here

    });
