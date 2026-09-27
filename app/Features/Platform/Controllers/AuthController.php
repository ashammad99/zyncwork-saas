<?php

namespace App\Features\Platform\Controllers;

use App\Core\BaseController;
use App\Features\Platform\Models\PlatformAuditLog;
use App\Features\Platform\Requests\PlatformLoginRequest;
use App\Features\Platform\Services\PlatformAuditLogger;
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
        protected PlatformAuditLogger $audit
    ) {}

    public function index()
    {
        return view('pages.platform.login.page');
    }

    public function login(PlatformLoginRequest $request)
    {
        $credentials = $request->only('email', 'password') + ['is_active' => true];

        if (! Auth::guard('platform')->attempt($credentials)) {
            $this->audit->record(PlatformAuditLog::LOGIN_FAILED, null, [
                'metadata' => ['email' => $request->input('email')],
            ]);

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $request->session()->regenerate();

        $admin = Auth::guard('platform')->user();
        $admin->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();

        $this->audit->record(PlatformAuditLog::LOGIN, $admin);

        return redirect()->intended(route('platform.tenants.index'));
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
