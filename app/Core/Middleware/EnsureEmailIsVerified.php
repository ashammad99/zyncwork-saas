<?php

namespace App\Core\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * A signed-in tenant user whose email is unverified (a new signup, or anyone
 * whose address changed) can do nothing but verify it or sign out.
 *
 * In the web group rather than per route file, so a new route is blocked by
 * default: page loads go to the "verify your email" screen and every other
 * request is refused on the server, whatever the UI shows.
 */
class EnsureEmailIsVerified
{
    /** The only routes an unverified user can reach. */
    private const ALLOWED_ROUTES = [
        'verification.notice',
        'verification.verify',
        'verification.send',
        'logout',
        // The public site (Landing): read-only pages, linked from the verify screen's footer.
        'landing.index',
        'landing.pricing',
        'landing.terms',
        'landing.privacy',
        'landing.refunds',
        'landing.contact',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Tenant accounts only (see EnsureUserIsActive).
        if ($request->getHost() === config('platform.domain')) {
            return $next($request);
        }

        $user = Auth::guard('web')->user();

        if (! $user || $user->hasVerifiedEmail() || in_array($request->route()?->getName(), self::ALLOWED_ROUTES, true)) {
            return $next($request);
        }

        if (! $request->isMethod('GET') && ! $request->isMethod('HEAD')) {
            abort(403, 'Please verify your email address first.');
        }

        if ($request->header('X-Inertia')) {
            return Inertia::location(route('verification.notice'));
        }

        if ($request->expectsJson()) {
            abort(403, 'Please verify your email address first.');
        }

        return redirect()->route('verification.notice');
    }
}
