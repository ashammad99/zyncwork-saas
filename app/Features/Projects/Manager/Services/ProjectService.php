<?php

namespace App\Features\Projects\Manager\Services;

use App\Features\Auth\Models\User;
use App\Features\Projects\Admin\Models\Project;
use App\Features\TaskAdmin\Models\Task;
use App\Features\TimeLog\Models\TimeLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProjectService
{
    /**
     * Timeline bucket keys, in display order. Every task falls into exactly one.
     */
    public const TIMELINE_BUCKETS = [
        'overdue',
        'today',
        'this_week',
        'next_week',
        'later',
        'no_due_date',
        'completed',
    ];

    /**
     * Return paginated projects scoped to the manager's department.
     * Applies optional filters: status, search.
     */
    public function listForManager(User $manager, array $filters = []): LengthAwarePaginator
    {
        return Project::query()
            ->where('department_id', $manager->department_id)
            ->with(['owner:id,name'])
            ->when(
                ! empty($filters['status']),
                fn ($q) => $q->where('status', $filters['status'])
            )
            ->when(
                ! empty($filters['search']),
                fn ($q) => $q->where('name', 'like', '%'.$filters['search'].'%')
            )
            ->withCount('tasks')
            ->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString();
    }

    /**
     * Create a new project assigned to the manager's department.
     */
    public function create(User $manager, array $data): Project
    {
        return DB::transaction(function () use ($manager, $data) {
            return Project::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'department_id' => $manager->department_id,
                'owner_id' => $manager->id,
                'status' => $data['status'],
                'deadline' => $data['deadline'] ?? null,
            ]);
        });
    }

    /**
     * Update an existing project.
     */
    public function update(Project $project, array $data): Project
    {
        return DB::transaction(function () use ($project, $data) {
            $project->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'status' => $data['status'],
                'deadline' => $data['deadline'] ?? null,
            ]);

            return $project->fresh(['owner:id,name']);
        });
    }

    /**
     * Archive (soft-delete via status) a project.
     * We set status = archived rather than hard delete.
     */
    public function archive(Project $project): Project
    {
        $project->update(['status' => 'archived']);

        return $project;
    }

    /**
     * Load a single project with its summary relations for the detail page.
     * Does NOT load the task list itself — see getProjectTasks() for the
     * paginated/filtered task query used by the detail page's task table.
     */
    public function findWithRelations(Project $project): Project
    {
        return $project->loadCount('tasks')
            ->load([
                'owner:id,name',
                'department:id,name',
            ]);
    }

    /**
     * Paginated, filterable task list for a single project's detail page.
     * Filters mirror the conventions already established in
     * ManagerTaskService::getAll() (search/status/priority/assignee/due_date).
     */
    public function getProjectTasks(Project $project, array $filters = []): LengthAwarePaginator
    {
        return $this->applyTaskFilters(
            Task::where('project_id', $project->id)->with(['assignee:id,name']),
            $filters
        )
            ->orderBy('status')
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * Shared task filter chain (search/status/priority/assignee/due_date), applied
     * server-side. Used by both the Tasks tab and the Timeline tab so the filtering
     * rules live in exactly one place.
     */
    private function applyTaskFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when(! empty($filters['search']), fn ($q) => $q->where('title', 'like', '%'.$filters['search'].'%'))
            ->when(! empty($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(! empty($filters['priority']), fn ($q) => $q->where('priority', $filters['priority']))
            ->when(! empty($filters['assignee_id']), fn ($q) => $q->where('assignee_id', $filters['assignee_id']))
            ->when(! empty($filters['due_date']), fn ($q) => $q->whereDate('due_date', $filters['due_date']));
    }

    /**
     * Whole-project task counts per status (independent of any pagination/
     * filtering applied to getProjectTasks(), so progress indicators always
     * reflect the full project, not just the current page).
     */
    public function getProjectStatusCounts(Project $project): array
    {
        return Task::statusCounts(Task::where('project_id', $project->id));
    }

    /**
     * Total minutes logged across every task in the project, computed at
     * the database level.
     */
    public function getProjectTotalMinutes(Project $project): int
    {
        return TimeLog::totalMinutesForProject($project->id);
    }

    // ── Timeline (Phase 5) ──────────────────────────────────────────────────

    /**
     * Date boundaries the timeline buckets are built from, using the app's
     * existing date conventions (calendar week, matching the employee task
     * due-date filters). Returns Y-m-d strings for portable SQL comparison.
     */
    private function timelineBoundaries(): array
    {
        $today = now()->startOfDay();

        return [
            $today->toDateString(),
            $today->copy()->endOfWeek()->toDateString(),
            $today->copy()->endOfWeek()->addWeek()->toDateString(),
        ];
    }

    /**
     * SQL CASE expression assigning every task to exactly one timeline bucket.
     * Ordered by precedence: completed wins over all date logic, so a completed
     * past-due task never inflates the overdue count. date() is used so the
     * comparison is portable across MySQL and the SQLite test database.
     *
     * @return array{0: string, 1: array<int, string>}
     */
    private function timelineBucketExpression(): array
    {
        [$today, $endOfWeek, $endOfNextWeek] = $this->timelineBoundaries();

        $sql = "CASE
            WHEN tasks.status = ? THEN 'completed'
            WHEN tasks.due_date IS NULL THEN 'no_due_date'
            WHEN date(tasks.due_date) < ? THEN 'overdue'
            WHEN date(tasks.due_date) = ? THEN 'today'
            WHEN date(tasks.due_date) <= ? THEN 'this_week'
            WHEN date(tasks.due_date) <= ? THEN 'next_week'
            ELSE 'later'
        END";

        return [$sql, [Task::STATUS_COMPLETED, $today, $today, $endOfWeek, $endOfNextWeek]];
    }

    /**
     * Same bucket logic, expressed as a sort rank so buckets render in display
     * order and stay contiguous across pagination pages.
     *
     * @return array{0: string, 1: array<int, string>}
     */
    private function timelineBucketRankExpression(): array
    {
        [$today, $endOfWeek, $endOfNextWeek] = $this->timelineBoundaries();

        $sql = 'CASE
            WHEN tasks.status = ? THEN 7
            WHEN tasks.due_date IS NULL THEN 6
            WHEN date(tasks.due_date) < ? THEN 1
            WHEN date(tasks.due_date) = ? THEN 2
            WHEN date(tasks.due_date) <= ? THEN 3
            WHEN date(tasks.due_date) <= ? THEN 4
            ELSE 5
        END';

        return [$sql, [Task::STATUS_COMPLETED, $today, $today, $endOfWeek, $endOfNextWeek]];
    }

    /**
     * Paginated, filtered task list for the Timeline tab. Each row carries its
     * resolved `timeline_bucket` so the frontend never re-derives date logic.
     *
     * Ordering: bucket rank, then dated tasks before undated ones, then due date
     * ascending, then id — the id tie-breaker keeps pagination stable.
     */
    public function getProjectTimelineTasks(Project $project, array $filters = []): LengthAwarePaginator
    {
        [$bucketSql, $bucketBindings] = $this->timelineBucketExpression();
        [$rankSql, $rankBindings] = $this->timelineBucketRankExpression();

        return $this->applyTaskFilters(
            Task::where('project_id', $project->id)->with(['assignee:id,name']),
            $filters
        )
            ->select('tasks.*')
            // Single correlated subquery per row set — no N+1, no time-log rows loaded.
            ->withSum('timeLogs as logged_minutes', 'duration_minutes')
            ->selectRaw("{$bucketSql} as timeline_bucket", $bucketBindings)
            ->orderByRaw("{$rankSql} asc", $rankBindings)
            ->orderByRaw('(tasks.due_date is null) asc')
            ->orderBy('tasks.due_date')
            ->orderBy('tasks.id')
            ->paginate(15)
            ->withQueryString()
            // Raw aggregates come back untyped (string/null depending on driver);
            // normalise to int so the payload is consistent for the frontend.
            ->through(function (Task $task) {
                $task->logged_minutes = (int) ($task->logged_minutes ?? 0);

                return $task;
            });
    }

    /**
     * Task counts per timeline bucket across the whole filtered result set —
     * one GROUP BY aggregate, independent of pagination. Zero-filled so every
     * bucket is always present in the payload.
     */
    public function getProjectTimelineBucketCounts(Project $project, array $filters = []): array
    {
        [$bucketSql, $bucketBindings] = $this->timelineBucketExpression();

        $raw = $this->applyTaskFilters(
            Task::where('project_id', $project->id),
            $filters
        )
            ->selectRaw("{$bucketSql} as timeline_bucket, COUNT(*) as aggregate", $bucketBindings)
            ->groupBy('timeline_bucket')
            ->pluck('aggregate', 'timeline_bucket');

        return collect(self::TIMELINE_BUCKETS)
            ->mapWithKeys(fn ($bucket) => [$bucket => (int) ($raw[$bucket] ?? 0)])
            ->toArray();
    }

    /**
     * Unfiltered task count for the project — powers the "Showing 8 of 42"
     * context line so users keep whole-project context while filtering.
     */
    public function getProjectTaskTotal(Project $project): int
    {
        return Task::where('project_id', $project->id)->count();
    }
}
