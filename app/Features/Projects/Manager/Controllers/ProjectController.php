<?php

namespace App\Features\Projects\Manager\Controllers;

use App\Core\BaseController;
use App\Features\Auth\Models\User;
use App\Features\Projects\Admin\Models\Project;
use App\Features\Projects\Manager\Requests\StoreProjectRequest;
use App\Features\Projects\Manager\Requests\UpdateProjectRequest;
use App\Features\Projects\Manager\Services\ProjectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends BaseController
{
    public function __construct(private readonly ProjectService $projectService) {}

    /**
     * GET /manager/projects
     */
    public function index(Request $request): Response
    {
        //        dd([
        //            'role'          => $request->user()->role,
        //            'department_id' => $request->user()->department_id,
        //        ]);

        $this->authorize('viewAny', Project::class);

        $projects = $this->projectService->listForManager(
            manager: $request->user(),
            filters: $request->only(['status', 'search'])
        );

        return Inertia::render('(portals)/manager/projects/page', [
            'projects' => $projects,
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    /**
     * GET /manager/projects/create
     */
    public function create(): Response
    {
        $this->authorize('create', Project::class);

        return Inertia::render('(portals)/manager/projects/page');
    }

    /**
     * POST /manager/projects
     */
    public function store(StoreProjectRequest $request): RedirectResponse
    {
        $project = $this->projectService->create(
            manager: $request->user(),
            data: $request->validated()
        );

        return redirect()
            ->route('manager.projects.index', $project)
            ->with('success', 'Project created successfully.');
    }

    /**
     * GET /manager/projects/{project}
     */
    public function show(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $filters = $request->only(['search', 'status', 'priority', 'assignee_id', 'due_date']);
        $tab = $request->query('tab') === 'timeline' ? 'timeline' : 'tasks';
        $loadedProject = $this->projectService->findWithRelations($project);

        return Inertia::render('(portals)/manager/projects/Show', [
            'project' => $loadedProject,
            'tab' => $tab,
            // Only the active tab's data is queried — the Timeline aggregates are
            // skipped entirely while the Tasks tab is being viewed, and vice versa.
            'tasks' => $tab === 'tasks'
                ? $this->projectService->getProjectTasks($project, $filters)
                : null,
            'timeline' => $tab === 'timeline'
                ? [
                    'tasks' => $this->projectService->getProjectTimelineTasks($project, $filters),
                    'bucketCounts' => $this->projectService->getProjectTimelineBucketCounts($project, $filters),
                    'projectTaskTotal' => $this->projectService->getProjectTaskTotal($project),
                ]
                : null,
            'statusCounts' => $this->projectService->getProjectStatusCounts($project),
            'totalMinutes' => $this->projectService->getProjectTotalMinutes($project),
            'filters' => $filters,
            'employees' => User::query()
                ->where('department_id', $project->department_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    /**
     * GET /manager/projects/{project}/edit
     */
    public function edit(Project $project): Response
    {
        $this->authorize('update', $project);

        return Inertia::render('(portals)/manager/projects/page', [
            'project' => $project,
        ]);
    }

    /**
     * PUT /manager/projects/{project}
     */
    public function update(UpdateProjectRequest $request, Project $project): RedirectResponse
    {
        $this->projectService->update($project, $request->validated());

        return redirect()
            ->route('manager.projects.index')
            ->with('success', 'Project updated successfully.');
    }

    /**
     * DELETE /manager/projects/{project}
     * Archives the project instead of hard deleting.
     */
    public function destroy(Project $project): RedirectResponse
    {
        $this->authorize('delete', $project);

        $this->projectService->archive($project);

        return redirect()
            ->route('manager.projects.index')
            ->with('success', 'Project archived successfully.');
    }
}
