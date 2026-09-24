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
     * Log out a user whose account was suspended while they were signed in.
     *
     * LoginService only checks is_active at login; this closes existing sessions
     * on their next request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->is_active) {
            return $next($request);
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $request->session()->flash('errors', (new ViewErrorBag)->put('default', new MessageBag([
            'email' => 'Your account has been suspended. Please contact an administrator.',
        ])));

        if ($request->header('X-Inertia')) {
            return Inertia::location(route('login'));
        }

        if ($request->expectsJson()) {
            abort(401, 'Your account has been suspended.');
        }

        return redirect()->route('login');
    }
}
