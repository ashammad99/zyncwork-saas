<?php

namespace Tests\Feature;

use App\Features\Auth\Models\User;
use App\Features\Departments\Admin\Models\Department;
use App\Features\Projects\Admin\Models\Project;
use App\Features\TaskAdmin\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EmployeeTaskListOrderingTest extends TestCase
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

    public function test_employee_task_list_loads_on_sqlite_and_orders_by_status_then_priority(): void
    {
        $department = Department::create(['name' => 'Engineering']);
        $manager = $this->makeUser(['role' => 'manager', 'department_id' => $department->id]);
        $employee = $this->makeUser(['department_id' => $department->id]);
        $other = $this->makeUser(['department_id' => $department->id]);
        $project = Project::create([
            'department_id' => $department->id,
            'owner_id' => $manager->id,
            'name' => 'Test Project',
            'status' => 'active',
        ]);

        $make = fn (string $title, ?string $status, ?string $priority, User $assignee = null) => Task::create([
            'project_id' => $project->id,
            'created_by' => $manager->id,
            'assignee_id' => ($assignee ?? $employee)->id,
            'title' => $title,
            'status' => $status,
            'priority' => $priority,
        ]);

        // Created in scrambled order on purpose.
        $make('completed-urgent', Task::STATUS_COMPLETED, 'urgent');
        $make('backlog-high', Task::STATUS_BACKLOG, 'high');
        $make('in_progress-low', Task::STATUS_IN_PROGRESS, 'low');
        $make('todo-medium', Task::STATUS_TODO, 'medium');
        $make('in_progress-urgent', Task::STATUS_IN_PROGRESS, 'urgent');
        $make('in_review-high', Task::STATUS_IN_REVIEW, 'high');
        $make('in_progress-none', Task::STATUS_IN_PROGRESS, null);
        $make('not-mine', Task::STATUS_IN_PROGRESS, 'urgent', $other);

        $response = $this->actingAs($employee)->get('/employee/tasks');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('(portals)/employee/tasks/Index')
            ->has('tasks.data', 7)
            ->where('tasks.data', fn ($rows) => collect($rows)->pluck('title')->all() === [
                // NULL priority sorts first within its status, matching the old FIELD() behaviour.
                'in_progress-none',
                'in_progress-urgent',
                'in_progress-low',
                'in_review-high',
                'todo-medium',
                'backlog-high',
                'completed-urgent',
            ])
        );
    }
}
