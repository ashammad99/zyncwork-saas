<?php

namespace App\Features\TimeLog\Services;

use App\Core\BaseService;
use App\Core\Support\CsvCell;
use App\Features\TimeLog\Models\TimeLog;
use Barryvdh\DomPDF\PDF;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TimeLogExportService extends BaseService
{
    /**
     * Stream a CSV of time logs matching the filters.
     * $departmentScope limits to one department (manager use); null = all (admin).
     */
    public function streamCsv(array $filters = [], ?int $departmentScope = null): StreamedResponse
    {
        $query = $this->buildQuery($filters, $departmentScope);

        $filename = 'time-logs_'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');

            // UTF-8 BOM so Excel opens Arabic/accented names correctly
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Employee', 'Email', 'Department', 'Project', 'Task',
                'Date', 'Start', 'End', 'Duration (min)', 'Duration (h)',
                'Type', 'Notes', 'Approved', 'Approved By',
            ]);

            $query->chunk(500, function ($logs) use ($out) {
                foreach ($logs as $log) {
                    // Names, titles and notes are typed by users: never let them run as formulas.
                    fputcsv($out, CsvCell::row([
                        $log->user?->name,
                        $log->user?->email,
                        $log->task?->project?->department?->name,
                        $log->task?->project?->name,
                        $log->task?->title,
                        $log->started_at?->format('Y-m-d'),
                        $log->started_at?->format('H:i'),
                        $log->ended_at?->format('H:i'),
                        $log->duration_minutes,
                        $log->duration_minutes !== null ? round($log->duration_minutes / 60, 2) : null,
                        $log->entry_type,
                        $log->notes,
                        $log->approved_by ? 'yes' : 'no',
                        $log->approvedBy?->name,
                    ]));
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function buildQuery(array $filters, ?int $departmentScope): Builder
    {
        return TimeLog::query()
            ->whereNotNull('ended_at') // never export running timers
            ->with([
                'user:id,name,email,department_id',
                'task:id,title,project_id',
                'task.project:id,name,department_id',
                'task.project.department:id,name',
                'approvedBy:id,name',
            ])
            ->when($departmentScope, fn ($q, $deptId) => $q->whereHas('task.project', fn ($p) => $p->where('department_id', $deptId))
            )
            ->when($filters['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($filters['department_id'] ?? null, fn ($q, $v) => $q->whereHas('task.project', fn ($p) => $p->where('department_id', $v))
            )
            ->when($filters['entry_type'] ?? null, fn ($q, $v) => $q->where('entry_type', $v))
            ->when($filters['approval'] ?? null, fn ($q, $v) => match ($v) {
                'approved' => $q->whereNotNull('approved_by'),
                'pending' => $q->whereNull('approved_by'),
                default => $q,
            })
            ->when($filters['date_from'] ?? null, fn ($q, $v) => $q->whereDate('started_at', '>=', $v))
            ->when($filters['date_to'] ?? null, fn ($q, $v) => $q->whereDate('started_at', '<=', $v))
            ->orderBy('started_at');
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
        $filters = $request->only(['user_id', 'entry_type', 'approval', 'date_from', 'date_to']);

        return $this->exportService->makePdf($filters, $request->user()->department_id)
            ->download('time-logs_'.now()->format('Y-m-d').'.pdf');
    }

    /**
     * Build the PDF. Enforces a date range (PRD: no unbounded exports).
     */
    public function makePdf(array $filters, ?int $departmentScope = null): PDF
    {
        abort_unless(
            ($filters['date_from'] ?? null) && ($filters['date_to'] ?? null),
            422,
            'A date range (from and to) is required for PDF export.'
        );

        $logs = $this->buildQuery($filters, $departmentScope)->get();

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('exports.time-logs-pdf', [
            'logs' => $logs,
            'dateFrom' => $filters['date_from'],
            'dateTo' => $filters['date_to'],
            'departmentName' => null,
            'generatedBy' => auth()->user()->name,
        ])->setPaper('a4', 'landscape');
    }
}
