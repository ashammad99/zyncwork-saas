<?php

namespace App\Features\TaskEmployee\Services;

use App\Core\BaseService;
use App\Features\Auth\Models\User;
use App\Features\Notification\Services\TaskNotifierService;
use App\Features\Projects\Admin\Models\Project;
use App\Features\TaskAdmin\Models\Task;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

class EmployeeTaskService extends BaseService
{
    public function __construct(
        // ...existing deps...
        private readonly TaskNotifierService $notifier,
    ) {}

    /**
     * Transitions an employee is allowed to trigger on their own tasks.
     * Matches SRS §4.4 — employees cannot mark tasks Completed or reactivate.
     */
    protected const ALLOWED_TRANSITIONS = [
        Task::STATUS_BACKLOG => [Task::STATUS_TODO],
        Task::STATUS_TODO => [Task::STATUS_IN_PROGRESS],
        Task::STATUS_IN_PROGRESS => [Task::STATUS_IN_REVIEW],
    ];

    // ── Index ──────────────────────────────────────────────────────────────

    /**
     * Fetch tasks assigned to this employee, with optional filters.
     */
    public function getAssignedTasks(User $employee, array $filters = []): LengthAwarePaginator
    {
        return Task::query()
            ->where('assignee_id', $employee->id)
            ->with([
                'project:id,name,department_id',
                'dependency:id,title,status',
            ])
            ->withCount(['timeLogs', 'comments'])
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where('title', 'like', "%{$s}%")
            )
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s)
            )
            ->when($filters['priority'] ?? null, fn ($q, $p) => $q->where('priority', $p)
            )
            ->when($filters['project_id'] ?? null, fn ($q, $p) => $q->where('project_id', $p)
            )
            ->when($filters['due'] ?? null, fn ($q, $due) => $this->applyDueFilter($q, $due)
            )
            // Priority ordering: active work first, completed last.
            // CASE instead of MySQL-only FIELD() so it also runs on SQLite (tests);
            // ELSE 0 keeps FIELD()'s behaviour of sorting unlisted values (e.g. NULL) first.
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 1 WHEN 'in_review' THEN 2 WHEN 'todo' THEN 3 WHEN 'backlog' THEN 4 WHEN 'completed' THEN 5 ELSE 0 END")
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 ELSE 0 END")
            ->orderBy('due_date', 'asc')
            ->paginate(15)
            ->withQueryString();
    }

    // ── Show ───────────────────────────────────────────────────────────────

    /**
     * Load a single task with everything needed for the detail page.
     */
    public function getTaskDetail(Task $task): Task
    {
        $task->load([
            'project.department',
            'assignee',
            'creator',
            'dependency',
            'dependents:id,title,status,assignee_id',
            'dependents.assignee:id,name',
            'timeLogs' => fn ($q) => $q
                ->with('user:id,name,profile_image')
                ->orderBy('started_at', 'desc'),
            'comments.user',
            'comments.replies.user',
        ]);

        return $task;
    }

    // ── Status transition ──────────────────────────────────────────────────

    /**
     * Advance a task through the state machine, enforcing:
     *  - only allowed transitions
     *  - dependency must be completed before Todo → In Progress
     *
     * The Policy handles "can this user touch this task at all";
     * this method handles "is this transition valid for the state machine".
     */
    public function transitionStatus(Task $task, string $newStatus): Task
    {
        $current = $task->status;
        $allowed = self::ALLOWED_TRANSITIONS[$current] ?? [];

        if (! in_array($newStatus, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "You can't move a task from {$current} to {$newStatus}.",
            ]);
        }

        // Dependency check for Todo → In Progress (SRS FR-025)
        if ($newStatus === Task::STATUS_IN_PROGRESS && $task->depends_on_task_id !== null) {
            $task->loadMissing('dependency');

            if (! $task->dependency || $task->dependency->status !== Task::STATUS_COMPLETED) {
                $blockerTitle = $task->dependency?->title ?? 'another task';
                throw ValidationException::withMessages([
                    'status' => "This task is blocked by \"{$blockerTitle}\" — it must be completed first.",
                ]);
            }
        }

        $task->update(['status' => $newStatus]);
        if ($newStatus === Task::STATUS_IN_REVIEW) {
            $this->notifier->taskInReview($task, auth()->user());
        }

        return $task->fresh();
    }

    // ── Filter dropdown options ────────────────────────────────────────────

    /**
     * Return only the projects/statuses/priorities the filter dropdown needs.
     * Projects list is scoped to ones where the employee has tasks.
     */
    public function getFilterOptions(User $employee): array
    {
        $projectIds = Task::where('assignee_id', $employee->id)
            ->pluck('project_id')
            ->unique();

        return [
            'projects' => Project::whereIn('id', $projectIds)
                ->orderBy('name')
                ->get(['id', 'name']),
            'statuses' => Task::STATUSES,
            'priorities' => Task::PRIORITIES,
        ];
    }

    // ── Internal ───────────────────────────────────────────────────────────

    protected function applyDueFilter($query, string $filter)
    {
        return match ($filter) {
            'overdue' => $query
                ->whereDate('due_date', '<', now())
                ->where('status', '!=', Task::STATUS_COMPLETED),

            'today' => $query->whereDate('due_date', now()->toDateString()),

            'this_week' => $query->whereBetween('due_date', [
                now()->startOfWeek(),
                now()->endOfWeek(),
            ]),

            'no_date' => $query->whereNull('due_date'),

            default => $query,
        };
    }
}
