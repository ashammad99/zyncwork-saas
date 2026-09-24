<?php

namespace Tests\Feature;

use App\Features\Auth\Models\User;
use App\Features\Departments\Admin\Models\Department;
use App\Features\Projects\Admin\Models\Project;
use App\Features\TaskAdmin\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminProjectShowTest extends TestCase
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

    private function makeProject(Department $department, User $owner): Project
    {
        return Project::create([
            'department_id' => $department->id,
            'owner_id' => $owner->id,
            'name' => 'Test Project',
            'status' => 'active',
        ]);
    }

    public function test_admin_route_resolves_and_can_view_any_project_regardless_of_department(): void
    {
        $this->assertSame(
            '/admin/projects/1',
            route('admin.projects.show', 1, absolute: false)
        );

        $department = $this->makeDepartment();
        $admin = $this->makeUser(['role' => 'admin']);
        $owner = $this->makeUser(['role' => 'manager', 'department_id' => $department->id]);
        $project = $this->makeProject($department, $owner);

        Task::create([
            'project_id' => $project->id,
            'created_by' => $owner->id,
            'title' => 'Task 1',
            'status' => Task::STATUS_TODO,
        ]);

        $response = $this->actingAs($admin)->get("/admin/projects/{$project->id}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('(portals)/manager/projects/Show')
            ->where('role', 'admin')
            ->where('statusCounts.todo', 1)
            ->has('tasks.data', 1)
        );
    }

    public function test_manager_outside_the_projects_department_is_still_forbidden_via_admin_route(): void
    {
        $departmentA = $this->makeDepartment();
        $departmentB = Department::create(['name' => 'Sales']);

        $owner = $this->makeUser(['role' => 'manager', 'department_id' => $departmentA->id]);
        $outsideManager = $this->makeUser(['role' => 'manager', 'department_id' => $departmentB->id]);

        $project = $this->makeProject($departmentA, $owner);

        // Same ProjectPolicy enforced regardless of which route (admin or manager) is used.
        $this->actingAs($outsideManager)
            ->get("/admin/projects/{$project->id}")
            ->assertForbidden();
    }
}
