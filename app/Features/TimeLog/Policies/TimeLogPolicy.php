<?php

namespace App\Features\TimeLog\Policies;

use App\Features\Auth\Models\User;
use App\Features\TimeLog\Models\TimeLog;

class TimeLogPolicy
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
        return in_array($user->role, ['manager', 'employee']);
    }

    public function view(User $user, TimeLog $log): bool
    {
        if ($user->role === 'manager') {
            return $log->task->project->department_id === $user->department_id;
        }

        // Employee can only view their own logs
        return $log->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'manager', 'employee']);
    }

    public function update(User $user, TimeLog $log): bool
    {
        // Approved logs cannot be edited
        if ($log->approved_by !== null) {
            return false;
        }

        // Running timers cannot be edited (must be stopped first)
        if ($log->entry_type === 'timer' && $log->ended_at === null) {
            return false;
        }

        // Employee can update only their own logs
        if ($user->role === 'employee') {
            return $log->user_id === $user->id;
        }

        // Manager can update logs in their department
        if ($user->role === 'manager') {
            return $log->task->project->department_id === $user->department_id;
        }

        return false;
    }

    public function delete(User $user, TimeLog $log): bool
    {
        // Approved logs cannot be deleted
        if ($log->approved_by !== null) {
            return false;
        }

        // Employee can delete only their own logs
        if ($user->role === 'employee') {
            return $log->user_id === $user->id;
        }

        // Manager can delete logs in their department
        if ($user->role === 'manager') {
            return $log->task->project->department_id === $user->department_id;
        }

        return false;
    }

    public function approve(User $user, TimeLog $log): bool
    {
        // Cannot approve a running timer
        if ($log->entry_type === 'timer' && $log->ended_at === null) {
            return false;
        }

        // Cannot approve own log
        if ($log->user_id === $user->id) {
            return false;
        }

        if ($user->role === 'manager') {
            return $log->task->project->department_id === $user->department_id;
        }

        return false;
    }

    public function reject(User $user, TimeLog $log): bool
    {
        // Same authorization as approve (revoking is the same scope)
        if ($user->role === 'manager') {
            return $log->task->project->department_id === $user->department_id;
        }

        return false;
    }
}
