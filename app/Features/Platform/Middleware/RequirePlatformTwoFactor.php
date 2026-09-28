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

    private function passwordUnchanged(Request $request, $admin): bool
    {
        $session = $request->session();

        // Sessions signed in before this check existed get the current fingerprint.
        if (! $session->has(self::PASSWORD_FINGERPRINT)) {
            $session->put(self::PASSWORD_FINGERPRINT, $admin->passwordFingerprint());
        }

        return hash_equals($session->get(self::PASSWORD_FINGERPRINT), $admin->passwordFingerprint());
    }

    private function signOut(Request $request, string $message): Response
    {
        Auth::guard('platform')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('platform.login')->withErrors(['email' => $message]);
    }
}
