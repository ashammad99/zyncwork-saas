<?php

namespace App\Features\UserManagement\Admin\Services;

use App\Core\BaseService;
use App\Features\ActivityLog\Events\ActivityLogged;
use App\Features\Auth\Models\User;
use App\Features\Billing\Exceptions\SeatLimitReached;
use App\Features\Billing\Services\BillingNotifier;
use App\Features\Billing\Services\SeatGuard;
use App\Features\Onboarding\Services\InvitationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UserService extends BaseService
{
    public function __construct(
        protected InvitationService $invitations,
        protected SeatGuard $seats,
        protected BillingNotifier $billingNotifier,
    ) {}

    /**
     * Get paginated users.
     */
    public function getPaginatedUsers(array $filters = [])
    {
        $query = User::with('department');

        if (! empty($filters['search'])) {
            $query->where('name', 'like', '%'.$filters['search'].'%')
                ->orWhere('email', 'like', '%'.$filters['search'].'%');
        }

        return $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();
    }

    /**
     * Update user details and privileges.
     */
    /**
     * @throws SeatLimitReached when reactivating a user needs a seat the tenant doesn't have
     */
    public function updateUser(User $user, array $data): User
    {
        $wasActive = $user->is_active;
        $wasAdmin = $user->role === 'admin';

        $apply = fn () => $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'role' => $data['role'],
            'is_active' => $data['is_active'],
            'daily_capacity_hours' => $data['daily_capacity_hours'],
            'department_id' => $data['department_id'] ?? null,

        ]);

        // Only reactivation takes a seat; deactivating and other edits never check.
        if (! $wasActive && $data['is_active']) {
            try {
                DB::transaction(function () use ($user, $apply) {
                    $this->seats->ensureCanAdd($user->tenant_id);
                    $apply();
                });
            } catch (SeatLimitReached $e) {
                // After the rollback, so the notice record isn't rolled back with it.
                if ($e->reason === SeatLimitReached::SEATS_FULL) {
                    $this->billingNotifier->seatLimitReached($user->tenant_id);
                }

                throw $e;
            }
        } else {
            $apply();
        }

        // Invitations carry the inviter's authority: an admin who is demoted or
        // suspended can no longer bring anyone in (findOpen also refuses them).
        if ($wasAdmin && ($user->role !== 'admin' || ! $user->is_active)) {
            $this->invitations->revokeAllFrom($user, Auth::user());
        }

        if ($wasActive && ! $user->is_active) {
            event(new ActivityLogged(Auth::user(), 'user_suspended', "Suspended user account: {$user->email}", 'security'));
        } elseif (! $wasActive && $user->is_active) {
            event(new ActivityLogged(Auth::user(), 'user_activated', "Activated user account: {$user->email}", 'system'));
        } else {
            event(new ActivityLogged(Auth::user(), 'user_updated', "Updated profile/privileges for user: {$user->email}", 'system'));
        }

        return $user;
    }
}
