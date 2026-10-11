<?php

namespace Tests\Feature;

use App\Core\Support\CsvCell;
use App\Features\Auth\Models\User;
use App\Features\Departments\Admin\Models\Department;
use App\Features\Projects\Admin\Models\Project;
use App\Features\TaskAdmin\Models\Task;
use App\Features\TimeLog\Models\TimeLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Text a user typed must never run as a spreadsheet formula when an export
 * is opened in Excel or similar (CSV / formula injection).
 */
class CsvFormulaInjectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_text_starting_like_a_formula_is_made_plain_text(): void
    {
        foreach (['=1+1', '+SUM(A1)', '-2+3', '@cmd', "\t=1", "\r=1"] as $value) {
            $this->assertSame("'".$value, CsvCell::safe($value), json_encode($value));
        }
    }

    public function test_ordinary_values_are_left_alone(): void
    {
        foreach (['Fix login', 'a=b', 'email@example.com', '', null, 60, -5, 1.5, true] as $value) {
            $this->assertSame($value, CsvCell::safe($value), json_encode($value));
        }

        $this->assertSame(['Plain', "'=1", 60, null], CsvCell::row(['Plain', '=1', 60, null]));
    }

    public function test_the_time_log_export_neutralises_formulas_in_user_text(): void
    {
        Carbon::setTestNow('2026-10-11 10:00:00');
        $department = Department::create(['name' => '=Dept']);
        $employee = User::create([
            'name' => '@Evil Name', 'email' => 'evil@example.com', 'password' => 'password',
            'role' => 'employee', 'is_active' => true, 'department_id' => $department->id,
        ]);
        $project = Project::create(['department_id' => $department->id, 'owner_id' => $employee->id, 'name' => '+Project', 'status' => 'active']);
        $task = Task::create([
            'project_id' => $project->id, 'created_by' => $employee->id, 'assignee_id' => $employee->id,
            'title' => '=HYPERLINK("http://evil.test","click")', 'status' => Task::STATUS_IN_PROGRESS,
        ]);
        TimeLog::create([
            'task_id' => $task->id, 'user_id' => $employee->id, 'started_at' => now()->subHour(), 'ended_at' => now(),
            'duration_minutes' => 60, 'entry_type' => 'manual', 'notes' => '-cmd|calc',
        ]);
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password', 'role' => 'admin', 'is_active' => true]);

        $csv = $this->actingAs($admin)->get('/admin/timesheets/export')->assertOk()->streamedContent();
        $rows = array_map('str_getcsv', array_filter(explode("\n", ltrim($csv, "\xEF\xBB\xBF"))));
        $row = $rows[1];

        $this->assertSame("'@Evil Name", $row[0]);
        $this->assertSame("'=Dept", $row[2]);
        $this->assertSame("'+Project", $row[3]);
        $this->assertSame("'=HYPERLINK(\"http://evil.test\",\"click\")", $row[4]);
        $this->assertSame("'-cmd|calc", $row[11]);
        // Numbers stay numbers.
        $this->assertSame('60', $row[8]);

        Carbon::setTestNow();
    }
}
