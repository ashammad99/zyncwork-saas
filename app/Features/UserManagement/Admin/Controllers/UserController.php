<?php

namespace App\Features\UserManagement\Admin\Controllers;

use App\Core\BaseController;
use App\Features\Auth\Models\User;
use App\Features\Billing\Exceptions\SeatLimitReached;
use App\Features\Departments\Admin\Models\Department;
use App\Features\Onboarding\Models\UserInvitation;
use App\Features\UserManagement\Admin\Requests\UpdateUserRequest;
use App\Features\UserManagement\Admin\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class UserController extends BaseController
{
    public function __construct(
        protected UserService $userService
    ) {}

    /**
     * Display a listing of the users.
     */
    public function index(Request $request)
    {
        $users = $this->userService->getPaginatedUsers($request->only(['search']));

        return Inertia::render('(portals)/admin/users/page', [
            'users' => $users,
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            // Open invitations; expired ones stay listed so they can be resent.
            'invitations' => UserInvitation::with('department:id,name')
                ->whereNull('accepted_at')
                ->whereNull('revoked_at')
                ->latest()
                ->get(['id', 'email', 'role', 'department_id', 'expires_at', 'created_at'])
                ->map(fn (UserInvitation $invitation) => $invitation->only(['id', 'email', 'role', 'expires_at', 'created_at']) + [
                    'department' => $invitation->department?->name,
                    'expired' => $invitation->expires_at->isPast(),
                ]),

            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Update the specified user details.
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        if ($user->id === Auth::id()) {
            return redirect()->back()->with('error', 'You cannot modify your own core privileges from this page.');
        }

        try {
            $this->userService->updateUser($user, $request->validated());
        } catch (SeatLimitReached $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'User updated successfully.');
    }
}
