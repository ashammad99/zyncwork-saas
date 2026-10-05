<?php

namespace App\Features\TaskEmployee\Controllers;

use App\Core\BaseController;
use App\Features\Auth\Models\User;
use App\Features\Comment\Services\CommentService;
use App\Features\TaskAdmin\Models\Task;
use App\Features\TaskEmployee\Requests\UpdateTaskStatusRequest;
use App\Features\TaskEmployee\Services\EmployeeTaskService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeTaskController extends BaseController
{
    public function __construct(
        protected EmployeeTaskService $taskService,
    ) {}

    // ── Index ──────────────────────────────────────────────────────────────
    public function index(Request $request): Response
    {
        $employee = $request->user();

        $filters = $request->only([
            'search', 'status', 'priority', 'project_id', 'due',
        ]);

        $tasks = $this->taskService->getAssignedTasks($employee, $filters);
        $options = $this->taskService->getFilterOptions($employee);

        return Inertia::render('(portals)/employee/tasks/Index', [
            'tasks' => $tasks,
            'filters' => $filters,
            'projects' => $options['projects'],
            'statuses' => $options['statuses'],
            'priorities' => $options['priorities'],
        ]);
    }

    // ── Show ───────────────────────────────────────────────────────────────
    public function show(Task $task): Response
    {
        $this->authorize('view', $task);

        $task = $this->taskService->getTaskDetail($task);

        return Inertia::render('(portals)/employee/tasks/Show', [
            'task' => $task,
            'currentUser' => auth()->user(),
            'comments' => app(CommentService::class)
                ->getThreadForTask($task),
            'mentionableUsers' => User::query()
                ->where('is_active', true)
                ->where('id', '!=', auth()->id())
                ->where(function ($q) use ($task) {
                    $q->where('department_id', $task->project->department_id)
                        ->orWhere('role', 'admin');
                })
                ->orderBy('name')
                ->get(['id', 'name']),
            'canTransition' => [
                'to_todo' => $task->status === Task::STATUS_BACKLOG,
                'to_in_progress' => $task->status === Task::STATUS_TODO,
                'to_in_review' => $task->status === Task::STATUS_IN_PROGRESS,
            ],
        ]);
    }

    // ── Update status ──────────────────────────────────────────────────────
    public function updateStatus(UpdateTaskStatusRequest $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $task = $this->taskService->transitionStatus(
            $task,
            $request->validated('status'),
        );

        return back()->with('success', "Task moved to {$task->status}.");
    }
}
