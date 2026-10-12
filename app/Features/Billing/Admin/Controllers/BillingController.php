<?php

namespace App\Features\Billing\Admin\Controllers;

use App\Core\BaseController;
use App\Features\Billing\Admin\Requests\UpdateBillingEmailRequest;
use App\Features\Billing\Admin\Services\BillingEmailService;
use App\Features\Billing\Services\SeatGuard;
use App\Features\TenantExport\Models\TenantExport;
use App\Features\TenantExport\Services\TenantExportService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The tenant admin's billing page: plan, status, seats, billing email, and the
 * full data export.
 * Always the signed-in admin's own tenant; nothing is taken from the request.
 * No payment provider is connected yet, so there is nothing to buy here.
 */
class BillingController extends BaseController
{
    public function __construct(
        protected SeatGuard $seats,
        protected BillingEmailService $billingEmail,
        protected TenantExportService $exports,
    ) {}

    public function index(Request $request)
    {
        $tenant = $request->user()->tenant;
        $subscription = $tenant->subscription()->with('plan')->first();
        $usage = $this->seats->usage($tenant->id);
        $seats = $subscription?->seats ?? 0;

        return Inertia::render('(portals)/admin/billing/page', [
            'billing' => [
                'plan' => $subscription?->plan?->name,
                'status' => $subscription?->status,
                'trial_ends_at' => $subscription?->trial_ends_at,
                'trial_expired' => (bool) $subscription?->trialExpired(),
                'can_add_seats' => (bool) $subscription?->canAddSeats(),
                'seats' => $seats,
                'active_users' => $usage['active'],
                'pending_invitations' => $usage['pending'],
                'seats_used' => $usage['used'],
                'seats_full' => $usage['used'] >= $seats,
                'billing_email' => $tenant->billing_email,
            ],
            'exports' => [
                'can_request' => $this->exports->canRequest(),
                // The tenant's latest exports; only the requester gets a download link.
                'recent' => TenantExport::with('requester:id,name')->latest('id')->limit(5)->get()
                    ->map(fn (TenantExport $export) => [
                        'id' => $export->id,
                        'status' => $export->status,
                        'requested_by' => $export->requester?->name,
                        'created_at' => $export->created_at,
                        'expires_at' => $export->expires_at,
                        'size_bytes' => $export->size_bytes,
                        'download_url' => (int) $export->requested_by === (int) $request->user()->id && $export->isDownloadable()
                            ? $this->exports->downloadUrl($export)
                            : null,
                    ]),
            ],
        ]);
    }

    public function updateEmail(UpdateBillingEmailRequest $request)
    {
        $changed = $this->billingEmail->update($request->user()->tenant, $request->validated('billing_email'), $request->user());

        return redirect()->back()->with('success', $changed ? 'Billing email updated.' : 'The billing email is unchanged.');
    }
}
