<?php

namespace Tests\Feature;

use App\Features\Auth\Models\User;
use App\Features\Departments\Admin\Models\Department;
use App\Features\Projects\Admin\Models\Project;
use App\Features\TaskAdmin\Models\Task;
use App\Features\TimeLog\Models\TimeLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TaskStatusSummaryTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Test User',
            'email' => uniqid('user_') . '@example.com',
            'password' => Hash::make('password'),
            'role' => 'employee',
            'is_active' => true,
        ], $overrides));
    }

    private function makeDepartment(string $name = 'Engineering'): Department
    {
        return Department::create(['name' => $name]);
    }

    private function makeProject(Department $department, User $owner): Project
    {
        return Project::create([
            'department_id' => $department->id,
            'owner_id' => $owner->id,
            'name' => 'Test Project',
            'status' => 'active',
        ]);
    }

    private function makeTask(Project $project, User $creator, array $overrides = []): Task
    {
        return Task::create(array_merge([
            'project_id' => $project->id,
            'created_by' => $creator->id,
            'title' => 'Test Task',
            'status' => Task::STATUS_BACKLOG,
        ], $overrides));
    }

    // ── Model-level aggregation ─────────────────────────────────────────────

    public function test_task_status_summary_is_zero_filled_across_all_five_statuses_with_correct_counts_and_hours(): void
    {
        $department = $this->makeDepartment();
        $manager = $this->makeUser(['role' => 'manager', 'department_id' => $department->id]);
        $project = $this->makeProject($department, $manager);

        // Only 2 of the 5 statuses populated — the other 3 must still appear, zero-filled.
        $this->makeTask($project, $manager, ['status' => Task::STATUS_BACKLOG, 'estimated_hours' => 2]);
        $this->makeTask($project, $manager, ['status' => Task::STATUS_BACKLOG, 'estimated_hours' => 3]);
        $this->makeTask($project, $manager, ['status' => Task::STATUS_COMPLETED, 'estimated_hours' => 5]);

        $summary = Task::statusSummary(Task::where('project_id', $project->id));

        $this->assertSame(
            ['backlog', 'todo', 'in_progress', 'in_review', 'completed'],
            array_keys($summary)
        );

        $this->assertSame(2, $summary['backlog']['count']);
        $this->assertSame(5.0, $summary['backlog']['estimated_hours']);
        $this->assertSame(1, $summary['completed']['count']);
        $this->assertSame(5.0, $summary['completed']['estimated_hours']);

        // Zero-filled statuses
        foreach (['todo', 'in_progress', 'in_review'] as $status) {
            $this->assertSame(0, $summary[$status]['count']);
            $this->assertSame(0.0, $summary[$status]['estimated_hours']);
        }
    }

    public function test_logged_minutes_by_task_status_aggregates_via_join_and_zero_fills(): void
    {
        $department = $this->makeDepartment();
        $manager = $this->makeUser(['role' => 'manager', 'department_id' => $department->id]);
        $employee = $this->makeUser(['role' => 'employee', 'department_id' => $department->id]);
        $project = $this->makeProject($department, $manager);

        $backlogTask = $this->makeTask($project, $manager, ['status' => Task::STATUS_BACKLOG]);
        $inProgressTaskA = $this->makeTask($project, $manager, ['status' => Task::STATUS_IN_PROGRESS]);
        $inProgressTaskB = $this->makeTask($project, $manager, ['status' => Task::STATUS_IN_PROGRESS]);

        TimeLog::create([
            'task_id' => $backlogTask->id, 'user_id' => $employee->id,
            'started_at' => now(), 'ended_at' => now(), 'duration_minutes' => 30, 'entry_type' => 'manual',
        ]);
        TimeLog::create([
            'task_id' => $inProgressTaskA->id, 'user_id' => $employee->id,
            'started_at' => now(), 'ended_at' => now(), 'duration_minutes' => 40, 'entry_type' => 'manual',
        ]);
        TimeLog::create([
            'task_id' => $inProgressTaskB->id, 'user_id' => $employee->id,
            'started_at' => now(), 'ended_at' => now(), 'duration_minutes' => 25, 'entry_type' => 'manual',
        ]);

        $minutes = TimeLog::loggedMinutesByTaskStatus(Task::where('project_id', $project->id));

        $this->assertSame(
            ['backlog', 'todo', 'in_progress', 'in_review', 'completed'],
            array_keys($minutes)
        );
        $this->assertSame(30, $minutes['backlog']);
        $this->assertSame(65, $minutes['in_progress']); // 40 + 25, summed across two tasks of the same status
        $this->assertSame(0, $minutes['todo']);
        $this->assertSame(0, $minutes['in_review']);
        $this->assertSame(0, $minutes['completed']);
    }

    public function test_logged_minutes_by_task_status_respects_the_callers_scoping(): void
    {
        $department = $this->makeDepartment();
        $manager = $this->makeUser(['role' => 'manager', 'department_id' => $department->id]);
        $employee = $this->makeUser(['role' => 'employee', 'department_id' => $department->id]);

        $projectA = $this->makeProject($department, $manager);
        $projectB = $this->makeProject($department, $manager);

        $taskInA = $this->makeTask($projectA, $manager, ['status' => Task::STATUS_TODO]);
        $taskInB = $this->makeTask($projectB, $manager, ['status' => Task::STATUS_TODO]);

        TimeLog::create([
            'task_id' => $taskInA->id, 'user_id' => $employee->id,
            'started_at' => now(), 'ended_at' => now(), 'duration_minutes' => 15, 'entry_type' => 'manual',
        ]);
        TimeLog::create([
            'task_id' => $taskInB->id, 'user_id' => $employee->id,
            'started_at' => now(), 'ended_at' => now(), 'duration_minutes' => 999, 'entry_type' => 'manual',
        ]);

        // Scoped to project A only — project B's logged time must not leak in.
        $minutes = TimeLog::loggedMinutesByTaskStatus(Task::where('project_id', $projectA->id));

        $this->assertSame(15, $minutes['todo']);
    }

    // ── Dashboard payload per role ──────────────────────────────────────────

    public function test_admin_dashboard_task_status_summary_is_unscoped_across_all_departments(): void
    {
        $deptA = $this->makeDepartment('Engineering');
        $deptB = $this->makeDepartment('Sales');
        $admin = $this->makeUser(['role' => 'admin']);
        $managerA = $this->makeUser(['role' => 'manager', 'department_id' => $deptA->id]);
        $managerB = $this->makeUser(['role' => 'manager', 'department_id' => $deptB->id]);

        $projectA = $this->makeProject($deptA, $managerA);
        $projectB = $this->makeProject($deptB, $managerB);

        $this->makeTask($projectA, $managerA, ['status' => Task::STATUS_TODO]);
        $this->makeTask($projectB, $managerB, ['status' => Task::STATUS_TODO]);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('(portals)/admin/dashboard/page')
            ->where('metrics.task_status_summary.todo.count', 2) // both departments counted
        );
    }

    public function test_manager_dashboard_task_status_summary_is_scoped_to_own_department_only(): void
    {
        $deptA = $this->makeDepartment('Engineering');
        $deptB = $this->makeDepartment('Sales');
        $managerA = $this->makeUser(['role' => 'manager', 'department_id' => $deptA->id]);
        $managerB = $this->makeUser(['role' => 'manager', 'department_id' => $deptB->id]);

        $projectA = $this->makeProject($deptA, $managerA);
        $projectB = $this->makeProject($deptB, $managerB);

        $this->makeTask($projectA, $managerA, ['status' => Task::STATUS_IN_REVIEW, 'estimated_hours' => 4]);
        $this->makeTask($projectA, $managerA, ['status' => Task::STATUS_IN_REVIEW, 'estimated_hours' => 6]);
        $this->makeTask($projectB, $managerB, ['status' => Task::STATUS_IN_REVIEW, 'estimated_hours' => 999]);

        $response = $this->actingAs($managerA)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('(portals)/manager/dashboard/page')
            ->where('metrics.task_status_summary.in_review.count', 2)
            ->where('metrics.task_status_summary.in_review.estimated_hours', fn ($value) => $value == 10)
        );
    }

    public function test_employee_dashboard_task_status_summary_is_scoped_to_assigned_tasks_only(): void
    {
        $department = $this->makeDepartment();
        $manager = $this->makeUser(['role' => 'manager', 'department_id' => $department->id]);
        $employee = $this->makeUser(['role' => 'employee', 'department_id' => $department->id]);
        $colleague = $this->makeUser(['role' => 'employee', 'department_id' => $department->id]);
        $project = $this->makeProject($department, $manager);

        $this->makeTask($project, $manager, ['status' => Task::STATUS_COMPLETED, 'assignee_id' => $employee->id]);
        $this->makeTask($project, $manager, ['status' => Task::STATUS_COMPLETED, 'assignee_id' => $colleague->id]);

        $response = $this->actingAs($employee)->get('/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('(portals)/employee/dashboard/page')
            ->where('metrics.task_status_summary.completed.count', 1) // only the employee's own task
        );
    }

    // ── Performance ──────────────────────────────────────────────────────────

    public function test_task_status_summary_and_logged_minutes_query_count_does_not_grow_with_task_count(): void
    {
        $department = $this->makeDepartment();
        $manager = $this->makeUser(['role' => 'manager', 'department_id' => $department->id]);

        $smallProject = $this->makeProject($department, $manager);
        for ($i = 0; $i < 3; $i++) {
            $employee = $this->makeUser(['role' => 'employee', 'department_id' => $department->id]);
            $task = $this->makeTask($smallProject, $manager, ['assignee_id' => $employee->id]);
            TimeLog::create([
                'task_id' => $task->id, 'user_id' => $employee->id,
                'started_at' => now(), 'ended_at' => now(), 'duration_minutes' => 10, 'entry_type' => 'manual',
            ]);
        }

        $bigProject = $this->makeProject($department, $manager);
        for ($i = 0; $i < 30; $i++) {
            $employee = $this->makeUser(['role' => 'employee', 'department_id' => $department->id]);
            $task = $this->makeTask($bigProject, $manager, ['assignee_id' => $employee->id]);
            TimeLog::create([
                'task_id' => $task->id, 'user_id' => $employee->id,
                'started_at' => now(), 'ended_at' => now(), 'duration_minutes' => 10, 'entry_type' => 'manual',
            ]);
        }

        $run = function ($project) {
            DB::flushQueryLog();
            Task::statusSummary(Task::where('project_id', $project->id));
            TimeLog::loggedMinutesByTaskStatus(Task::where('project_id', $project->id));

            return DB::getQueryLog();
        };

        DB::enableQueryLog();
        $small = $run($smallProject);
        $big = $run($bigProject);
        DB::disableQueryLog();

        $this->assertSame(
            count($small),
            count($big),
            'Expected identical query counts for 3 tasks vs 30 tasks: ' . count($small) . ' vs ' . count($big)
        );
        // Two aggregate queries total (one per helper) — proves neither loads task/time_log rows individually.
        $this->assertSame(2, count($small));
    }
}
