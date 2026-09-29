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
            $this->useSessionStoreFor(config('session.cookie'));

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
        $this->useSessionStoreFor(config('session.cookie'));

        try {
            return $next($request);
        } finally {
            // The session is saved inside $next; this only matters when the
            // process serves further requests (tests, long-running servers).
            config(['session' => $original]);
        }
    }

    /**
     * The session store reads its cookie name and lifetime when it is built.
     * Rebuild it (with the current config) only when it was built for the
     * other host, so a process serving several requests never mixes them up.
     */
    private function useSessionStoreFor(string $cookie): void
    {
        if (app('session')->driver()->getName() !== $cookie) {
            app('session')->forgetDrivers();
        }
    }
}
