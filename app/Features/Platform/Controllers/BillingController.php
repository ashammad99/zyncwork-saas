<?php

namespace App\Features\Platform\Controllers;

use App\Core\BaseController;
use App\Features\Auth\Models\User;
use App\Features\Onboarding\Models\UserInvitation;
use App\Features\Platform\Models\PlatformAuditLog;
use App\Features\Platform\Services\PlatformAuditLogger;
use App\Features\Tenancy\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Billing status per tenant: plan, status and seats, from tenant_subscriptions
 * (no billing provider is connected yet). Seats used are counted the way
 * SeatGuard counts them: active users plus open invitations.
 * Viewing is recorded in the platform log only, not in the tenant's log.
 */
class BillingController extends BaseController
{
    public function __construct(
        protected PlatformAuditLogger $audit
    ) {}

    public function index(Request $request)
    {
        $this->audit->record(PlatformAuditLog::BILLING_VIEWED, $request->user('platform'));

        // Across every tenant, on purpose: the platform has no tenant context.
        $activeUsers = User::withoutTenancy()->where('is_active', true)
            ->selectRaw('tenant_id, count(*) as total')->groupBy('tenant_id')->pluck('total', 'tenant_id');
        $openInvites = UserInvitation::withoutTenancy()->open()
            ->selectRaw('tenant_id, count(*) as total')->groupBy('tenant_id')->pluck('total', 'tenant_id');

        $tenants = Tenant::with('subscription.plan')->orderBy('name')->get()->map(function (Tenant $tenant) use ($activeUsers, $openInvites) {
            $subscription = $tenant->subscription;

            return [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'billing_email' => $tenant->billing_email,
                'plan' => $subscription?->plan?->name,
                'status' => $subscription?->status,
                'trial_expired' => (bool) $subscription?->trialExpired(),
                'seats_used' => (int) ($activeUsers[$tenant->id] ?? 0) + (int) ($openInvites[$tenant->id] ?? 0),
                'seats' => $subscription?->seats,
                'trial_ends_at' => $subscription?->trial_ends_at,
                'current_period_ends_at' => $subscription?->current_period_ends_at,
                'grace_ends_at' => $subscription?->grace_ends_at,
                'suspended_at' => $tenant->suspended_at,
            ];
        });

        return Inertia::render('(platform)/billing/Index', ['tenants' => $tenants]);
    }
}
