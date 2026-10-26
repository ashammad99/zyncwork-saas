<?php

namespace App\Features\Landing\Controllers;

use App\Core\BaseController;
use App\Features\Billing\Models\Plan;
use App\Features\Landing\Services\LegalDocumentService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The public site: no sign-in and no tenant context. Only platform-wide
 * data (plans, config) is read here, never a tenant-owned model.
 *
 * These are Blade pages, so a link followed from inside the Inertia app is
 * turned into a full page load.
 */
class LandingController extends BaseController
{
    public function __construct(
        protected LegalDocumentService $legalDocuments,
    ) {}

    public function index(Request $request)
    {
        if ($request->header('X-Inertia')) {
            return Inertia::location($request->url());
        }

        return view('pages.site.home', [
            'trialDays' => (int) config('billing.trial_days'),
        ]);
    }

    /** The trial comes from the same plan and config that signup uses (Tenant), so the two can't disagree. */
    public function pricing(Request $request)
    {
        if ($request->header('X-Inertia')) {
            return Inertia::location($request->url());
        }

        return view('pages.site.pricing', [
            'trialDays' => (int) config('billing.trial_days'),
            'trialSeats' => Plan::trialPlan()->included_seats,
            'plans' => Plan::where('is_trial', false)
                ->where('is_active', true)
                ->whereNotNull('price_amount')
                ->orderBy('sort')
                ->get(),
        ]);
    }

    /** $document is set per route (terms, privacy, refunds). */
    public function legal(Request $request, string $document)
    {
        if ($request->header('X-Inertia')) {
            return Inertia::location($request->url());
        }

        return view('pages.site.legal', [
            'document' => $document,
            'html' => $this->legalDocuments->render($document),
            'isPlaceholder' => $this->legalDocuments->isPlaceholder($document),
        ]);
    }

    public function contact(Request $request)
    {
        if ($request->header('X-Inertia')) {
            return Inertia::location($request->url());
        }

        return view('pages.site.contact');
    }
}
