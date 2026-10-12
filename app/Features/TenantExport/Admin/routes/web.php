<?php

use App\Features\TenantExport\Admin\Controllers\TenantExportController;
use Illuminate\Support\Facades\Route;

// Tenant admins only. Mounted at /admin/exports (names admin.exports.*) by bootstrap/app.php.
// Both are blocked during platform support sessions (EnforceImpersonation::BILLING_ROUTES).
Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::post('/exports', [TenantExportController::class, 'store'])->name('exports.store');
    Route::get('/exports/{export}/download', [TenantExportController::class, 'download'])
        ->middleware('signed')
        ->name('exports.download');
});
