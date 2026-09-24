<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\File;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        health: '/up',
        using: function () {
            $featuresPath = app_path('Features');

            if (! File::isDirectory($featuresPath)) {
                return;
            }

            foreach (File::directories($featuresPath) as $featurePath) {
                $hasRoleRoutes = false;

                // Check subdirectories for role-based features (Dashboard/Admin, Dashboard/User)
                foreach (File::directories($featurePath) as $subPath) {
                    $webRoute = $subPath . '/routes/web.php';
                    $apiRoute = $subPath . '/routes/api.php';

                    if (File::exists($webRoute) || File::exists($apiRoute)) {
                        $hasRoleRoutes = true;
                        $role          = strtolower(basename($subPath));

                        if (File::exists($webRoute)) {
                            Route::middleware('web')
                                ->prefix($role)
                                ->name($role . '.')
                                ->group($webRoute);
                        }

                        if (File::exists($apiRoute)) {
                            Route::middleware('api')
                                ->prefix('api/' . $role)
                                ->name('api.' . $role . '.')
                                ->group($apiRoute);
                        }
                    }
                }

                // Simple feature (Auth, Notification, etc.)
                if (! $hasRoleRoutes) {
                    $webRoute = $featurePath . '/routes/web.php';
                    $apiRoute = $featurePath . '/routes/api.php';

                    if (File::exists($webRoute)) {
                        Route::middleware('web')
                            ->group($webRoute);
                    }

                    if (File::exists($apiRoute)) {
                        Route::middleware('api')
                            ->prefix('api')
                            ->group($apiRoute);
                    }
                }
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Core\Middleware\EnsureUserIsActive::class,
            \App\Core\Middleware\HandleInertiaRequests::class,
        ]);

        // Middleware aliases
        $middleware->alias([
            'role' => \App\Core\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->withCommands([
        __DIR__ . '/../app/Console/Commands',
    ])

    ->create();
