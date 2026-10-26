<?php

namespace App\Features\TaskManager\Services;

use App\Core\BaseService;
use App\Features\Auth\Models\User;
use App\Features\Notification\Services\TaskNotifierService;
use App\Features\Projects\Admin\Models\Project;
use App\Features\TaskAdmin\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManagerTaskService extends BaseService
{
    public function __construct(
        // ...existing deps...
        private readonly TaskNotifierService $notifier,
    ) {}

    // ─── Valid transitions a manager can perform ──────────────────────────────
    private const ALLOWED_TRANSITIONS = [
        Task::STATUS_BACKLOG => [Task::STATUS_TODO],
        Task::STATUS_TODO => [Task::STATUS_IN_PROGRESS],
        Task::STATUS_IN_PROGRESS => [Task::STATUS_IN_REVIEW],
        Task::STATUS_IN_REVIEW => [Task::STATUS_COMPLETED, Task::STATUS_IN_PROGRESS],
        Task::STATUS_COMPLETED => [Task::STATUS_TODO], // reactivate
    ];

    // ─── List ─────────────────────────────────────────────────────────────────

    public function getAll(User $manager, array $filters = [])
    {
        $query = Task::with(['assignee', 'project', 'dependency'])
            ->whereHas('project', fn ($q) => $q->where('department_id', $manager->department_id)
            );

        if (! empty($filters['search'])) {
            $query->where('title', 'like', '%'.$filters['search'].'%');
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (! empty($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        if (! empty($filters['assignee_id'])) {
            $query->where('assignee_id', $filters['assignee_id']);
        }

        if (! empty($filters['due_date'])) {
            $query->whereDate('due_date', $filters['due_date']);
        }

        return $query->latest()->paginate(20)->withQueryString();
    }

    // ─── Single Task ──────────────────────────────────────────────────────────

    public function find(int $id, User $manager): Task
    {
        return Task::with([
            'assignee',
            'project.department',
            'creator',
            'dependency.assignee',
            'dependents.assignee',
            'timeLogs.user',
        ])
            ->whereHas('project', fn ($q) => $q->where('department_id', $manager->department_id)
            )
            ->findOrFail($id);
    }

    // ─── Create ───────────────────────────────────────────────────────────────

    public function create(array $data, User $manager): Task
    {
        // Verify project belongs to manager's department
        $this->assertProjectInDepartment($data['project_id'], $manager->department_id);

        if (! empty($data['depends_on_task_id'])) {
            $this->detectCircularDependency($data['depends_on_task_id'], null);
        }

        return DB::transaction(function () use ($data, $manager) {
            $task = Task::create([
                ...$data,
                'created_by' => $manager->id,
                'status' => Task::STATUS_BACKLOG,
            ]);

            if ($task->assignee_id) {
                $this->notifier->taskAssigned($task, $task->assignee_id, $manager);
            }

            return $task;
        });
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function update(Task $task, array $data, User $manager): Task
    {
        $this->assertTaskInDepartment($task, $manager->department_id);

        if (! empty($data['depends_on_task_id']) && $data['depends_on_task_id'] !== $task->depends_on_task_id) {
            $this->detectCircularDependency($data['depends_on_task_id'], $task->id);
        }

        DB::transaction(function () use ($task, $data) {
            $task->update($data);
        });
        if ($task->wasChanged('assignee_id')) {
            $this->notifier->taskAssigned($task, $task->assignee_id, $manager);
        }

        return $task->fresh(['assignee', 'project', 'dependency']);
    }

    // ─── Delete ───────────────────────────────────────────────────────────────

    public function delete(Task $task, User $manager): void
    {
        $this->assertTaskInDepartment($task, $manager->department_id);

        if ($task->status === Task::STATUS_COMPLETED) {
            throw ValidationException::withMessages([
                'task' => 'Completed tasks cannot be deleted.',
            ]);
        }

        $task->delete();
    }

    // ─── State Transition ─────────────────────────────────────────────────────

    public function transition(Task $task, string $newStatus, User $manager): Task
    {
        $this->assertTaskInDepartment($task, $manager->department_id);

        $currentStatus = $task->status;
        $allowed = self::ALLOWED_TRANSITIONS[$currentStatus] ?? [];

        if (! in_array($newStatus, $allowed)) {
            throw ValidationException::withMessages([
                'status' => "Cannot transition from [{$currentStatus}] to [{$newStatus}].",
            ]);
        }

        // Block: Todo → In Progress if dependency not completed
        if ($newStatus === Task::STATUS_IN_PROGRESS && $task->depends_on_task_id) {
            $dep = Task::find($task->depends_on_task_id);
            if ($dep && $dep->status !== Task::STATUS_COMPLETED) {
                throw ValidationException::withMessages([
                    'status' => "Blocked by task: [{$dep->title}]. Complete it first.",
                ]);
            }
        }

        return DB::transaction(function () use ($task, $newStatus, $manager) {
            $old = $task->status;

            $task->status = $newStatus;

            if ($newStatus === Task::STATUS_COMPLETED) {
                $task->completed_at = now();
            }

            // Reactivate: clear completed_at, push dependents back to Todo
            if ($newStatus === Task::STATUS_TODO && $old === Task::STATUS_COMPLETED) {
                $task->completed_at = null;
                $this->reactivateDependents($task->id);
            }

            $task->save();
            if ($newStatus === Task::STATUS_IN_REVIEW) {
                $this->notifier->taskInReview($task, $manager);
            }

            return $task->fresh();
        });
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function reactivateDependents(int $taskId): void
    {
        Task::where('depends_on_task_id', $taskId)
            ->whereNotIn('status', [Task::STATUS_BACKLOG, Task::STATUS_TODO])
            ->each(function (Task $dependent) {
                $dependent->update(['status' => Task::STATUS_TODO, 'completed_at' => null]);
            });
    }

    private function detectCircularDependency(int $dependsOnId, ?int $currentTaskId): void
    {
        $visited = [];
        $id = $dependsOnId;

        while ($id !== null) {
            if ($id === $currentTaskId) {
                throw ValidationException::withMessages([
                    'depends_on_task_id' => 'Circular dependency detected.',
                ]);
            }

            if (in_array($id, $visited)) {
                break;
            }

            $visited[] = $id;
            $id = Task::where('id', $id)->value('depends_on_task_id');
        }
    }

    private function assertProjectInDepartment(int $projectId, int $departmentId): void
    {
        $exists = Project::where('id', $projectId)
            ->where('department_id', $departmentId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'project_id' => 'Project does not belong to your department.',
            ]);
        }
    }

    private function assertTaskInDepartment(Task $task, int $departmentId): void
    {
        $task->loadMissing('project');

        if ($task->project->department_id !== $departmentId) {
            abort(403, 'This task does not belong to your department.');
        }
    }

    // ─── Form helpers ─────────────────────────────────────────────────────────

    public function getFormDependencies(User $manager): array
    {
        $projects = Project::where('department_id', $manager->department_id)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);

        $employees = User::where('department_id', $manager->department_id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return compact('projects', 'employees');
    }

    public function getTasksForDependency(int $projectId, User $manager, ?int $excludeId = null)
    {
        $this->assertProjectInDepartment($projectId, $manager->department_id);

        return Task::where('project_id', $projectId)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->whereNotIn('status', [Task::STATUS_COMPLETED])
            ->orderBy('title')
            ->get(['id', 'title', 'status']);
    }

    public function assign(Task $task, ?int $assigneeId, User $manager): Task
    {
        $old = ['assignee_id' => $task->assignee_id];
        $task->update(['assignee_id' => $assigneeId]);
        $this->logActivity($manager, 'assigned', $task, $old, ['assignee_id' => $assigneeId]);

        if ($assigneeId !== $old['assignee_id']) {
            $this->notifier->taskAssigned($task, $assigneeId, $manager);
        }

        return $task->fresh(['assignee']);
    }
}
