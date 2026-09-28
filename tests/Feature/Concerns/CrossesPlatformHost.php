<?php

namespace Tests\Feature\Concerns;

use App\Features\Platform\Middleware\RequirePlatformTwoFactor;
use App\Features\Platform\Models\PlatformAdmin;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Facade;

/**
 * For tests that send requests to both the platform and the tenant host.
 *
 * UsePlatformHost swaps the session driver mid-request. In production each
 * request is a fresh process, so the session store and redirector are first
 * built after that swap. In tests the app outlives the request, so they would
 * keep pointing at the previous request's store: flashes and session
 * assertions would then read a store the response never saved.
 */
trait CrossesPlatformHost
{
    private function platform(string $path): string
    {
        return 'http://'.config('platform.domain').$path;
    }

    /** Console sessions need confirmed TOTP (RequirePlatformTwoFactor); returns the plain secret. */
    private function enrollTwoFactor(PlatformAdmin $admin): string
    {
        $secret = app(Google2FA::class)->generateSecretKey(32);

        $admin->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => [],
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $secret;
    }

    /** Later requests are tenant-host requests again, in a browser with no platform session. */
    private function backToTenantHost(): void
    {
        Auth::forgetGuards();
        Auth::shouldUse('web');
        // Relative test URLs resolve against the last request's host.
        $this->app['url']->setRequest(Request::create(config('app.url')));
    }

    /**
     * A real platform sign-in stores the password fingerprint in the session
     * (TwoFactorController), and RequirePlatformTwoFactor rejects sessions
     * without one, so acting as a platform admin stores it too.
     */
    public function actingAs(\Illuminate\Contracts\Auth\Authenticatable $user, $guard = null)
    {
        if ($guard === 'platform') {
            $this->putInPlatformSession([RequirePlatformTwoFactor::PASSWORD_FINGERPRINT => $user->passwordFingerprint()]);
        }

        return parent::actingAs($user, $guard);
    }

    /**
     * withSession() writes to the current store, which UsePlatformHost throws
     * away when the next request is on the other host. Build the platform
     * host's store now (as UsePlatformHost would) so that request keeps it.
     */
    private function putInPlatformSession(array $data): void
    {
        $cookie = config('session.cookie');
        config(['session.cookie' => config('platform.session.cookie')]);

        if (app('session')->driver()->getName() !== config('session.cookie')) {
            app('session')->forgetDrivers();
        }
        app('session')->driver()->put($data);

        config(['session.cookie' => $cookie]);
    }

    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        foreach (['session.store', 'redirect'] as $abstract) {
            $this->app->forgetInstance($abstract);
            Facade::clearResolvedInstance($abstract);
        }

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }
}
