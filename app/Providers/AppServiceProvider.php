<?php

namespace App\Providers;

use App\Features\Projects\Admin\Models\Project;
use App\Features\Projects\Manager\Policies\ProjectPolicy;
use App\Features\SystemSettings\Admin\Models\Setting;
use App\Features\SystemSettings\Admin\Policies\SettingPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Setting::class, SettingPolicy::class);

        \Illuminate\Support\Facades\Event::listen(
            \App\Features\Notification\Events\NotificationCreated::class,
            [\App\Features\Notification\Listeners\CreateNotificationRecord::class, 'handle']
        );

        \Illuminate\Support\Facades\Event::listen(
            \App\Features\ActivityLog\Events\ActivityLogged::class,
            [\App\Features\ActivityLog\Listeners\CreateActivityLogRecord::class, 'handle']
        );
    }
}
