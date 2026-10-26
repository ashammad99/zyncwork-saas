<?php

namespace App\Features\TimeLog\Services;

use App\Core\BaseService;
use App\Features\Auth\Models\User;
use App\Features\TaskAdmin\Models\Task;
use App\Features\TimeLog\Models\TimeLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TimerService extends BaseService
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
}
