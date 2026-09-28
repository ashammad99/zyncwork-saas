<?php

namespace App\Features\Platform\Controllers;

use App\Core\BaseController;
use App\Features\Platform\Middleware\RequirePlatformTwoFactor;
use App\Features\Platform\Models\PlatformAdmin;
use App\Features\Platform\Models\PlatformAuditLog;
use App\Features\Platform\Requests\TwoFactorChallengeRequest;
use App\Features\Platform\Requests\TwoFactorSetupRequest;
use App\Features\Platform\Services\PlatformAuditLogger;
use App\Features\Platform\Services\PlatformTwoFactorService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
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

        // Per account, so guessing from many IPs still hits the same limit.
        if (RateLimiter::tooManyAttempts($this->attemptsKey($admin), config('platform.two_factor.max_attempts'))) {
            $this->twoFactor->forgetPending($request->session());
            $minutes = (int) ceil(RateLimiter::availableIn($this->attemptsKey($admin)) / 60);

            return redirect()->route('platform.login')
                ->withErrors(['email' => "Too many incorrect codes. Try again in {$minutes} ".Str::plural('minute', $minutes).'.']);
        }

        if ($request->filled('recovery_code')) {
            $remaining = $this->twoFactor->useRecoveryCode($admin, $request->input('recovery_code'));

            if ($remaining === null) {
                return $this->rejected($request, $admin, 'recovery_code');
            }

            $this->audit->record(PlatformAuditLog::TWO_FACTOR_RECOVERY_USED, $admin, ['metadata' => ['remaining' => $remaining]]);

            return $this->signIn($request, $admin, 'recovery_code');
        }

        if (! $this->twoFactor->verify($admin, $request->input('code'))) {
            return $this->rejected($request, $admin, 'code');
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
        $request->session()->put(RequirePlatformTwoFactor::PASSWORD_FINGERPRINT, $admin->passwordFingerprint());
        RateLimiter::clear($this->attemptsKey($admin));

        $admin->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
        $this->audit->record(PlatformAuditLog::LOGIN, $admin, ['metadata' => ['method' => $method]]);

        return redirect()->intended(route('platform.tenants.index'));
    }

    /**
     * A wrong code at sign-in counts against the account and ends the
     * pending sign-in: the next guess needs the password again.
     */
    private function rejected(Request $request, PlatformAdmin $admin, string $field)
    {
        RateLimiter::hit($this->attemptsKey($admin), config('platform.two_factor.lockout_minutes') * 60);
        $this->audit->record(PlatformAuditLog::TWO_FACTOR_FAILED, $admin, ['metadata' => ['step' => 'challenge', 'field' => $field]]);
        $this->twoFactor->forgetPending($request->session());

        return redirect()->route('platform.login')->withErrors(['email' => 'That code is not valid. Please sign in again.']);
    }

    private function attemptsKey(PlatformAdmin $admin): string
    {
        return "platform-two-factor:{$admin->id}";
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
