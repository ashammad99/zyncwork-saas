<?php

namespace App\Features\TaskManager\Controllers;

use App\Core\BaseController;
use App\Features\TaskAdmin\Models\Task;
use App\Features\TaskManager\Requests\StoreTaskRequest;
use App\Features\TaskManager\Requests\UpdateTaskRequest;
use App\Features\TaskManager\Requests\UpdateTaskStatusRequest;
use App\Features\TaskManager\Services\ManagerTaskService;
use Inertia\Inertia;
use Illuminate\Http\RedirectResponse;

use Inertia\Response;

class ManagerTaskController extends BaseController
{
    public function __construct(
        protected ManagerTaskService $taskService
    ) {}

    // ─── Index ────────────────────────────────────────────────────────────────

    public function index(): Response
    {
        $this->authorize('viewAny', Task::class);

        $manager = auth()->user();
        $filters = request()->only(['search', 'status', 'priority', 'project_id', 'assignee_id', 'due_date']);

        $tasks = $this->taskService->getAll($manager, $filters);
        $deps  = $this->taskService->getFormDependencies($manager);

        return Inertia::render('(portals)/manager/tasks/Index', [
            'tasks'     => $tasks,
            'projects'  => $deps['projects'],
            'employees' => $deps['employees'],
            'filters'   => $filters,
        ]);
    }

    // ─── Create ───────────────────────────────────────────────────────────────

    public function create(): Response
    {
        $this->authorize('create', Task::class);

        $manager = auth()->user();
        $deps    = $this->taskService->getFormDependencies($manager);

        return Inertia::render('(portals)/manager/tasks/Create', [
            'projects'  => $deps['projects'],
            'employees' => $deps['employees'],
        ]);
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function store(StoreTaskRequest $request): RedirectResponse
    {
        $this->authorize('create', Task::class);

        $task = $this->taskService->create($request->validated(), auth()->user());

        return redirect()
            ->route('manager.tasks.show', $task->id)
            ->with('success', 'Task created successfully.');
    }

    // ─── Show ─────────────────────────────────────────────────────────────────

    public function show(Task $task): Response
    {
        $this->authorize('view', $task);

        $manager  = auth()->user();
        $fullTask = $this->taskService->find($task->id, $manager);

        $deps = $this->taskService->getFormDependencies($manager);

        // Load comments with user + replies
        $comments = $fullTask->comments()
            ->with(['user', 'replies.user'])
            ->whereNull('parent_id')
            ->latest()
            ->get();

        return Inertia::render('(portals)/manager/tasks/Show', [
            'task'      => $fullTask,
            'comments'  => $comments,
            'projects'  => $deps['projects'],
            'employees' => $deps['employees'],
            'currentUser' => $manager->only(['id', 'name', 'role']),
            'mentionableUsers' => \App\Features\Auth\Models\User::query()
                ->where('is_active', true)
                ->where('id', '!=', $manager->id)
                ->where(function ($q) use ($fullTask) {
                    $q->where('department_id', $fullTask->project->department_id)
                        ->orWhere('role', 'admin');
                })
                ->orderBy('name')
                ->get(['id', 'name']),
            'can' => [
                'edit'       => auth()->user()->can('update', $fullTask),
                'delete'     => auth()->user()->can('delete', $fullTask),
                'transition' => auth()->user()->can('updateStatus', $fullTask),
                'reactivate' => auth()->user()->can('reactivate', $fullTask),
                'assign'     => auth()->user()->can('assign', $fullTask),
            ],
        ]);
    }

    // ─── Edit ─────────────────────────────────────────────────────────────────

    public function edit(Task $task): Response
    {
        $this->authorize('update', $task);

        $manager  = auth()->user();
        $fullTask = $this->taskService->find($task->id, $manager);
        $deps     = $this->taskService->getFormDependencies($manager);

        // Tasks available as dependency (same project, exclude self)
        $dependencyOptions = $fullTask->project_id
            ? $this->taskService->getTasksForDependency($fullTask->project_id, $manager, $task->id)
            : [];

        return Inertia::render('(portals)/manager/tasks/Edit', [
            'task'              => $fullTask,
            'projects'          => $deps['projects'],
            'employees'         => $deps['employees'],
            'dependencyOptions' => $dependencyOptions,
        ]);
    }

    // ─── Update ───────────────────────────────────────────────────────────────

    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $this->taskService->update($task, $request->validated(), auth()->user());

        return redirect()
            ->route('manager.tasks.show', $task->id)
            ->with('success', 'Task updated successfully.');
    }

    // ─── Delete ───────────────────────────────────────────────────────────────

    public function destroy(Task $task): RedirectResponse
    {
        $this->authorize('delete', $task);

        $this->taskService->delete($task, auth()->user());

        return redirect()
            ->route('manager.tasks.index')
            ->with('success', 'Task deleted.');
    }

    // ─── Status Transition ────────────────────────────────────────────────────

    public function updateStatus(UpdateTaskStatusRequest $request, Task $task): RedirectResponse
    {
        $this->authorize('updateStatus', $task);

        $this->taskService->transition($task, $request->validated()['status'], auth()->user());

        return back()->with('success', 'Task status updated.');
    }

    // ─── Dependency options (AJAX helper) ────────────────────────────────────

    public function dependencyOptions(int $projectId): \Illuminate\Http\JsonResponse
    {
        $manager = auth()->user();
        $taskId  = request()->integer('exclude');

        $options = $this->taskService->getTasksForDependency($projectId, $manager, $taskId ?: null);

        return response()->json($options);
    }
    public function assign(\Illuminate\Http\Request $request, Task $task): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('assign', $task);

        $validated = $request->validate([
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $this->taskService->assign($task, $validated['assignee_id'], $request->user());

        return back()->with('success', 'Task assigned.');
    }
    public function team(\Illuminate\Http\Request $request): Response
    {
        $manager   = $request->user();
        $weekStart = now()->startOfWeek();
        $weekEnd   = now()->endOfWeek();

        $members = \App\Features\Auth\Models\User::query()
            ->where('department_id', $manager->department_id)
            ->where('role', 'employee')
            ->where('is_active', true)
            ->withCount(['assignedTasks as assigned_tasks' => fn ($q) =>
            $q->whereNotIn('status', [Task::STATUS_COMPLETED]),
            ])
            ->withSum(['timeLogs as week_minutes' => fn ($q) =>
            $q->whereBetween('started_at', [$weekStart, $weekEnd]),
            ], 'duration_minutes')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'profile_image', 'daily_capacity_hours'])
            ->map(function ($m) {
                $loggedHours   = round(($m->week_minutes ?? 0) / 60, 1);
                $capacityWeek  = ($m->daily_capacity_hours ?: 8) * 5;

                return [
                    'id'                   => $m->id,
                    'name'                 => $m->name,
                    'email'                => $m->email,
                    'profile_image'        => $m->profile_image,
                    'daily_capacity_hours' => $m->daily_capacity_hours ?: 8,
                    'assigned_tasks'       => $m->assigned_tasks,
                    'logged_hours_week'    => $loggedHours,
                    'utilization'          => $capacityWeek > 0
                        ? (int) round(($loggedHours / $capacityWeek) * 100)
                        : 0,
                ];
            });

        return Inertia::render('(portals)/manager/team/page', [
            'members' => $members,
        ]);
    }


}
