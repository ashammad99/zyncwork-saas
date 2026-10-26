<?php

use App\Features\Landing\Controllers\LandingController;
use Illuminate\Support\Facades\Route;

// The public site. Read-only pages for everyone, signed in or not; an
// unverified user can reach them too (EnsureEmailIsVerified::ALLOWED_ROUTES).
Route::get('/', [LandingController::class, 'index'])->name('landing.index');
Route::get('/pricing', [LandingController::class, 'pricing'])->name('landing.pricing');
Route::get('/terms', [LandingController::class, 'legal'])->defaults('document', 'terms')->name('landing.terms');
Route::get('/privacy', [LandingController::class, 'legal'])->defaults('document', 'privacy')->name('landing.privacy');
Route::get('/refund-policy', [LandingController::class, 'legal'])->defaults('document', 'refunds')->name('landing.refunds');
Route::get('/contact', [LandingController::class, 'contact'])->name('landing.contact');
