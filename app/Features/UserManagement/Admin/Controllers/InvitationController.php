<?php

namespace App\Features\UserManagement\Admin\Controllers;

use App\Core\BaseController;
use App\Features\Billing\Exceptions\SeatLimitReached;
use App\Features\Onboarding\Models\UserInvitation;
use App\Features\Onboarding\Services\InvitationService;
use App\Features\UserManagement\Admin\Requests\StoreInvitationRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Tenant admins invite, resend and revoke; accepting is Onboarding\Controllers\InvitationController. */
class InvitationController extends BaseController
{
    public function __construct(
        protected InvitationService $invitations
    ) {}

    public function store(StoreInvitationRequest $request)
    {
        try {
            $invitation = $this->invitations->invite($request->user(), $request->validated());
        } catch (SeatLimitReached $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', "Invitation sent to {$invitation->email}.");
    }

    public function resend(Request $request, UserInvitation $invitation)
    {
        $this->authorize('update', $invitation);

        try {
            $this->invitations->resend($invitation, $request->user());
        } catch (ValidationException $e) {
            return redirect()->back()->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->back()->with('success', "Invitation sent again to {$invitation->email}.");
    }

    public function destroy(Request $request, UserInvitation $invitation)
    {
        $this->authorize('delete', $invitation);

        try {
            $this->invitations->revoke($invitation, $request->user());
        } catch (ValidationException $e) {
            return redirect()->back()->with('error', collect($e->errors())->flatten()->first());
        }

        return redirect()->back()->with('success', "Invitation to {$invitation->email} revoked.");
    }
}
