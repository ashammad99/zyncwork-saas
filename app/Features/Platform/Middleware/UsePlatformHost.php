<?php

namespace App\Features\Platform\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs first in the web group, before the session starts.
 *
 * On the platform host: only platform.* routes exist (tenant pages 404),
 * and the session uses its own cookie, a shorter lifetime, SameSite=strict,
 * and ends when the browser closes. The tenant host is left untouched.
 */
class UsePlatformHost
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->getHost() !== config('platform.domain')) {
            return $next($request);
        }

        if (! str_starts_with((string) $request->route()?->getName(), 'platform.')) {
            abort(404);
        }

        $original = config('session');

        config([
            'session.cookie' => config('platform.session.cookie'),
            'session.lifetime' => config('platform.session.lifetime'),
            'session.expire_on_close' => true,
            'session.same_site' => 'strict',
        ]);
        // The session store reads its cookie name when it is built; rebuild it with these settings.
        app('session')->forgetDrivers();

        try {
            return $next($request);
        } finally {
            // The session is saved inside $next, so this only matters when the
            // app serves further requests (tests): the next one starts clean.
            config(['session' => $original]);
            app('session')->forgetDrivers();
        }
    }
}
