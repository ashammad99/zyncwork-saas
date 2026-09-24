<?php

namespace App\Features\ActivityLog\Admin\Controllers;

use App\Core\BaseController;
use App\Features\ActivityLog\Models\ActivityLog;

class ActivityLogController extends BaseController
{
    /**
     * Admin sees ALL users' activity logs.
     */
    public function index()
    {
        $logs = ActivityLog::with('user')
            ->latest()
            ->paginate(20);

        return inertia('(portals)/admin/activity-logs/page', [
            'logs' => $logs,
        ]);
    }
}
