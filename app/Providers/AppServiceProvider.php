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
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

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
        if ($this->app->isProduction() && blank(config('platform.domain'))) {
            throw new RuntimeException('PLATFORM_DOMAIN must be set in production (see config/platform.php).');
        }

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

        $this->configureRateLimiting();
    }

    /**
     * Route limits, used as throttle:<name>. Always a named limiter, never a
     * plain throttle:N,M: that keys only on the user or IP, so every route
     * using it shares one counter, whose window is set by whichever route
     * was hit first (one route's short window then resets another's long one).
     * A named limiter's key includes its name.
     */
    private function configureRateLimiting(): void
    {
        $byUserOrIp = fn (Request $request) => $request->user()?->getAuthIdentifier() ?? $request->ip();

        // Tenant sign-in: across all emails from one IP (password spraying).
        // LoginService also limits each email and IP.
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));

        RateLimiter::for('signup', fn (Request $request) => Limit::perHour((int) config('onboarding.signup.per_hour'))->by($request->ip()));
        RateLimiter::for('verification-verify', fn (Request $request) => Limit::perMinute(6)->by($byUserOrIp($request)));
        RateLimiter::for('verification-send', fn (Request $request) => Limit::perMinute(6)->by($byUserOrIp($request)));
        RateLimiter::for('invitation-accept', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));

        // Every invite or resend attempt, valid or not, per tenant: rejected
        // attempts must cost something, or the platform-wide "email is taken"
        // check could be used to test addresses without limit.
        RateLimiter::for('invitations', fn (Request $request) => Limit::perHour((int) config('onboarding.invitations.per_hour'))
            ->by((string) $request->user()?->tenant_id)
            ->response(fn () => redirect()->back()->with('error', 'Too many invitations in the last hour. Please try again later.')));

        RateLimiter::for('impersonation-consume', fn (Request $request) => Limit::perMinute(20)->by($request->ip()));

        // The password and code steps each have their own budget.
        RateLimiter::for('platform-login', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('platform-two-factor', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('platform-two-factor-setup', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
    }
}
