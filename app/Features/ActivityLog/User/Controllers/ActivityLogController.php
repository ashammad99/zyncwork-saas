<?php

namespace App\Features\ActivityLog\User\Controllers;

use App\Core\BaseController;
use App\Features\ActivityLog\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;

class ActivityLogController extends BaseController
{
    /**
     * User sees only their own activity logs.
     */
    public function index()
    {
        $logs = ActivityLog::where('user_id', Auth::id())
            ->latest()
            ->paginate(20);

        return inertia('(portals)/employee/activity-logs/page', [
            'logs' => $logs,
        ]);
    }
}
