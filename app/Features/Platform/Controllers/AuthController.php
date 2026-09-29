<?php

namespace App\Features\Platform\Controllers;

use App\Core\BaseController;
use App\Features\Platform\Models\PlatformAuditLog;
use App\Features\Platform\Requests\PlatformLoginRequest;
use App\Features\Platform\Services\PlatformAuditLogger;
use App\Features\Platform\Services\PlatformTwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Login for platform staff, on the platform host only. Deliberately shares
 * nothing with the tenant login (LoginService): other guard, other table,
 * other session cookie, no "remember me".
 */
class AuthController extends BaseController
{
    public function __construct(
        protected PlatformAuditLogger $audit,
        protected PlatformTwoFactorService $twoFactor,
    ) {}

    public function index()
    {
        return view('pages.platform.login.page');
    }

    /**
     * Password step only: nobody is signed in until TwoFactorController
     * accepts a code (or, for an admin without 2FA yet, a confirmed setup).
     */
    public function login(PlatformLoginRequest $request)
    {
        $credentials = $request->only('email', 'password') + ['is_active' => true];
        $guard = Auth::guard('platform');

        if (! $guard->validate($credentials)) {
            $this->audit->record(PlatformAuditLog::LOGIN_FAILED, null, [
                'metadata' => ['email' => $request->input('email')],
            ]);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $admin = $guard->getLastAttempted();

        $request->session()->regenerate();
        // Drop any earlier, unfinished sign-in first: its setup secret must never reach this admin.
        $this->twoFactor->forgetPending($request->session());
        $this->twoFactor->rememberPending($request->session(), $admin);

        return redirect()->route($admin->hasTwoFactor() ? 'platform.two-factor.challenge' : 'platform.two-factor.setup');
    }

    public function logout(Request $request)
    {
        $this->audit->record(PlatformAuditLog::LOGOUT, $request->user('platform'));

        Auth::guard('platform')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Inertia::location(route('platform.login'));
    }
}
