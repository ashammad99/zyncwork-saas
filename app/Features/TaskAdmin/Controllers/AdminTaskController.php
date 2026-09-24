<?php

namespace App\Features\TaskAdmin\Controllers;

use App\Core\BaseController;

use App\Features\Auth\Models\User;
use App\Features\Departments\Admin\Models\Department;
use App\Features\Projects\Admin\Models\Project;
use App\Features\TaskAdmin\Models\Task;
use App\Features\TaskAdmin\Requests\AssignTaskRequest;
use App\Features\TaskAdmin\Requests\StoreTaskRequest;
use App\Features\TaskAdmin\Requests\UpdateTaskRequest;
use App\Features\TaskAdmin\Requests\UpdateTaskStatusRequest;
use App\Features\TaskAdmin\Services\TaskService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use App\Features\ActivityLog\Models\ActivityLog;

class AdminTaskController extends BaseController
{
    public function __construct(private readonly TaskService $taskService)
    {
        $this->middleware(['auth', 'role:admin']);
    }

    // ── Index ──────────────────────────────────────────────────────────────
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Task::class);

        $filters = $request->only(['search', 'status', 'priority', 'project_id', 'department_id', 'assignee_id', 'due_date_from', 'due_date_to']);

        $tasks = Task::query()
            ->with(['project.department', 'assignee', 'creator', 'dependency'])
            ->when($filters['search'] ?? null, fn($q, $s) => $q->where('title', 'like', "%{$s}%")
            )
            ->when($filters['status'] ?? null, fn($q, $s) => $q->where('status', $s))
            ->when($filters['priority'] ?? null, fn($q, $p) => $q->where('priority', $p))
            ->when($filters['project_id'] ?? null, fn($q, $p) => $q->where('project_id', $p))
            ->when($filters['department_id'] ?? null, fn($q, $d) => $q->whereHas('project', fn($pq) => $pq->where('department_id', $d))
            )
            ->when($filters['assignee_id'] ?? null, fn($q, $a) => $q->where('assignee_id', $a))
            ->when($filters['due_date_from'] ?? null, fn($q, $d) => $q->whereDate('due_date', '>=', $d))
            ->when($filters['due_date_to'] ?? null, fn($q, $d) => $q->whereDate('due_date', '<=', $d))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('(portals)/admin/tasks/Index', [
            'tasks' => $tasks,
            'filters' => $filters,
            'departments' => Department::orderBy('name')->get(['id', 'name']),
            'projects' => Project::orderBy('name')->get(['id', 'name', 'department_id']),
            'users' => User::orderBy('name')->get(['id', 'name']),
            'statuses' => Task::STATUSES,
            'priorities' => Task::PRIORITIES,
        ]);
    }

    // ── Store ──────────────────────────────────────────────────────────────
    public function store(StoreTaskRequest $request)
    {
        $task = $this->taskService->create($request->validated(), $request->user());

        return redirect()
            ->route('admin.tasks.show', $task)
            ->with('success', 'Task created successfully.');
    }

    // ── Show ───────────────────────────────────────────────────────────────
    public function show(Task $task): Response
    {
        $this->authorize('view', $task);

        $task->load([
            'project.department',
            'assignee',
            'creator',
            'dependency',
            'dependents.assignee',
            'timeLogs.user',
            'comments.user',
            'comments.replies.user',
//            'attachments.uploader',
        ]);

        $activityLogs = ActivityLog::where('model_type', Task::class)
            ->where('model_id', $task->id)
            ->with('user')
            ->latest()
            ->get();

        $timeLogs = $task->timeLogs()
            ->with(['user', 'approvedBy'])
            ->latest('started_at')
            ->get();

        return Inertia::render('(portals)/admin/tasks/show', [
            'task' => $task,
            'priorities'   => ['low', 'medium', '   high', 'urgent'],
            'activityLogs' => $activityLogs,
            'timeLogs' => $timeLogs,
            'currentUser'  => auth()->user(),
            'tasks'        => Task::select('id', 'title')->where('id', '!=', $task->id)->get(), // ← this was missing
            'users' => User::orderBy('name')->get(['id', 'name']),
            'statuses' => Task::STATUSES,
            'projects'     => Project::select('id', 'name')->orderBy('name')->get(),
            'canTransition' => [
                'to_todo' => !in_array($task->status, [Task::STATUS_BACKLOG, Task::STATUS_TODO]),
                'to_in_progress' => $task->status === Task::STATUS_TODO,
                'to_in_review' => $task->status === Task::STATUS_IN_PROGRESS,
                'to_completed' => $task->status === Task::STATUS_IN_REVIEW,
                'reactivate' => $task->status === Task::STATUS_COMPLETED,
            ],
            'comments'      => $task->comments->whereNull('parent_id')->values(),
            'mentionableUsers' => \App\Features\Auth\Models\User::query()
                ->where('is_active', true)
                ->where('id', '!=', auth()->id())
                ->orderBy('name')
                ->get(['id', 'name']),

        ]);
    }

    // ── Update ─────────────────────────────────────────────────────────────
    public function update(UpdateTaskRequest $request, Task $task)
    {
        $this->taskService->update($task, $request->validated(), $request->user());

        return redirect()
            ->route('admin.tasks.show', $task)
            ->with('success', 'Task updated successfully.');
    }

    // ── Update Status ──────────────────────────────────────────────────────
    public function updateStatus(UpdateTaskStatusRequest $request, Task $task)
    {
        $task = $this->taskService->transition($task, $request->validated('status'), $request->user());

        return back()->with('success', "Task moved to {$task->status}.");
    }

    // ── Assign ─────────────────────────────────────────────────────────────
    public function assign(AssignTaskRequest $request, Task $task)
    {
        $this->taskService->assign($task, $request->validated('assignee_id'), $request->user());

        return back()->with('success', 'Task assigned successfully.');
    }

    // ── Destroy ────────────────────────────────────────────────────────────
    public function destroy(Task $task)
    {
        $this->authorize('delete', $task);

        $this->taskService->delete($task, request()->user());

        return redirect()
            ->route('admin.tasks.index')
            ->with('success', 'Task deleted.');
    }
}
