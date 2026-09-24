<?php

namespace Tests\Feature;

use App\Features\Auth\Models\User;
use App\Features\Departments\Admin\Models\Department;
use App\Features\Projects\Admin\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SeedingAndFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_factory_creates_a_project(): void
    {
        $department = Department::create(['name' => 'Engineering']);
        $owner = User::create([
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => Hash::make('password'),
            'role' => 'manager',
            'is_active' => true,
            'department_id' => $department->id,
        ]);

        $explicit = Project::factory()->create([
            'department_id' => $department->id,
            'owner_id' => $owner->id,
        ]);
        $defaulted = Project::factory()->create();

        $this->assertInstanceOf(Project::class, $explicit);
        $this->assertSame($department->id, $explicit->department_id);
        $this->assertSame($department->id, $defaulted->department_id);
        $this->assertSame($owner->id, $defaulted->owner_id);
    }

    public function test_seeded_manager_has_a_department_and_can_open_a_seeded_project(): void
    {
        $this->seed();

        $manager = User::where('email', 'manager@example.com')->firstOrFail();
        $employee = User::where('email', 'emp@example.com')->firstOrFail();
        $admin = User::where('email', 'admin@example.com')->firstOrFail();

        $this->assertNotNull($manager->department_id);
        $this->assertSame($manager->department_id, $employee->department_id);
        $this->assertNull($admin->department_id);
        $this->assertSame($manager->id, Department::find($manager->department_id)->manager_id);

        $this->assertSame(3, Project::count());
        $project = Project::firstOrFail();

        $this->actingAs($manager)->get("/manager/projects/{$project->id}")->assertOk();
    }

    public function test_no_route_uses_the_noop_verified_middleware(): void
    {
        // User does not implement MustVerifyEmail, so `verified` never blocked anyone.
        // Re-add it together with real email verification (Phase 1C).
        $withVerified = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => in_array('verified', $route->gatherMiddleware(), true))
            ->map(fn ($route) => $route->uri())
            ->values()
            ->all();

        $this->assertSame([], $withVerified);
    }
}
