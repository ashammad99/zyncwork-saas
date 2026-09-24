<?php

namespace App\Features\Projects\Admin\Controllers;

use App\Core\BaseController;
use App\Features\Auth\Models\User;
use App\Features\Departments\Admin\Models\Department;
use App\Features\Projects\Admin\Models\Project;
use App\Features\Projects\Admin\Requests\StoreProjectRequest;
use App\Features\Projects\Admin\Requests\UpdateProjectRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Features\Projects\Admin\Services\ProjectService;
use App\Features\Projects\Manager\Services\ProjectService as ManagerProjectService;
class ProjectController extends BaseController
{
    public function __construct(
        protected ProjectService $projectService,
        protected ManagerProjectService $managerProjectService
    ) {}

    public function index(Request $request)
    {
        return Inertia::render('(portals)/admin/projects/page', [
            'projects' => $this->projectService->paginate(
                10,
                $request->only(['status', 'department_id', 'owner_id'])
            ),

            'departments' => Department::select('id', 'name')->get(),

            'owners' => User::select('id', 'name')->get(),

            'filters' => $request->only([
                'status',
                'department_id',
                'owner_id',
            ]),
        ]);
    }

    /**
     * GET /admin/projects/{project}
     * Reuses Manager\Services\ProjectService for the task list/status counts/
     * total-time queries (Phase 2) rather than duplicating that logic — Admin
     * has no department restriction, so ProjectPolicy::view() passes via its
     * existing admin bypass in before().
     */
    public function show(Request $request, Project $project)
    {
        $this->authorize('view', $project);

        $filters = $request->only(['search', 'status', 'priority', 'assignee_id', 'due_date']);
        $tab = $request->query('tab') === 'timeline' ? 'timeline' : 'tasks';

        return Inertia::render('(portals)/manager/projects/Show', [
            'role' => 'admin',
            'project' => $this->managerProjectService->findWithRelations($project),
            'tab' => $tab,
            // Only the active tab's data is queried (see Manager\ProjectController::show).
            'tasks' => $tab === 'tasks'
                ? $this->managerProjectService->getProjectTasks($project, $filters)
                : null,
            'timeline' => $tab === 'timeline'
                ? [
                    'tasks' => $this->managerProjectService->getProjectTimelineTasks($project, $filters),
                    'bucketCounts' => $this->managerProjectService->getProjectTimelineBucketCounts($project, $filters),
                    'projectTaskTotal' => $this->managerProjectService->getProjectTaskTotal($project),
                ]
                : null,
            'statusCounts' => $this->managerProjectService->getProjectStatusCounts($project),
            'totalMinutes' => $this->managerProjectService->getProjectTotalMinutes($project),
            'filters' => $filters,
            'employees' => User::query()
                ->where('department_id', $project->department_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(StoreProjectRequest $request)
    {
        $this->projectService->store($request->validated());

        return redirect()
            ->route('admin.projects.index')
            ->with('success', 'Project created successfully.');
    }

    public function update(UpdateProjectRequest $request, Project $project)
    {

        $this->projectService->update($project, $request->validated());

        return redirect()
            ->route('admin.projects.index')
            ->with('success', 'Project updated successfully.');
    }

    public function destroy(Project $project)
    {
        $this->projectService->delete($project);

        return redirect()
            ->route('admin.projects.index')
            ->with('success', 'Project deleted successfully.');
    }

}
