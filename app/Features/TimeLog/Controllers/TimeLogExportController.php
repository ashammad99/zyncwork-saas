<?php

namespace App\Features\TimeLog\Controllers;

use App\Core\BaseController;
use App\Features\TimeLog\Services\TimeLogExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TimeLogExportController extends BaseController
{
    public function __construct(
        protected TimeLogExportService $exportService,
    ) {}

    /**
     * Admin: export any time logs, all filters available.
     */
    public function admin(Request $request): StreamedResponse
    {
        $filters = $request->only([
            'user_id', 'project_id', 'department_id',
            'entry_type', 'approval', 'date_from', 'date_to',
        ]);

        return $this->exportService->streamCsv($filters);
    }

    /**
     * Manager: export scoped to own department (server-side clamp),
     * mirroring the manager approval page's filter set.
     */
    public function manager(Request $request): StreamedResponse
    {
        // A null scope means "all departments" in the export service, so never pass one.
        abort_unless($request->user()->department_id, 403, 'You are not assigned to a department.');

        $filters = $request->only([
            'user_id', 'entry_type', 'approval', 'date_from', 'date_to',
        ]);

        return $this->exportService->streamCsv(
            $filters,
            $request->user()->department_id,   // hard clamp — ignores any department_id param
        );
    }

    public function adminPdf(Request $request)
    {
        $filters = $request->only([
            'user_id', 'project_id', 'department_id',
            'entry_type', 'approval', 'date_from', 'date_to',
        ]);

        return $this->exportService->makePdf($filters)
            ->download('time-logs_'.now()->format('Y-m-d').'.pdf');
    }

    public function managerPdf(Request $request)
    {
        abort_unless($request->user()->department_id, 403, 'You are not assigned to a department.');

        $filters = $request->only(['user_id', 'entry_type', 'approval', 'date_from', 'date_to']);

        return $this->exportService->makePdf($filters, $request->user()->department_id)
            ->download('time-logs_'.now()->format('Y-m-d').'.pdf');
    }
}
