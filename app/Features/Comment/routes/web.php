<?php

use App\Features\Comment\Controllers\CommentController;
use Illuminate\Support\Facades\Route;

// Comment web routes

Route::middleware(['auth'])->group(function () {
    Route::post('/tasks/{task}/comments', [CommentController::class, 'store'])
        ->name('comments.store');
    Route::put('/comments/{comment}', [CommentController::class, 'update'])
        ->name('comments.update');
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])
        ->name('comments.destroy');
});
