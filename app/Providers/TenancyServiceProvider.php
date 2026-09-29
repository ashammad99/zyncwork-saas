<?php

namespace App\Providers;

use App\Core\Tenancy\TenantAwareUserProvider;
use App\Core\Tenancy\TenantContext;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the tenant context:
 * - web requests: the tenant of the authenticated web-guard user;
 * - queued jobs: the tenant that was current when the job was dispatched.
 */
class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
    }

    public function boot(): void
    {
        // config/auth.php: providers.users.driver = 'tenant-eloquent'
        Auth::provider('tenant-eloquent', function ($app, array $config) {
            return new TenantAwareUserProvider($app['hash'], $config['model']);
        });

        $context = fn (): TenantContext => $this->app->make(TenantContext::class);

        // Authenticated fires whenever the web guard resolves a user (session,
        // remember-me, actingAs); Login fires on Auth::attempt()/login().
        Event::listen([Authenticated::class, Login::class], function (Authenticated|Login $event) use ($context) {
            if ($event->guard === 'web') {
                $context()->set($event->user->tenant_id);
            }
        });

        Event::listen(Logout::class, function (Logout $event) use ($context) {
            if ($event->guard === 'web') {
                $context()->forget();
            }
        });

        // Carry the tenant into every queued job and restore it in the worker.
        // A job dispatched without a tenant runs without one, and fails closed.
        Queue::createPayloadUsing(fn () => ['tenant_id' => $context()->id()]);

        Event::listen(JobProcessing::class, function (JobProcessing $event) use ($context) {
            $context()->push($event->job->payload()['tenant_id'] ?? null);
        });

        Event::listen([JobProcessed::class, JobExceptionOccurred::class], fn () => $context()->pop());
    }
}
