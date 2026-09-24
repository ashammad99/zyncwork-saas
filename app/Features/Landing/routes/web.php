<?php

use App\Features\Landing\Controllers\LandingController;
use Illuminate\Support\Facades\Route;

Route::get('/', [LandingController::class, 'index'])->name('landing.index');
Route::get('/documentation', [LandingController::class, 'docs'])->name('landing.docs');
Route::get('/features', [LandingController::class, 'features'])->name('landing.features');
