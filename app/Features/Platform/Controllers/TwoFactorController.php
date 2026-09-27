<?php

namespace App\Features\Platform\Controllers;

use App\Core\BaseController;
use App\Features\Platform\Models\PlatformAdmin;
use App\Features\Platform\Models\PlatformAuditLog;
use App\Features\Platform\Requests\TwoFactorChallengeRequest;
use App\Features\Platform\Requests\TwoFactorSetupRequest;
use App\Features\Platform\Services\PlatformAuditLogger;
use App\Features\Platform\Services\PlatformTwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Second sign-in step for platform admins. Every admin has TOTP: one
 * without it is sent to setup (forced at first sign-in) and can do nothing
 * else until a code confirms the new secret.
 */
class TwoFactorController extends BaseController
{
    public function __construct(
        protected PlatformTwoFactorService $twoFactor,
        protected PlatformAuditLogger $audit,
    ) {}

    public function challenge(Request $request)
    {
        $admin = $this->twoFactor->pendingAdmin($request->session());

        if (! $admin) {
            return $this->backToLogin();
        }

        if (! $admin->hasTwoFactor()) {
            return redirect()->route('platform.two-factor.setup');
        }

        return view('pages.platform.two-factor.page');
    }

    public function verify(TwoFactorChallengeRequest $request)
    {
        $admin = $this->twoFactor->pendingAdmin($request->session());

        if (! $admin || ! $admin->hasTwoFactor()) {
            return $this->backToLogin();
        }

        if ($request->filled('recovery_code')) {
            $remaining = $this->twoFactor->useRecoveryCode($admin, $request->input('recovery_code'));

            if ($remaining === null) {
                $this->failed($admin, 'recovery_code');
            }

            $this->audit->record(PlatformAuditLog::TWO_FACTOR_RECOVERY_USED, $admin, ['metadata' => ['remaining' => $remaining]]);

            return $this->signIn($request, $admin, 'recovery_code');
        }

        if (! $this->twoFactor->verify($admin, $request->input('code'))) {
            $this->failed($admin, 'code');
        }

        return $this->signIn($request, $admin, 'totp');
    }

    public function setup(Request $request)
    {
        $admin = $this->twoFactor->pendingAdmin($request->session());

        if (! $admin) {
            return $this->backToLogin();
        }

        if ($admin->hasTwoFactor()) {
            return redirect()->route('platform.two-factor.challenge');
        }

        $secret = $this->twoFactor->setupSecret($request->session());

        // Shows the secret: keep it out of the browser cache.
        return response()->view('pages.platform.two-factor-setup.page', [
            'qrCode' => $this->twoFactor->qrCodeSvg($admin, $secret),
            'secret' => $secret,
        ])->header('Cache-Control', 'no-store');
    }

    public function enable(TwoFactorSetupRequest $request)
    {
        $admin = $this->twoFactor->pendingAdmin($request->session());

        if (! $admin || $admin->hasTwoFactor()) {
            return $this->backToLogin();
        }

        $secret = $this->twoFactor->setupSecret($request->session());
        $step = $this->twoFactor->matchingStep($secret, $request->input('code'));

        if ($step === null) {
            $this->failed($admin, 'code', 'setup');
        }

        $codes = $this->twoFactor->enable($admin, $secret, $step);
        $this->audit->record(PlatformAuditLog::TWO_FACTOR_ENABLED, $admin);

        $this->signIn($request, $admin, 'setup');

        return redirect()->route('platform.two-factor.recovery-codes')->with('recovery_codes', $codes);
    }

    /** Shown once, straight after setup (flashed); afterwards there is nothing to show. */
    public function recoveryCodes(Request $request)
    {
        $codes = $request->session()->get('recovery_codes');

        if (! $codes) {
            return redirect()->route('platform.tenants.index');
        }

        return response()->view('pages.platform.recovery-codes.page', ['codes' => $codes])
            ->header('Cache-Control', 'no-store');
    }

    private function signIn(Request $request, PlatformAdmin $admin, string $method)
    {
        $this->twoFactor->forgetPending($request->session());

        Auth::guard('platform')->login($admin);
        $request->session()->regenerate();

        $admin->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
        $this->audit->record(PlatformAuditLog::LOGIN, $admin, ['metadata' => ['method' => $method]]);

        return redirect()->intended(route('platform.tenants.index'));
    }

    /** @throws ValidationException */
    private function failed(PlatformAdmin $admin, string $field, string $step = 'challenge'): never
    {
        $this->audit->record(PlatformAuditLog::TWO_FACTOR_FAILED, $admin, ['metadata' => ['step' => $step, 'field' => $field]]);

        throw ValidationException::withMessages([$field => 'That code is not valid.']);
    }

    private function backToLogin()
    {
        return redirect()->route('platform.login')->withErrors(['email' => 'Your sign-in expired. Please sign in again.']);
    }
}
