<?php

namespace App\Features\TimeLog\Controllers;

use App\Core\BaseController;
use App\Features\Auth\Models\User;
use App\Features\TaskAdmin\Models\Task;
use App\Features\TimeLog\Models\TimeLog;
use App\Features\TimeLog\Services\TimeLogService;
use App\Features\TimeLog\Services\TimerService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class TimesheetController extends BaseController
{
    public function __construct(
        private readonly TimeLogService $timeLogService,
        private readonly TimerService $timerService,
    ) {}

    /** Employee: own weekly timesheet */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $weekStart = $this->resolveWeekStart($request);

        $logs = $this->timeLogService->getWeekLogs($user, $weekStart);

        return Inertia::render('(portals)/employee/timesheets/Index', [
            'logs' => $logs,
            'activeTimer' => $this->timerService->active($user),
            'weekStart' => $weekStart->toDateString(),
            'weekEnd' => $weekStart->copy()->endOfWeek()->toDateString(),
            'tasks' => $this->assignableTasks($user),
            'summary' => $this->buildWeekSummary($logs, $user),
        ]);
    }

    /** Manager: list department team members */
    public function managerIndex(Request $request): Response
    {
        $this->authorize('viewAny', TimeLog::class);

        $manager = $request->user();

        // where('department_id', null) would match every user without a department.
        abort_unless($manager->department_id, 403, 'You are not assigned to a department.');

        $filters = $request->only(['user_id', 'entry_type', 'date_from', 'date_to']);

        // Department scope: employees in the manager's department
        $employeeIds = User::where('department_id', $manager->department_id)
            ->pluck('id');

        $timeLogs = TimeLog::query()
            ->whereIn('user_id', $employeeIds)
            ->with([
                'user:id,name,profile_image',
                'task:id,title,project_id',
                'task.project:id,name',
                'approvedBy:id,name',
            ])
            ->when($filters['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($filters['entry_type'] ?? null, fn ($q, $v) => $q->where('entry_type', $v))
            ->when($filters['date_from'] ?? null, fn ($q, $v) => $q->whereDate('started_at', '>=', $v))
            ->when($filters['date_to'] ?? null, fn ($q, $v) => $q->whereDate('started_at', '<=', $v))
            ->orderBy('started_at', 'desc')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('(portals)/manager/timesheets/page', [
            'time_logs' => $timeLogs,
            'employees' => User::whereIn('id', $employeeIds)
                ->orderBy('name')
                ->get(['id', 'name']),
            'filters' => $filters,
        ]);
    }

    /** Admin: list all users with timesheet links */
    public function adminIndex(Request $request): Response
    {
        $this->authorize('viewAny', TimeLog::class);

        $weekStart = now()->startOfWeek();
        $weekEnd = now()->endOfWeek();

        $users = User::query()
            ->with('department:id,name')
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->integer('department_id')))
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->string('search').'%'))
            ->withSum(['timeLogs as week_minutes' => function ($q) use ($weekStart, $weekEnd) {
                $q->whereBetween('started_at', [$weekStart, $weekEnd]);
            }], 'duration_minutes')
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('(portals)/admin/timesheets/Index', [
            'users' => $users,
            'filters' => $request->only(['department_id', 'search']),
            'weekStart' => $weekStart->toDateString(),
        ]);
    }

    protected function resolveWeekStart(Request $request): Carbon
    {
        if ($request->filled('week')) {
            return Carbon::parse($request->string('week'))->startOfWeek();
        }

        return now()->startOfWeek();
    }

    protected function assignableTasks(User $user): Collection
    {
        return Task::query()
            ->where('assignee_id', $user->id)
            ->where('status', '!=', 'completed')
            ->with('project:id,name')
            ->orderBy('title')
            ->get(['id', 'title', 'project_id'])
            ->map(fn ($task) => [
                'id' => $task->id,
                'title' => $task->title,
                'project_name' => $task->project?->name,
            ]);
    }

    protected function buildWeekSummary($logs, User $user): array
    {
        $totalMinutes = (int) $logs->whereNotNull('duration_minutes')->sum('duration_minutes');
        $capacityHours = $user->daily_capacity_hours * 5;
        $capacityMinutes = $capacityHours * 60;

        return [
            'total_minutes' => $totalMinutes,
            'total_hours' => round($totalMinutes / 60, 2),
            'capacity_hours' => $capacityHours,
            'capacity_minutes' => $capacityMinutes,
            'utilization_pct' => $capacityMinutes > 0
                ? round(($totalMinutes / $capacityMinutes) * 100, 1)
                : 0,
            'by_day' => $logs
                ->groupBy(fn ($log) => $log->started_at->toDateString())
                ->map(fn ($day) => (int) $day->sum('duration_minutes'))
                ->toArray(),
        ];
    }

    protected function ensureCanViewUserTimesheet(User $viewer, User $target): void
    {
        if ($viewer->id === $target->id) {
            return;
        }

        if ($viewer->role === 'admin') {
            return;
        }

        if ($viewer->role === 'manager' && $target->department_id === $viewer->department_id) {
            return;
        }

        abort(403, 'You are not authorized to view this timesheet.');
    }

    public function activeTimer(Request $request, Task $task)
    {
        $active = TimeLog::running()
            ->forUser($request->user()->id)
            ->where('task_id', $task->id)
            ->first(['id', 'task_id', 'started_at']);

        return response()->json([
            'active' => $active !== null,
            'timer' => $active,
        ]);
    }
}
