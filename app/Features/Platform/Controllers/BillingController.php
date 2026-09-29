<?php

namespace App\Features\Platform\Controllers;

use App\Core\BaseController;
use App\Features\Platform\Models\PlatformAuditLog;
use App\Features\Platform\Services\PlatformAuditLogger;
use App\Features\Tenancy\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Billing status per tenant. Plans and subscriptions arrive with Phase 1D
 * (Cashier + Stripe); until then every tenant shows as having no plan.
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

        $tenants = Tenant::orderBy('name')->get()->map(fn (Tenant $tenant) => [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'plan' => null,
            'suspended_at' => $tenant->suspended_at,
        ]);

        return Inertia::render('(platform)/billing/Index', ['tenants' => $tenants]);
    }
}
