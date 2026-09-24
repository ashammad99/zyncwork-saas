<?php

namespace App\Features\TaskAdmin\Policies;

use App\Features\TaskAdmin\Models\Task;
use App\Features\Auth\Models\User;

class TaskPolicy
{
    public function before(User $user): ?bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'manager', 'employee']);
    }

    public function view(User $user, Task $task): bool
    {
        if ($user->role === 'manager') {
            return $task->project->department_id === $user->department_id;
        }

        if ($user->role === 'employee') {
            return $task->assignee_id === $user->id
                || $task->project->department_id === $user->department_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'manager', 'employee']);
    }

    public function update(User $user, Task $task): bool
    {
        if ($user->role === 'manager') {
            return $task->project->department_id === $user->department_id;
        }

        if ($user->role === 'employee') {
            return $task->assignee_id === $user->id && ! $task->isCompleted();
        }

        return false;
    }

    public function delete(User $user, Task $task): bool
    {
        if ($user->role === 'manager') {
            return $task->project->department_id === $user->department_id;
        }

        return false;
    }

    public function updateStatus(User $user, Task $task): bool
    {
        if ($user->role === 'manager') {
            return $task->project->department_id === $user->department_id;
        }

        if ($user->role === 'employee') {
            return $task->assignee_id === $user->id;
        }

        return false;
    }

    public function reactivate(User $user, Task $task): bool
    {
        if ($user->role === 'manager') {
            return $task->project->department_id === $user->department_id;
        }

        return false;
    }

    public function assign(User $user, Task $task): bool
    {
        if ($user->role === 'manager') {
            return $task->project->department_id === $user->department_id;
        }

        return false;
    }
}
