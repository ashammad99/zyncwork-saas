<?php

namespace App\Features\Onboarding\Controllers;

use App\Core\BaseController;
use App\Features\Onboarding\Requests\AcceptInvitationRequest;
use App\Features\Onboarding\Services\InvitationService;
use App\Features\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Auth;

/**
 * Accepting an invitation (guest). Unknown, used, revoked and expired links
 * all get the same page, so a link never reveals which it was.
 */
class InvitationController extends BaseController
{
    public function __construct(
        protected InvitationService $invitations
    ) {}

    public function show(string $token)
    {
        $invitation = $this->invitations->findOpen($token);

        return view('pages.auth.invitation.page', [
            'invitation' => $invitation,
            'token' => $token,
            'companyName' => $invitation ? Tenant::find($invitation->tenant_id)?->name : null,
            'role' => $invitation?->role,
        ]);
    }

    public function accept(AcceptInvitationRequest $request, string $token)
    {
        $user = $this->invitations->accept($token, $request->validated());

        if (! $user) {
            return redirect()->route('invitations.show', $token);
        }

        // Whoever was signed in in this browser before is replaced by the new account.
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Welcome! Your account is ready.');
    }
}
