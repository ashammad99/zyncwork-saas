<?php

namespace App\Features\SystemHealth\Admin\Controllers;

use App\Core\BaseController;
use Inertia\Inertia;
use App\Features\SystemHealth\Admin\Services\HealthStatusService;

class HealthController extends BaseController
{
    public function __construct(
        protected HealthStatusService $healthService
    ) {}

    /**
     * Display the system health page.
     */
    public function index()
    {
        return Inertia::render('(portals)/admin/system/health/page', [
            'metrics' => $this->healthService->getSystemMetrics()
        ]);
    }
}
