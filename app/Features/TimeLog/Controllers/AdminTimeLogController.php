<?php

namespace App\Features\TimeLog\Controllers;

use App\Core\BaseController;
use App\Features\Auth\Models\User;
use App\Features\TimeLog\Requests\BulkApproveTimeLogsRequest;
use App\Features\TimeLog\Services\TimeLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminTimeLogController extends BaseController
{
    public function __construct(
        protected TimeLogService $timeLogService,
    ) {}

    /** All time logs across the org, filterable */
    public function index(Request $request): Response
    {
        $filters = $request->only([
            'user_id', 'project_id', 'department_id',
            'entry_type', 'approval', 'date_from', 'date_to',
        ]);

        return Inertia::render('(portals)/admin/timesheets/Index', [
            'logs' => $this->timeLogService->getAllPaginated($filters),
            'filters' => $filters,
            'options' => $this->timeLogService->getAdminFilterOptions(),
        ]);
    }

    /** Approval queue — unapproved, finished entries grouped by user */
    public function pending(): Response
    {
        return Inertia::render('(portals)/admin/timesheets/Pending', [
            'groups' => $this->timeLogService->getPendingGroupedByUser(),
        ]);
    }

    /** Aggregate reports: per user / project / department */
    public function reports(Request $request): Response
    {
        $period = $request->input('period', 'week'); // week | month

        return Inertia::render('(portals)/admin/timesheets/Reports', [
            'period' => $period,
            'reports' => $this->timeLogService->getReports($period),
        ]);
    }

    /** Bulk approve selected entries */
    public function bulkApprove(BulkApproveTimeLogsRequest $request): RedirectResponse
    {
        $count = $this->timeLogService->bulkApprove(
            $request->validated('time_log_ids'),
            $request->user(),
        );

        return back()->with('success', "{$count} time entries approved.");
    }
}
