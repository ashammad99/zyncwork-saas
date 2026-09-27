<?php

namespace Tests\Feature\Concerns;

use Illuminate\Http\Request;
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

    /** Later requests are tenant-host requests again, in a browser with no platform session. */
    private function backToTenantHost(): void
    {
        Auth::forgetGuards();
        Auth::shouldUse('web');
        // Relative test URLs resolve against the last request's host.
        $this->app['url']->setRequest(Request::create(config('app.url')));
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
