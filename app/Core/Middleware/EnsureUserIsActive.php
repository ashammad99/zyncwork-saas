<?php

namespace App\Core\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Log out a user whose account, or whose organisation (tenant), was
     * suspended while they were signed in.
     *
     * LoginService only checks this at login; this closes existing sessions
     * on their next request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Tenant accounts only. Checked explicitly, not left to this running
        // before auth:platform switches the default guard to a PlatformAdmin.
        if ($request->getHost() === config('platform.domain')) {
            return $next($request);
        }

        $user = Auth::guard('web')->user();

        if (! $user || ($user->is_active && ! $user->tenant?->isSuspended())) {
            return $next($request);
        }

        $message = $user->is_active
            ? 'Your organisation\'s account has been suspended. Please contact support.'
            : 'Your account has been suspended. Please contact an administrator.';

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $request->session()->flash('errors', (new ViewErrorBag)->put('default', new MessageBag([
            'email' => $message,
        ])));

        if ($request->header('X-Inertia')) {
            return Inertia::location(route('login'));
        }

        if ($request->expectsJson()) {
            abort(401, $message);
        }

        return redirect()->route('login');
    }
}
