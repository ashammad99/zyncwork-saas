<?php

namespace App\Features\Notification\Services;

use App\Core\BaseService;
use App\Features\Auth\Models\User;
use App\Features\Notification\Events\NotificationCreated;
use App\Features\TaskAdmin\Models\Task;

class TaskNotifierService extends BaseService
{
    /**
     * Notify a user they've been assigned a task.
     * Safe to call with null assignee or self-assignment — both no-op.
     */
    public function taskAssigned(Task $task, ?int $assigneeId, User $actor): void
    {
        if (! $assigneeId || $assigneeId === $actor->id) {
            return;
        }

        $assignee = User::find($assigneeId);

        if (! $assignee || ! $assignee->is_active) {
            return;
        }

        event(new NotificationCreated(
            $assignee,
            "You've been assigned a task: {$task->title}",
            "Assigned by {$actor->name}".($task->due_date ? " · due {$task->due_date->format('M j, Y')}" : ''),
            'task_assigned',
        ));
    }

    /**
     * Notify the department manager that a task entered review.
     */
    public function taskInReview(Task $task, User $actor): void
    {
        $task->loadMissing('project.department');

        $managerId = $task->project?->department?->manager_id;

        if (! $managerId || $managerId === $actor->id) {
            return;
        }

        $manager = User::find($managerId);

        if (! $manager || ! $manager->is_active) {
            return;
        }

        event(new NotificationCreated(
            $manager,
            "Task ready for review: {$task->title}",
            "{$actor->name} moved it to In Review.",
            'task_review',
        ));
    }
}
