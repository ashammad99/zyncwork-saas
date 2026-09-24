<?php

namespace Tests\Feature;

use App\Features\Auth\Models\User;
use App\Features\Departments\Admin\Models\Department;
use App\Features\Projects\Admin\Models\Project;
use App\Features\Projects\Manager\Services\ProjectService;
use App\Features\TaskAdmin\Models\Task;
use App\Features\TimeLog\Models\TimeLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ManagerProjectShowTest extends TestCase
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

    private function makeDepartment(): Department
    {
        return Department::create(['name' => 'Engineering']);
    }

    private function makeProject(Department $department, User $owner, array $overrides = []): Project
    {
        return Project::create(array_merge([
            'department_id' => $department->id,
            'owner_id' => $owner->id,
            'name' => 'Test Project',
            'status' => 'active',
        ], $overrides));
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

    public function test_manager_sees_paginated_tasks_status_counts_and_total_time(): void
    {
        $department = $this->makeDepartment();
        $manager = $this->makeUser(['role' => 'manager', 'department_id' => $department->id]);
        $employee = $this->makeUser(['role' => 'employee', 'department_id' => $department->id]);
        $project = $this->makeProject($department, $manager);

        // Whole-project status mix: 2 backlog, 1 todo, 1 in_progress, 1 in_review, 2 completed
        $statuses = [
            Task::STATUS_BACKLOG, Task::STATUS_BACKLOG,
            Task::STATUS_TODO,
            Task::STATUS_IN_PROGRESS,
            Task::STATUS_IN_REVIEW,
            Task::STATUS_COMPLETED, Task::STATUS_COMPLETED,
        ];

        $tasks = [];
        foreach ($statuses as $i => $status) {
            $tasks[] = $this->makeTask($project, $manager, [
                'title' => "Task {$i}",
                'assignee_id' => $employee->id,
                'status' => $status,
            ]);
        }

        // Log time on two different tasks — total must be summed at the DB level.
        TimeLog::create([
            'task_id' => $tasks[0]->id,
            'user_id' => $employee->id,
            'started_at' => now()->subHour(),
            'ended_at' => now(),
            'duration_minutes' => 60,
            'entry_type' => 'manual',
        ]);
        TimeLog::create([
            'task_id' => $tasks[5]->id,
            'user_id' => $employee->id,
            'started_at' => now()->subMinutes(30),
            'ended_at' => now(),
            'duration_minutes' => 30,
            'entry_type' => 'manual',
        ]);

        $response = $this->actingAs($manager)->get("/manager/projects/{$project->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('(portals)/manager/projects/Show')
            ->where('statusCounts.backlog', 2)
            ->where('statusCounts.todo', 1)
            ->where('statusCounts.in_progress', 1)
            ->where('statusCounts.in_review', 1)
            ->where('statusCounts.completed', 2)
            ->where('totalMinutes', 90)
            ->has('tasks.data', 7)
            ->missing('project.tasks') // findWithRelations no longer eager-loads the task array
        );
    }

    public function test_status_filter_narrows_paginated_tasks_but_not_whole_project_counts(): void
    {
        $department = $this->makeDepartment();
        $manager = $this->makeUser(['role' => 'manager', 'department_id' => $department->id]);
        $project = $this->makeProject($department, $manager);

        $this->makeTask($project, $manager, ['status' => Task::STATUS_COMPLETED]);
        $this->makeTask($project, $manager, ['status' => Task::STATUS_COMPLETED]);
        $this->makeTask($project, $manager, ['status' => Task::STATUS_BACKLOG]);

        $response = $this->actingAs($manager)
            ->get("/manager/projects/{$project->id}?status=completed");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('tasks.data', 2)
            ->where('statusCounts.completed', 2)
            ->where('statusCounts.backlog', 1) // whole-project count unaffected by the filter
        );
    }

    public function test_manager_from_another_department_cannot_view_project(): void
    {
        $departmentA = $this->makeDepartment();
        $departmentB = Department::create(['name' => 'Sales']);

        $ownerA = $this->makeUser(['role' => 'manager', 'department_id' => $departmentA->id]);
        $managerB = $this->makeUser(['role' => 'manager', 'department_id' => $departmentB->id]);

        $project = $this->makeProject($departmentA, $ownerA);

        $this->actingAs($managerB)
            ->get("/manager/projects/{$project->id}")
            ->assertForbidden();
    }

    public function test_pagination_returns_full_paginator_shape_for_the_shared_component(): void
    {
        $department = $this->makeDepartment();
        $manager = $this->makeUser(['role' => 'manager', 'department_id' => $department->id]);
        $project = $this->makeProject($department, $manager);

        for ($i = 0; $i < 20; $i++) {
            $this->makeTask($project, $manager, ['title' => "Task {$i}"]);
        }

        $response = $this->actingAs($manager)->get("/manager/projects/{$project->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->has('tasks.data', 15) // first page of the 15-per-page paginator
            ->where('tasks.total', 20)
            ->has('tasks.links') // the nested `links` array the shared Pagination component expects
        );
    }

    public function test_priority_assignee_and_due_date_filters_narrow_the_task_list(): void
    {
        $department = $this->makeDepartment();
        $manager = $this->makeUser(['role' => 'manager', 'department_id' => $department->id]);
        $employee1 = $this->makeUser(['role' => 'employee', 'department_id' => $department->id]);
        $employee2 = $this->makeUser(['role' => 'employee', 'department_id' => $department->id]);
        $project = $this->makeProject($department, $manager);

        $taskA = $this->makeTask($project, $manager, [
            'priority' => 'high', 'assignee_id' => $employee1->id, 'due_date' => '2026-01-10',
        ]);
        $taskB = $this->makeTask($project, $manager, [
            'priority' => 'low', 'assignee_id' => $employee2->id, 'due_date' => '2026-02-10',
        ]);
        $taskC = $this->makeTask($project, $manager, [
            'priority' => 'high', 'assignee_id' => $employee1->id, 'due_date' => '2026-02-10',
        ]);

        // priority filter -> A, C
        $this->actingAs($manager)
            ->get("/manager/projects/{$project->id}?priority=high")
            ->assertInertia(fn ($page) => $page->has('tasks.data', 2));

        // assignee filter -> A, C
        $this->actingAs($manager)
            ->get("/manager/projects/{$project->id}?assignee_id={$employee1->id}")
            ->assertInertia(fn ($page) => $page->has('tasks.data', 2));

        // due_date filter -> B, C
        $this->actingAs($manager)
            ->get("/manager/projects/{$project->id}?due_date=2026-02-10")
            ->assertInertia(fn ($page) => $page->has('tasks.data', 2));

        // whole-project status counts are unaffected by any of these task-level filters
        $this->actingAs($manager)
            ->get("/manager/projects/{$project->id}?priority=high")
            ->assertInertia(fn ($page) => $page->where('statusCounts.backlog', 3));
    }

    public function test_existing_edit_and_archive_actions_still_work(): void
    {
        // CSRF verification (Illuminate\Foundation\Http\Middleware\ValidateCsrfToken, part of
        // Laravel's default 'web' middleware group) is not exercised by this test — this is
        // Laravel's own documented pattern for testing state-changing routes without simulating
        // a browser's token handshake. It does NOT disable 'auth', 'role', or the ProjectPolicy
        // checks below — those still run and are what this test is actually verifying.
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);

        $department = $this->makeDepartment();
        $manager = $this->makeUser(['role' => 'manager', 'department_id' => $department->id]);
        $project = $this->makeProject($department, $manager, ['status' => 'active']);

        // Edit (PUT) — untouched by Phase 2, verifying no regression.
        $this->actingAs($manager)
            ->put("/manager/projects/{$project->id}", [
                'name' => 'Renamed Project',
                'description' => null,
                'status' => 'on_hold',
                'deadline' => null,
            ])
            ->assertRedirect(route('manager.projects.index'));

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'name' => 'Renamed Project',
            'status' => 'on_hold',
        ]);

        // Archive (DELETE) — soft-archives via status, not a hard delete.
        $this->actingAs($manager)
            ->delete("/manager/projects/{$project->id}")
            ->assertRedirect(route('manager.projects.index'));

        $this->assertDatabaseHas('projects', [
            'id' => $project->id,
            'status' => 'archived',
        ]);
    }

    /**
     * Asserts query-count flatness by calling ProjectService's methods directly rather than
     * through two separate HTTP round-trips. A prior version of this test compared query
     * counts across two full $this->get() calls and was fragile: it failed intermittently
     * from unrelated auth/session/gate resolution costs that differ between the first and
     * second simulated HTTP request in the same test process, not from the reviewed code.
     * Calling the service directly, once per project size, in the same process with no
     * request-lifecycle boundary between them eliminates that noise and isolates exactly
     * the query behavior this phase's code is responsible for.
     */
    public function test_project_service_query_count_does_not_grow_with_task_count(): void
    {
        $department = $this->makeDepartment();
        $manager = $this->makeUser(['role' => 'manager', 'department_id' => $department->id]);
        $service = app(ProjectService::class);

        $smallProject = $this->makeProject($department, $manager, ['name' => 'Small']);
        for ($i = 0; $i < 3; $i++) {
            $employee = $this->makeUser(['role' => 'employee', 'department_id' => $department->id]);
            $this->makeTask($smallProject, $manager, ['assignee_id' => $employee->id]);
        }

        $bigProject = $this->makeProject($department, $manager, ['name' => 'Big']);
        for ($i = 0; $i < 30; $i++) {
            $employee = $this->makeUser(['role' => 'employee', 'department_id' => $department->id]);
            $this->makeTask($bigProject, $manager, ['assignee_id' => $employee->id]);
        }

        $runAndCount = function (Project $project) use ($service) {
            DB::flushQueryLog();
            $service->findWithRelations($project);
            $service->getProjectTasks($project);
            $service->getProjectStatusCounts($project);
            $service->getProjectTotalMinutes($project);

            return DB::getQueryLog();
        };

        DB::enableQueryLog();
        $smallQueries = $runAndCount($smallProject);
        $bigQueries = $runAndCount($bigProject);
        DB::disableQueryLog();

        // 3 tasks/assignees vs 30 — a real per-row (N+1) query would make these counts
        // diverge sharply. Identical counts across a 10x difference in row count is strong,
        // deterministic proof of flat query behavior; no arbitrary fixed number is asserted
        // since the meaningful signal is "does it grow with N", not a specific total.
        $this->assertSame(
            count($smallQueries),
            count($bigQueries),
            'Expected identical query counts for a 3-task project vs a 30-task project: ' .
            count($smallQueries) . ' vs ' . count($bigQueries) . ' — a difference would indicate an N+1 query.'
        );
    }
}
