<?php

namespace App\Features\Report\Controllers;

use App\Core\BaseController;
use App\Features\Projects\Admin\Models\Project;
use App\Features\Report\Services\BurndownService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BurndownController extends BaseController
{
    public function __construct(
        protected BurndownService $burndown,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        // Managers see their department's projects; admins see all
        $projects = Project::query()
            ->when($user->role === 'manager', fn ($q) => $q->where('department_id', $user->department_id)
            )
            ->orderBy('name')
            ->get(['id', 'name', 'deadline']);

        $selectedId = (int) $request->query('project_id', $projects->first()?->id ?? 0);
        $selected = $projects->firstWhere('id', $selectedId) ?? $projects->first();

        $portal = $user->role === 'admin' ? 'admin' : 'manager';

        return Inertia::render("(portals)/{$portal}/reports/burndown/page", [
            'projects' => $projects,
            'selectedId' => $selected?->id,
            'burndown' => $selected
                ? $this->burndown->forProject(Project::find($selected->id))
                : null,
        ]);
    }
}
