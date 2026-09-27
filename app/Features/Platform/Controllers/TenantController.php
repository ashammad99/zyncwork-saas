<?php

namespace App\Features\Platform\Controllers;

use App\Core\BaseController;
use App\Core\Tenancy\TenantContext;
use App\Features\Auth\Models\User;
use App\Features\Platform\Models\PlatformAdmin;
use App\Features\Platform\Models\PlatformAuditLog;
use App\Features\Platform\Models\PlatformImpersonation;
use App\Features\Platform\Requests\SuspendTenantRequest;
use App\Features\Platform\Services\PlatformAuditLogger;
use App\Features\Platform\Services\TenantSuspensionService;
use App\Features\Tenancy\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TenantController extends BaseController
{
    public function __construct(
        protected PlatformAuditLogger $audit,
        protected TenantSuspensionService $suspensions,
    ) {}

    public function index()
    {
        // Counts only: an explicit, read-only opt-out of tenant filtering.
        $userCounts = User::withoutTenancy()
            ->selectRaw('tenant_id, count(*) as aggregate')
            ->groupBy('tenant_id')
            ->pluck('aggregate', 'tenant_id');

        $tenants = Tenant::orderBy('name')->get()->map(fn (Tenant $tenant) => [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'users_count' => (int) ($userCounts[$tenant->id] ?? 0),
            'suspended_at' => $tenant->suspended_at,
            'created_at' => $tenant->created_at,
        ]);

        return Inertia::render('(platform)/tenants/Index', ['tenants' => $tenants]);
    }

    public function show(Request $request, Tenant $tenant, TenantContext $context)
    {
        $this->audit->record(PlatformAuditLog::TENANT_VIEWED, $request->user('platform'), ['tenant_id' => $tenant->id]);

        // Only what support needs to pick a user to act as.
        $users = $context->run($tenant->id, fn () => User::orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'is_active']));

        $impersonations = PlatformImpersonation::with('platformAdmin:id,name')
            ->where('tenant_id', $tenant->id)
            ->latest()
            ->limit(10)
            ->get(['id', 'platform_admin_id', 'target_user_id', 'reason', 'consumed_at', 'ended_at', 'end_reason', 'created_at']);

        return Inertia::render('(platform)/tenants/Show', [
            'tenant' => $tenant->only(['id', 'name', 'suspended_at', 'suspended_reason', 'created_at']),
            'users' => $users,
            'impersonations' => $impersonations,
            'can' => [
                'suspend' => $request->user('platform')->hasCapability(PlatformAdmin::SUSPEND_TENANTS),
                'impersonate' => $request->user('platform')->hasCapability(PlatformAdmin::IMPERSONATE),
            ],
        ]);
    }

    public function suspend(SuspendTenantRequest $request, Tenant $tenant)
    {
        if ($tenant->isSuspended()) {
            return redirect()->back()->with('error', 'This tenant is already suspended.');
        }

        $this->suspensions->suspend($tenant, $request->user('platform'), $request->validated()['reason']);

        return redirect()->back()->with('success', "{$tenant->name} has been suspended.");
    }

    public function unsuspend(Request $request, Tenant $tenant)
    {
        if (! $tenant->isSuspended()) {
            return redirect()->back()->with('error', 'This tenant is not suspended.');
        }

        $this->suspensions->unsuspend($tenant, $request->user('platform'));

        return redirect()->back()->with('success', "{$tenant->name} is active again.");
    }
}
