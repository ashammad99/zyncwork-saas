<?php

namespace Tests\Feature;

use App\Features\Auth\Models\User;
use App\Features\Departments\Admin\Models\Department;
use App\Features\Projects\Admin\Models\Project;
use App\Features\TaskAdmin\Models\Task;
use App\Features\TimeLog\Models\TimeLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ManagerTimesheetDepartmentScopeTest extends TestCase
{
    use RefreshDatabase;

    private Department $deptA;

    private Department $deptB;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-15 10:00:00');

        $this->deptA = Department::create(['name' => 'Alpha']);
        $this->deptB = Department::create(['name' => 'Beta']);

        $this->makeLog($this->deptA, 'log-in-alpha');
        $this->makeLog($this->deptB, 'log-in-beta');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Test User',
            'email' => uniqid('user_').'@example.com',
            'password' => Hash::make('password'),
            'role' => 'employee',
            'is_active' => true,
        ], $overrides));
    }

    private function makeLog(Department $department, string $notes): void
    {
        $employee = $this->makeUser(['department_id' => $department->id]);
        $project = Project::create([
            'department_id' => $department->id,
            'owner_id' => $employee->id,
            'name' => "Project {$department->name}",
            'status' => 'active',
        ]);
        $task = Task::create([
            'project_id' => $project->id,
            'created_by' => $employee->id,
            'assignee_id' => $employee->id,
            'title' => "Task {$department->name}",
            'status' => Task::STATUS_IN_PROGRESS,
        ]);
        TimeLog::create([
            'task_id' => $task->id,
            'user_id' => $employee->id,
            'started_at' => now()->subHour(),
            'ended_at' => now(),
            'duration_minutes' => 60,
            'entry_type' => 'manual',
            'notes' => $notes,
        ]);
    }

    private function csvFor(User $user, string $url): string
    {
        $response = $this->actingAs($user)->get($url);
        $response->assertOk();

        return $response->streamedContent();
    }

    public function test_manager_csv_is_clamped_to_own_department_even_with_department_param(): void
    {
        $manager = $this->makeUser(['role' => 'manager', 'department_id' => $this->deptA->id]);

        $csv = $this->csvFor($manager, "/manager/timesheets/export?department_id={$this->deptB->id}");

        $this->assertStringContainsString('log-in-alpha', $csv);
        $this->assertStringNotContainsString('log-in-beta', $csv);
    }

    public function test_other_department_manager_only_sees_their_own_department(): void
    {
        $manager = $this->makeUser(['role' => 'manager', 'department_id' => $this->deptB->id]);

        $csv = $this->csvFor($manager, '/manager/timesheets/export');

        $this->assertStringContainsString('log-in-beta', $csv);
        $this->assertStringNotContainsString('log-in-alpha', $csv);
    }

    public function test_admin_export_still_covers_all_departments(): void
    {
        $admin = $this->makeUser(['role' => 'admin']);

        $csv = $this->csvFor($admin, '/admin/timesheets/export');

        $this->assertStringContainsString('log-in-alpha', $csv);
        $this->assertStringContainsString('log-in-beta', $csv);
    }

    public function test_manager_without_department_cannot_export_or_list(): void
    {
        $manager = $this->makeUser(['role' => 'manager', 'department_id' => null]);

        $this->actingAs($manager)->get('/manager/timesheets/export')->assertForbidden();
        $this->actingAs($manager)
            ->get('/manager/timesheets/export-pdf?date_from=2026-09-01&date_to=2026-09-30')
            ->assertForbidden();
        $this->actingAs($manager)->get('/manager/timesheets')->assertForbidden();
    }

    public function test_employee_cannot_open_manager_timesheet_list(): void
    {
        $employee = $this->makeUser(['department_id' => $this->deptA->id]);

        $this->actingAs($employee)->get('/manager/timesheets')->assertForbidden();
    }

    public function test_manager_index_lists_only_own_department_logs(): void
    {
        $manager = $this->makeUser(['role' => 'manager', 'department_id' => $this->deptA->id]);

        $this->actingAs($manager)->get('/manager/timesheets')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('time_logs.data', 1)
                ->where('time_logs.data.0.notes', 'log-in-alpha')
            );
    }
}
