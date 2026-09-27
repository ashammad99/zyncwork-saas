<?php

namespace App\Features\Platform\Controllers;

use App\Core\BaseController;
use App\Features\Platform\Models\PlatformAuditLog;
use App\Features\Platform\Services\HealthStatusService;
use App\Features\Platform\Services\PlatformAuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HealthController extends BaseController
{
    public function __construct(
        protected HealthStatusService $healthService,
        protected PlatformAuditLogger $audit,
    ) {}

    /**
     * Display the system health page.
     */
    public function index(Request $request)
    {
        $this->audit->record(PlatformAuditLog::HEALTH_VIEWED, $request->user('platform'));

        return Inertia::render('(platform)/health/page', [
            'metrics' => $this->healthService->getSystemMetrics(),
        ]);
    }
}
