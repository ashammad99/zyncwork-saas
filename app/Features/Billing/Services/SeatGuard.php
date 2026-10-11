<?php

namespace App\Features\Billing\Services;

use App\Core\BaseService;
use App\Core\Tenancy\TenantContext;
use App\Features\Auth\Models\User;
use App\Features\Billing\Exceptions\SeatLimitReached;
use App\Features\Billing\Models\TenantSubscription;
use App\Features\Onboarding\Models\UserInvitation;
use Illuminate\Support\Str;

/**
 * Checks a tenant can take on one more user. Called wherever a seat is about
 * to be used: sending an invite, accepting one, and reactivating a user.
 *
 * Seats used = active users + open invitations (an invite holds its seat
 * until it is accepted, revoked or expires). Deactivating never checks.
 */
class SeatGuard extends BaseService
{
    public function __construct(
        protected TenantContext $tenantContext,
    ) {}

    /**
     * Call inside a DB transaction: the subscription row stays locked until it
     * commits, so two requests can't both take the last seat. Lock it before
     * any other row in that transaction, so every caller locks in one order.
     *
     * @param  int|null  $excludingInvitationId  the invite being accepted (its seat becomes the user's)
     *
     * @throws SeatLimitReached
     */
    public function ensureCanAdd(int $tenantId, ?int $excludingInvitationId = null): void
    {
        $subscription = TenantSubscription::where('tenant_id', $tenantId)->lockForUpdate()->first();

        // Every tenant gets a row when it is created; without one, fail closed.
        if (! $subscription) {
            throw new SeatLimitReached('Your organisation has no active plan. Please contact support.');
        }

        if (! $subscription->canAddSeats()) {
            throw new SeatLimitReached($subscription->trialExpired()
                ? 'Your trial has ended, so no new users can be added. Choose a plan to continue adding users. See Billing for details.'
                : 'Your plan is not active, so no new users can be added. Please update your billing details. See Billing for details.');
        }

        if ($this->usage($tenantId, $excludingInvitationId)['used'] + 1 > $subscription->seats) {
            throw new SeatLimitReached("Your plan includes {$subscription->seats} ".Str::plural('seat', $subscription->seats)
                .', and all of them are in use (active users and pending invitations). Deactivate a user, revoke an invitation, or add seats. See Billing for details.');
        }
    }

    /**
     * Seats in use, counted the one way enforcement counts them, so the
     * billing page and the limit can never disagree.
     *
     * @return array{active: int, pending: int, used: int}
     */
    public function usage(int $tenantId, ?int $excludingInvitationId = null): array
    {
        return $this->tenantContext->run($tenantId, function () use ($excludingInvitationId) {
            $active = User::where('is_active', true)->count();
            $pending = UserInvitation::open()
                ->when($excludingInvitationId, fn ($query) => $query->whereKeyNot($excludingInvitationId))
                ->count();

            return ['active' => $active, 'pending' => $pending, 'used' => $active + $pending];
        });
    }
}
