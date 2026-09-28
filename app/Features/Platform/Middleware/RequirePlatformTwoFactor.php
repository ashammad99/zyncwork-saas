<?php

namespace App\Features\Platform\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every signed-in platform session must still be valid, checked on each
 * request (the admin is loaded fresh from the database every time):
 * - the admin must be active (`platform:admin --deactivate`);
 * - the password must be the one the session signed in with (`--reset-password`);
 * - TOTP must be confirmed. Signing in already requires it; this also ends
 *   sessions whose 2FA was reset (`--reset-2fa`) or that predate it.
 * Capabilities are checked per request by the can:platform.* gates.
 */
class RequirePlatformTwoFactor
{
    /** Session key for the admin's password fingerprint at sign-in. */
    public const PASSWORD_FINGERPRINT = 'platform.password_fingerprint';

    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('platform')->user();

        if (! $admin) {
            return $next($request);
        }

        if (! $admin->is_active || ! $this->passwordUnchanged($request, $admin)) {
            return $this->signOut($request, 'Your session has ended. Please sign in again.');
        }

        if (! $admin->hasTwoFactor()) {
            return $this->signOut($request, 'Two-factor authentication is required. Please sign in again.');
        }

        return $next($request);
    }

    /** A session without a fingerprint never went through sign-in (TwoFactorController), so it is not trusted. */
    private function passwordUnchanged(Request $request, $admin): bool
    {
        $stored = $request->session()->get(self::PASSWORD_FINGERPRINT);

        return is_string($stored) && hash_equals($stored, $admin->passwordFingerprint());
    }

    private function signOut(Request $request, string $message): Response
    {
        Auth::guard('platform')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('platform.login')->withErrors(['email' => $message]);
    }
}
