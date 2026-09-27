<?php

use App\Features\Platform\Controllers\AuthController;
use App\Features\Platform\Controllers\BillingController;
use App\Features\Platform\Controllers\CacheController;
use App\Features\Platform\Controllers\HealthController;
use App\Features\Platform\Controllers\ImpersonationController;
use App\Features\Platform\Controllers\TenantController;
use Illuminate\Support\Facades\Route;

// Platform console: served only on the platform host (config/platform.php).
// Everything else 404s there (UsePlatformHost), and these never match the tenant host.
Route::domain(config('platform.domain'))->name('platform.')->group(function () {
    Route::middleware('guest:platform')->group(function () {
        Route::get('/login', [AuthController::class, 'index'])->name('login');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('login.attempt');
    });

    Route::middleware('auth:platform')->group(function () {
        Route::redirect('/', '/tenants')->name('home');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

        Route::get('/tenants', [TenantController::class, 'index'])->name('tenants.index');
        Route::get('/tenants/{tenant}', [TenantController::class, 'show'])->name('tenants.show');

        Route::middleware('can:platform.suspend_tenants')->group(function () {
            Route::post('/tenants/{tenant}/suspend', [TenantController::class, 'suspend'])->name('tenants.suspend');
            Route::post('/tenants/{tenant}/unsuspend', [TenantController::class, 'unsuspend'])->name('tenants.unsuspend');
        });

        Route::post('/tenants/{tenant}/impersonate', [ImpersonationController::class, 'start'])
            ->middleware('can:platform.impersonate')
            ->name('tenants.impersonate');

        // Cache clearing affects every tenant, so it sits with the system-health power.
        Route::middleware('can:platform.view_system_health')->group(function () {
            Route::get('/health', [HealthController::class, 'index'])->name('health');
            Route::get('/cache', [CacheController::class, 'index'])->name('cache.index');
            Route::post('/cache/clear', [CacheController::class, 'clear'])->name('cache.clear');
        });

        Route::get('/billing', [BillingController::class, 'index'])
            ->middleware('can:platform.view_billing')
            ->name('billing.index');
    });
});

// Tenant host: where a support session is handed off and ended.
Route::get('/impersonation/{token}', [ImpersonationController::class, 'consume'])
    ->middleware('throttle:20,1')
    ->name('impersonation.consume');
Route::post('/impersonation/stop', [ImpersonationController::class, 'stop'])
    ->middleware('auth')
    ->name('impersonation.stop');
