<?php

namespace App\Features\Report\Services;

use App\Core\BaseService;
use App\Features\Projects\Admin\Models\Project;
use App\Features\TaskAdmin\Models\Task;
use Illuminate\Support\Carbon;

class BurndownService extends BaseService
{
    /**
     * Build burndown series for a project.
     * Returns null when the project has no deadline (chart hidden per PRD).
     */
    public function forProject(Project $project): ?array
    {
        if (! $project->deadline) {
            return null;
        }

        $start = Carbon::parse($project->created_at)->startOfDay();
        $end = Carbon::parse($project->deadline)->startOfDay();

        if ($end->lte($start)) {
            return null;
        }

        $totalTasks = Task::where('project_id', $project->id)->count();

        // completed count per date: { '2026-06-03' => 2, ... }
        $completedByDate = Task::where('project_id', $project->id)
            ->whereNotNull('completed_at')
            ->selectRaw('DATE(completed_at) as d, COUNT(*) as c')
            ->groupBy('d')
            ->pluck('c', 'd');

        $days = (int) $start->diffInDays($end);
        $today = now()->startOfDay();
        $labels = [];
        $ideal = [];
        $actual = [];
        $doneSoFar = 0;

        for ($i = 0; $i <= $days; $i++) {
            $date = $start->copy()->addDays($i);
            $key = $date->toDateString();

            $labels[] = $key;

            // Ideal: linear descent from total → 0
            $ideal[] = round($totalTasks * (1 - $i / $days), 2);

            // Actual: only for days up to today; future = null (line stops)
            if ($date->lte($today)) {
                $doneSoFar += (int) ($completedByDate[$key] ?? 0);
                $actual[] = $totalTasks - $doneSoFar;
            } else {
                $actual[] = null;
            }
        }

        return [
            'labels' => $labels,
            'ideal' => $ideal,
            'actual' => $actual,
            'total_tasks' => $totalTasks,
            'completed' => $doneSoFar,
            'deadline' => $end->toDateString(),
        ];
    }
}
