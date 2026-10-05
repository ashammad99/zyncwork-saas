<?php

namespace App\Features\TimeLog\Services;

use App\Core\BaseService;
use App\Features\Auth\Models\User;
use App\Features\Departments\Admin\Models\Department;
use App\Features\Projects\Admin\Models\Project;
use App\Features\TaskAdmin\Models\Task;
use App\Features\TimeLog\Models\TimeLog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

class TimeLogService extends BaseService
{
    /**
     * Start a timer for a user on a given task.
     * Enforces "one active timer per user" — auto-stops any running timer first.
     */
    public function start(User $user, Task $task): TimeLog
    {
        return DB::transaction(function () use ($user, $task) {
            $this->stopActiveIfAny($user);

            return TimeLog::create([
                'task_id' => $task->id,
                'user_id' => $user->id,
                'started_at' => now(),
                'ended_at' => null,
                'entry_type' => TimeLog::TYPE_TIMER,
            ]);
        });
    }

    /**
     * Stop the user's currently running timer (if any).
     * Returns the stopped log, or null if no timer was running.
     */
    public function stop(User $user): ?TimeLog
    {
        return DB::transaction(function () use ($user) {
            $active = TimeLog::running()
                ->forUser($user->id)
                ->lockForUpdate()
                ->first();

            if (! $active) {
                return null;
            }

            return $this->finalize($active);
        });
    }

    /**
     * Stop a specific timer by its TimeLog instance.
     * Useful for admin stopping a stale/abandoned timer on another user.
     */
    public function stopTimerById(TimeLog $log): TimeLog
    {
        if (! $log->isRunning()) {
            return $log;
        }

        return DB::transaction(fn () => $this->finalize($log));
    }

    /**
     * Get the user's currently running timer, eager-loaded with task + project.
     * Returns null if no timer is active.
     */
    public function active(User $user): ?TimeLog
    {
        return TimeLog::running()
            ->forUser($user->id)
            ->with([
                'task:id,title,project_id',
                'task.project:id,name,department_id',
            ])
            ->first();
    }

    /**
     * Internal: auto-stop any active timer for the user.
     */
    protected function stopActiveIfAny(User $user): void
    {
        $active = TimeLog::running()
            ->forUser($user->id)
            ->lockForUpdate()
            ->first();

        if ($active) {
            $this->finalize($active);
        }
    }

    /**
     * Internal: set ended_at + compute duration server-side.
     * Duration is NEVER trusted from the client.
     */
    protected function finalize(TimeLog $timer): TimeLog
    {
        $endedAt = Carbon::now();
        $duration = (int) $timer->started_at->diffInMinutes($endedAt);

        $timer->update([
            'ended_at' => $endedAt,
            'duration_minutes' => $duration,
        ]);

        return $timer->fresh();
    }

    public function getWeekLogs(User $user, Carbon $weekStart): Collection
    {
        $weekEnd = $weekStart->copy()->endOfWeek();

        return TimeLog::query()
            ->forUser($user->id)
            ->between($weekStart, $weekEnd)
            ->with([
                'task:id,title,project_id',
                'task.project:id,name',
                'approver:id,name',
            ])
            ->orderBy('started_at', 'desc')
            ->get();
    }

    /**
     * Create a manual time entry.
     * Combines date + HH:MM time strings into full datetimes.
     * Duration is computed server-side.
     */
    public function createManual(User $user, Task $task, array $data): TimeLog
    {
        [$startedAt, $endedAt, $duration] = $this->resolveTimes($data);

        return TimeLog::create([
            'task_id' => $task->id,
            'user_id' => $user->id,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'duration_minutes' => $duration,
            'entry_type' => TimeLog::TYPE_MANUAL,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    /**
     * Update an existing time entry.
     * Approved or running logs must be blocked at the Policy layer before reaching this.
     */
    public function update(TimeLog $log, array $data): TimeLog
    {
        [$startedAt, $endedAt, $duration] = $this->resolveTimes($data);

        $log->update([
            'task_id' => $data['task_id'] ?? $log->task_id,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'duration_minutes' => $duration,
            'notes' => $data['notes'] ?? null,
        ]);

        return $log->fresh();
    }

    /**
     * Delete a time entry.
     */
    public function delete(TimeLog $log): void
    {
        $log->delete();
    }

    /**
     * Approve a time entry (manager/admin action).
     */
    public function approve(TimeLog $log, User $approver): TimeLog
    {
        $log->update(['approved_by' => $approver->id]);

        return $log->fresh();
    }

    /**
     * Revoke a previously granted approval.
     */
    public function reject(TimeLog $log): TimeLog
    {
        $log->update(['approved_by' => null]);

        return $log->fresh();
    }

    /**
     * Internal: combine date + HH:MM start/end strings into full datetimes,
     * and compute duration in minutes server-side.
     *
     * Input format from FormRequest:
     *   date       => "2026-07-09"
     *   started_at => "09:00"
     *   ended_at   => "10:00"
     */
    protected function resolveTimes(array $data): array
    {
        $startedAt = Carbon::parse("{$data['date']} {$data['started_at']}");
        $endedAt = Carbon::parse("{$data['date']} {$data['ended_at']}");
        $duration = (int) $startedAt->diffInMinutes($endedAt);

        return [$startedAt, $endedAt, $duration];
    }

    // ════════════════════════════════════════════════════════════════════
    //  ADMIN — Time log oversight
    // ════════════════════════════════════════════════════════════════════

    /**
     * All time logs across the org, filterable + paginated.
     */
    public function getAllPaginated(array $filters = []): LengthAwarePaginator
    {
        return TimeLog::query()
            ->with([
                'user:id,name,department_id',
                'user.department:id,name',
                'task:id,title,project_id',
                'task.project:id,name,department_id',
                'approvedBy:id,name',
            ])
            ->when($filters['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v)
            )
            ->when($filters['project_id'] ?? null, fn ($q, $v) => $q->whereHas('task', fn ($t) => $t->where('project_id', $v))
            )
            ->when($filters['department_id'] ?? null, fn ($q, $v) => $q->whereHas('task.project', fn ($p) => $p->where('department_id', $v))
            )
            ->when($filters['entry_type'] ?? null, fn ($q, $v) => $q->where('entry_type', $v)
            )
            ->when($filters['approval'] ?? null, fn ($q, $v) => match ($v) {
                'approved' => $q->whereNotNull('approved_by'),
                'pending' => $q->whereNull('approved_by')->whereNotNull('ended_at'),
                'running' => $q->whereNull('ended_at'),
                default => $q,
            })
            ->when($filters['date_from'] ?? null, fn ($q, $v) => $q->whereDate('started_at', '>=', $v)
            )
            ->when($filters['date_to'] ?? null, fn ($q, $v) => $q->whereDate('started_at', '<=', $v)
            )
            ->orderBy('started_at', 'desc')
            ->paginate(25)
            ->withQueryString();
    }

    /**
     * Dropdown options for the admin filter bar.
     */
    public function getAdminFilterOptions(): array
    {
        return [
            'users' => User::orderBy('name')->get(['id', 'name']),
            'projects' => Project::orderBy('name')->get(['id', 'name']),
            'departments' => Department::orderBy('name')
                ->get(['id', 'name']),
            'entry_types' => [TimeLog::TYPE_TIMER, TimeLog::TYPE_MANUAL],
        ];
    }

    /**
     * Unapproved, finished entries grouped by user — for the approval queue.
     */
    public function getPendingGroupedByUser(): SupportCollection
    {
        return TimeLog::query()
            ->whereNull('approved_by')
            ->whereNotNull('ended_at')
            ->with([
                'user:id,name,department_id',
                'user.department:id,name',
                'task:id,title,project_id',
                'task.project:id,name',
            ])
            ->orderBy('started_at', 'desc')
            ->get()
            ->groupBy('user_id')
            ->map(function ($logs) {
                $user = $logs->first()->user;

                return [
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'department' => $user->department?->name,
                    ],
                    'total_minutes' => (int) $logs->sum('duration_minutes'),
                    'entry_count' => $logs->count(),
                    'logs' => $logs->values(),
                ];
            })
            ->values();
    }

    /**
     * Aggregate reports for a period ('week' or 'month'):
     * totals per user, per project, per department, plus approval breakdown.
     */
    public function getReports(string $period = 'week'): array
    {
        [$start, $end] = $this->periodRange($period);

        $logs = TimeLog::query()
            ->whereNotNull('ended_at')
            ->whereBetween('started_at', [$start, $end])
            ->with([
                'user:id,name,department_id',
                'user.department:id,name',
                'task:id,title,project_id',
                'task.project:id,name,department_id',
                'task.project.department:id,name',
            ])
            ->get();

        return [
            'range' => [
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
            ],
            'totals' => [
                'total_minutes' => (int) $logs->sum('duration_minutes'),
                'approved_minutes' => (int) $logs->whereNotNull('approved_by')->sum('duration_minutes'),
                'pending_minutes' => (int) $logs->whereNull('approved_by')->sum('duration_minutes'),
                'entry_count' => $logs->count(),
                'timer_count' => $logs->where('entry_type', TimeLog::TYPE_TIMER)->count(),
                'manual_count' => $logs->where('entry_type', TimeLog::TYPE_MANUAL)->count(),
            ],
            'by_user' => $logs->groupBy('user_id')->map(fn ($g) => [
                'name' => $g->first()->user?->name,
                'department' => $g->first()->user?->department?->name,
                'total_minutes' => (int) $g->sum('duration_minutes'),
                'entry_count' => $g->count(),
            ])->sortByDesc('total_minutes')->values(),
            'by_project' => $logs->groupBy(fn ($l) => $l->task?->project_id)->map(fn ($g) => [
                'name' => $g->first()->task?->project?->name,
                'total_minutes' => (int) $g->sum('duration_minutes'),
                'entry_count' => $g->count(),
            ])->sortByDesc('total_minutes')->values(),
            'by_department' => $logs->groupBy(fn ($l) => $l->task?->project?->department_id)->map(fn ($g) => [
                'name' => $g->first()->task?->project?->department?->name,
                'total_minutes' => (int) $g->sum('duration_minutes'),
                'entry_count' => $g->count(),
            ])->sortByDesc('total_minutes')->values(),
        ];
    }

    /**
     * Bulk approve. Skips running timers and already-approved entries.
     * Returns the number of entries actually approved.
     */
    public function bulkApprove(array $timeLogIds, User $approver): int
    {
        return TimeLog::query()
            ->whereIn('id', $timeLogIds)
            ->whereNull('approved_by')
            ->whereNotNull('ended_at')
            ->update(['approved_by' => $approver->id]);
    }

    /**
     * Internal: resolve a period name into a [start, end] Carbon pair.
     */
    protected function periodRange(string $period): array
    {
        return match ($period) {
            'month' => [now()->startOfMonth(), now()->endOfMonth()],
            default => [now()->startOfWeek(), now()->endOfWeek()],
        };
    }
}
