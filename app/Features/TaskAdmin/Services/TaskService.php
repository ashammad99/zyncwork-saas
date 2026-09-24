<?php

namespace App\Features\TaskAdmin\Services;

use App\Core\BaseService;
use App\Features\ActivityLog\Models\ActivityLog;
use App\Features\Auth\Models\User;
use App\Features\TaskAdmin\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Features\Notification\Services\TaskNotifierService;
class TaskService extends BaseService
{
    public function __construct(
        private readonly TaskNotifierService $notifier,
    ) {}
    // ── Valid transitions ──────────────────────────────────────────────────
    private const TRANSITIONS = [
        Task::STATUS_BACKLOG     => [Task::STATUS_TODO],
        Task::STATUS_TODO        => [Task::STATUS_IN_PROGRESS],
        Task::STATUS_IN_PROGRESS => [Task::STATUS_IN_REVIEW],
        Task::STATUS_IN_REVIEW   => [Task::STATUS_COMPLETED, Task::STATUS_IN_PROGRESS],
        Task::STATUS_COMPLETED   => [Task::STATUS_TODO], // reactivate only
    ];

    // Transitions only managers/admins can trigger
    private const MANAGER_ONLY_TRANSITIONS = [
        Task::STATUS_IN_REVIEW   . '->' . Task::STATUS_COMPLETED,
        Task::STATUS_IN_REVIEW   . '->' . Task::STATUS_IN_PROGRESS,
        Task::STATUS_COMPLETED   . '->' . Task::STATUS_TODO,
    ];

    // ── Create ─────────────────────────────────────────────────────────────
    public function create(array $data, User $actor): Task
    {
        if (isset($data['depends_on_task_id'])) {
            $this->assertNoCycle($data['depends_on_task_id'], null);
        }

        return DB::transaction(function () use ($data, $actor) {
            $task = Task::create(array_merge($data, ['created_by' => $actor->id]));

            $this->logActivity($actor, 'created', $task, null, $task->toArray());

            if ($task->assignee_id) {
                $this->notifier->taskAssigned($task, $task->assignee_id, $actor);
            }

            return $task;
        });
    }

    // ── Update ─────────────────────────────────────────────────────────────
    public function update(Task $task, array $data, User $actor): Task
    {
        if (
            isset($data['depends_on_task_id'])
            && $data['depends_on_task_id'] != $task->depends_on_task_id
        ) {
            $this->assertNoCycle($data['depends_on_task_id'], $task->id);
        }

        return DB::transaction(function () use ($task, $data, $actor) {
            $old = $task->toArray();
            $task->update($data);
            $this->logActivity($actor, 'updated', $task, $old, $task->fresh()->toArray());

            return $task->fresh();
        });
    }

    // ── Delete ─────────────────────────────────────────────────────────────
    public function delete(Task $task, User $actor): void
    {
        DB::transaction(function () use ($task, $actor) {
            $this->logActivity($actor, 'deleted', $task, $task->toArray(), null);
            $task->delete();
        });
    }

    // ── Status transition ──────────────────────────────────────────────────
    public function transition(Task $task, string $newStatus, User $actor): Task
    {
        $from = $task->status;
        $transitionKey = "{$from}->{$newStatus}";

        // 1. Valid transition?
        if (! in_array($newStatus, self::TRANSITIONS[$from] ?? [])) {
            throw ValidationException::withMessages([
                'status' => "Cannot transition from '{$from}' to '{$newStatus}'.",
            ]);
        }

        // 2. Manager-only check
        if (
            in_array($transitionKey, self::MANAGER_ONLY_TRANSITIONS)
            && ! in_array($actor->role, ['admin', 'manager'], true)
        ) {
            throw ValidationException::withMessages([
                'status' => 'Only a manager can perform this transition.',
            ]);
        }

        // 3. Dependency block: Todo → In Progress
        if ($from === Task::STATUS_TODO && $newStatus === Task::STATUS_IN_PROGRESS) {
            if ($task->isBlocked()) {
                throw ValidationException::withMessages([
                    'status' => "Task is blocked by: \"{$task->dependency->title}\".",
                ]);
            }
        }

        return DB::transaction(function () use ($task, $from, $newStatus, $actor) {
            $updates = ['status' => $newStatus];

            if ($newStatus === Task::STATUS_COMPLETED) {
                $updates['completed_at'] = now();
            }

            // Reactivate: clear completed_at and push dependents back to Todo
            if ($from === Task::STATUS_COMPLETED && $newStatus === Task::STATUS_TODO) {
                $updates['completed_at'] = null;
                $this->pushDependentsBackToTodo($task, $actor);
            }

            $old = $task->toArray();
            $task->update($updates);

            $this->logActivity($actor, 'status_changed', $task, ['status' => $from], ['status' => $newStatus]);

            // Notify department manager when a task enters review
            if ($newStatus === Task::STATUS_IN_REVIEW) {
                $this->notifier->taskInReview($task, $actor);
            }

            return $task->fresh();
        });
    }
    // ── Assign ─────────────────────────────────────────────────────────────
    public function assign(Task $task, ?int $assigneeId, User $actor): Task
    {
        $old = ['assignee_id' => $task->assignee_id];
        $task->update(['assignee_id' => $assigneeId]);
        $this->logActivity($actor, 'assigned', $task, $old, ['assignee_id' => $assigneeId]);

        if ($assigneeId !== $old['assignee_id']) {
            $this->notifier->taskAssigned($task, $assigneeId, $actor);
        }

        return $task->fresh(['assignee']);
    }
    // ── Private helpers ────────────────────────────────────────────────────
    private function pushDependentsBackToTodo(Task $task, User $actor): void
    {
        $dependents = Task::where('depends_on_task_id', $task->id)
            ->whereNotIn('status', [Task::STATUS_BACKLOG, Task::STATUS_TODO])
            ->get();

        foreach ($dependents as $dep) {
            $old = ['status' => $dep->status];
            $dep->update(['status' => Task::STATUS_TODO, 'completed_at' => null]);
            $this->logActivity($actor, 'dependency_reactivated', $dep, $old, ['status' => Task::STATUS_TODO]);
        }
    }

    /**
     * Traverse the dependency chain starting from $dependsOnId to ensure
     * setting it on $taskId would not create a cycle.
     */
    private function assertNoCycle(?int $dependsOnId, ?int $taskId): void
    {
        if ($dependsOnId === null) {
            return;
        }

        $visited = [];
        $current = $dependsOnId;

        while ($current !== null) {
            if ($current === $taskId) {
                throw ValidationException::withMessages([
                    'depends_on_task_id' => 'Circular task dependency detected.',
                ]);
            }

            if (in_array($current, $visited)) {
                break; // already-existing cycle in DB, stop
            }

            $visited[] = $current;
            $current   = Task::find($current)?->depends_on_task_id;
        }
    }

    private function logActivity(User $actor, string $action, Task $task, ?array $old, ?array $new): void
    {
        ActivityLog::create([
            'user_id'    => $actor->id,
            'action'     => $action,
            'model_type' => Task::class,
            'model_id'   => $task->id,
            'old_value'  => $old,
            'new_value'  => $new,
            'ip_address' => request()->ip(),
        ]);
    }
}
