<?php

namespace App\Features\Onboarding\Controllers;

use App\Core\BaseController;
use App\Core\Tenancy\TenantContext;
use App\Features\ActivityLog\Events\ActivityLogged;
use App\Features\Auth\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Email verification for tenant users. Until it is done, EnsureEmailIsVerified
 * lets the user reach only these routes and logout.
 */
class EmailVerificationController extends BaseController
{
    public function __construct(
        protected TenantContext $tenantContext
    ) {}

    public function notice(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        return view('pages.auth.verify-email.page', [
            'email' => $request->user()->email,
            'expiresInMinutes' => (int) config('auth.verification.expire', 60),
        ]);
    }

    /**
     * The signed link is the proof, so it works whoever is signed in, and
     * when nobody is (a link opened on a phone). It never signs anyone in:
     * that would turn every verification email into a login link.
     */
    public function verify(Request $request, string $id, string $hash)
    {
        $user = User::withoutTenancy()->find($id);

        if (! $user || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            abort(403, 'This verification link is not valid.');
        }

        if (! $user->hasVerifiedEmail()) {
            $this->tenantContext->run($user->tenant_id, function () use ($user) {
                $user->markEmailAsVerified();
                event(new Verified($user));
                event(new ActivityLogged($user, 'email_verified', "Verified email address {$user->email}.", 'security'));
            });
        }

        if ((int) Auth::guard('web')->id() === (int) $user->id) {
            return redirect()->route('dashboard')->with('success', 'Your email address is verified.');
        }

        return redirect()->route('login')->with('status', 'Your email address is verified. Please sign in.');
    }

    public function send(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'A new verification link is on its way.');
    }
}
