<?php

namespace App\Providers;

use App\Features\Platform\Models\PlatformAdmin;
use App\Features\Platform\Models\PlatformImpersonation;
use App\Features\Platform\Services\ImpersonationService;
use App\Features\Projects\Admin\Models\Project;
use App\Features\Projects\Manager\Policies\ProjectPolicy;
use App\Features\SystemSettings\Admin\Models\Setting;
use App\Features\SystemSettings\Admin\Policies\SettingPolicy;
use Illuminate\Auth\Events\Logout;
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

        // Platform powers (can:platform.<capability>). Tenant users never pass these.
        foreach (PlatformAdmin::CAPABILITIES as $capability) {
            Gate::define("platform.{$capability}", fn ($user) => $user instanceof PlatformAdmin && $user->hasCapability($capability));
        }

        // Signing out of a support session (or being signed out) ends it.
        \Illuminate\Support\Facades\Event::listen(Logout::class, function (Logout $event) {
            if ($event->guard !== 'web' || ! request()->hasSession()) {
                return;
            }

            $impersonation = PlatformImpersonation::find(request()->session()->get('impersonation_id'));

            if ($impersonation) {
                app(ImpersonationService::class)->end($impersonation, PlatformImpersonation::END_LOGGED_OUT);
            }
        });
    }
}
