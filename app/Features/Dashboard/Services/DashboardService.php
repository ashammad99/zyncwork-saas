<?php

namespace App\Features\Dashboard\Services;

use App\Core\BaseService;
use App\Features\ActivityLog\Models\ActivityLog;
use App\Features\Auth\Models\User;
use App\Features\Projects\Admin\Models\Project;
use App\Features\TaskAdmin\Models\Task;
use App\Features\TimeLog\Models\TimeLog;
use Illuminate\Database\Eloquent\Builder;

class DashboardService extends BaseService
{
    /**
     * Admin metrics
     */
    public function getAdminMetrics(): array
    {
        return [
            'total_users' => User::count(),
            'active_users' => User::where('is_active', true)->count(),
            'suspended_users' => User::where('is_active', false)->count(),
            'total_activities' => ActivityLog::count(),
            'task_status_summary' => $this->buildTaskStatusSummary(Task::query()),
        ];
    }

    /**
     * Combines Task::statusSummary() (count + estimated_hours) with
     * TimeLog::loggedMinutesByTaskStatus() (logged minutes) into one payload
     * per status, for whatever tasks $taskQuery is already scoped to. Callers
     * pass in their own scoped query — no scoping logic lives here.
     */
    private function buildTaskStatusSummary(Builder $taskQuery): array
    {
        $summary = Task::statusSummary(clone $taskQuery);
        $loggedMinutes = TimeLog::loggedMinutesByTaskStatus(clone $taskQuery);

        foreach ($summary as $status => $data) {
            $summary[$status]['logged_minutes'] = $loggedMinutes[$status] ?? 0;
        }

        return $summary;
    }

    /**
     * Manager metrics
     */
    public function getManagerMetrics(?User $manager = null): array
    {
        $projectIds = $manager
            ? Project::where('department_id', $manager->department_id)
                ->pluck('id')
            : collect();

        $taskIds = $manager
            ? Task::whereIn('project_id', $projectIds)
                ->pluck('id')
            : collect();

        $loggedHoursWeek = $manager
            ? TimeLog::whereIn('task_id', $taskIds)
                ->whereBetween('started_at', [now()->startOfWeek(), now()->endOfWeek()])
                ->whereNotNull('duration_minutes')
                ->sum('duration_minutes') / 60
            : 0;

        return [
            'active_projects' => Project::where('department_id', $manager?->department_id)
                ->where('status', 'active')
                ->count(),
            'open_tasks' => Task::whereIn('project_id', $projectIds)
                ->whereNotIn('status', ['completed'])
                ->count(),
            'logged_hours_week' => round($loggedHoursWeek, 1),
            'team_members' => User::where('department_id', $manager?->department_id)
                ->where('role', 'employee')
                ->count(),
            'task_status_summary' => $this->buildTaskStatusSummary(Task::whereIn('project_id', $projectIds)),
        ];
    }

    /**
     * Employee data (example)
     */
    public function getEmployeeData($user): array
    {
        return [
            'my_activity' => ActivityLog::where('user_id', $user->id)
                ->latest()
                ->take(10)
                ->get(),
        ];
    }

    /**
     * Shared recent activity (used by admin/manager)
     */
    public function getRecentActivity(int $limit = 10)
    {
        return ActivityLog::with('user:id,name,email,profile_image')
            ->latest()
            ->take($limit)
            ->get();
    }

    public function getUserStats(User $user): array
    {
        // Calculate profile completion
        $fields = ['name', 'email', 'phone', 'profile_image'];
        $filled = 0;
        foreach ($fields as $field) {
            if (! empty($user->{$field})) {
                $filled++;
            }
        }
        $profileCompletion = round(($filled / count($fields)) * 100);

        return [
            'profile_completion' => $profileCompletion,
            'member_since' => $user->created_at->diffForHumans(),
            'total_actions' => ActivityLog::where('user_id', $user->id)->count(),
        ];
    }

    public function getManagerRecentActivity(User $manager, int $limit = 10)
    {
        // Get project IDs in manager's department
        $projectIds = Project::where('department_id', $manager->department_id)
            ->pluck('id');

        // Get task IDs in those projects
        $taskIds = Task::whereIn('project_id', $projectIds)
            ->pluck('id');

        return ActivityLog::with('user:id,name,email,profile_image')
            ->where(function ($q) use ($projectIds, $taskIds) {
                $q->where(function ($q) use ($projectIds) {
                    $q->where('model_type', 'like', '%Project%')
                        ->whereIn('model_id', $projectIds);
                })
                    ->orWhere(function ($q) use ($taskIds) {
                        $q->where('model_type', 'like', '%Task%')
                            ->whereIn('model_id', $taskIds);
                    })
                    ->orWhere(function ($q) use ($taskIds) {
                        $q->where('model_type', 'like', '%TimeLog%')
                            ->whereIn('model_id', function ($sub) use ($taskIds) {
                                $sub->select('id')
                                    ->from('time_logs')
                                    ->whereIn('task_id', $taskIds);
                            });
                    });
            })
            ->latest()
            ->take($limit)
            ->get();
    }

    public function getEmployeeMetrics(User $user): array
    {
        $today = now()->toDateString();
        $weekStart = now()->startOfWeek();

        $tasks = Task::where('assignee_id', $user->id);

        return [
            'my_open_tasks' => (clone $tasks)
                ->whereNotIn('status', [Task::STATUS_COMPLETED])
                ->count(),

            'in_progress' => (clone $tasks)
                ->where('status', Task::STATUS_IN_PROGRESS)
                ->count(),

            'in_review' => (clone $tasks)
                ->where('status', Task::STATUS_IN_REVIEW)
                ->count(),

            'completed_this_week' => (clone $tasks)
                ->where('status', Task::STATUS_COMPLETED)
                ->where('completed_at', '>=', $weekStart)
                ->count(),

            'overdue' => (clone $tasks)
                ->whereNotIn('status', [Task::STATUS_COMPLETED])
                ->whereDate('due_date', '<', $today)
                ->count(),

            'task_status_summary' => $this->buildTaskStatusSummary(clone $tasks),
        ];
    }

    public function getEmployeeRecentActivity(User $user, int $limit = 10)
    {
        return ActivityLog::where('user_id', $user->id)
            ->latest()
            ->limit($limit)
            ->get(['id', 'action', 'model_type', 'created_at']);
    }

    /**
     * Per-employee capacity utilization for the manager's department.
     * PRD §4.4: SUM(estimated_hours) of tasks due today vs daily_capacity_hours.
     * Thresholds: <60% under · 60-100% on_track · 100-120% near_limit · >120% overloaded
     */
    public function getTeamCapacity(User $manager): array
    {
        $members = User::where('department_id', $manager->department_id)
            ->where('role', 'employee')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'profile_image', 'daily_capacity_hours']);

        $assignedToday = Task::whereIn('assignee_id', $members->pluck('id'))
            ->whereDate('due_date', now()->toDateString())
            ->whereNotIn('status', [Task::STATUS_COMPLETED])
            ->selectRaw('assignee_id, SUM(estimated_hours) as hours')
            ->groupBy('assignee_id')
            ->pluck('hours', 'assignee_id');

        return $members->map(function ($member) use ($assignedToday) {
            $assigned = (float) ($assignedToday[$member->id] ?? 0);
            $capacity = (int) $member->daily_capacity_hours ?: 8;
            $pct = $capacity > 0 ? round(($assigned / $capacity) * 100) : 0;

            return [
                'id' => $member->id,
                'name' => $member->name,
                'profile_image' => $member->profile_image,
                'assigned_hours' => round($assigned, 1),
                'capacity_hours' => $capacity,
                'utilization' => $pct,
                'status' => match (true) {
                    $pct > 120 => 'overloaded',
                    $pct > 100 => 'near_limit',
                    $pct >= 60 => 'on_track',
                    default => 'under',
                },
            ];
        })
            ->sortByDesc('utilization')
            ->values()
            ->toArray();
    }
}
