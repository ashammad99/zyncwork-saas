<?php

namespace App\Features\Platform\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every signed-in platform session must belong to an admin with confirmed
 * TOTP. Signing in already requires it; this also ends sessions whose 2FA
 * was reset (`platform:admin --reset-2fa`) or that predate it.
 */
class RequirePlatformTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('platform')->user();

        if ($admin && ! $admin->hasTwoFactor()) {
            Auth::guard('platform')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('platform.login')
                ->withErrors(['email' => 'Two-factor authentication is required. Please sign in again.']);
        }

        return $next($request);
    }
}
