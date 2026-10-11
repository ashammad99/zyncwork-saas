<?php

use App\Features\Billing\Admin\Controllers\BillingController;
use Illuminate\Support\Facades\Route;

// Tenant admins only. Mounted at /admin/billing (names admin.billing.*) by bootstrap/app.php.
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/billing', [BillingController::class, 'index'])->name('billing.index');
    // Blocked during platform support sessions (EnforceImpersonation::BILLING_ROUTES).
    Route::put('/billing/email', [BillingController::class, 'updateEmail'])->name('billing.email.update');
});
