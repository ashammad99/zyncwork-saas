<?php

namespace Database\Seeders;

use App\Features\Auth\Models\User;
use App\Features\Departments\Admin\Models\Department;
use App\Features\Projects\Admin\Models\Project;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = Department::pluck('id');
        $owners = User::whereIn('role', ['admin', 'manager'])->pluck('id');

        if ($departments->isEmpty() || $owners->isEmpty()) {
            $this->command->warn('Departments or eligible owners not found.');
            return;
        }

        Project::create([
            'department_id' => $departments->random(),
            'owner_id' => $owners->random(),
            'name' => 'HR Management System',
            'description' => 'Internal system for managing employees and departments.',
            'status' => 'active',
            'deadline' => now()->addDays(30)->toDateString(),
        ]);

        Project::create([
            'department_id' => $departments->random(),
            'owner_id' => $owners->random(),
            'name' => 'Task Tracking Platform',
            'description' => 'Platform to assign and monitor team tasks.',
            'status' => 'on_hold',
            'deadline' => now()->addDays(45)->toDateString(),
        ]);

        Project::create([
            'department_id' => $departments->random(),
            'owner_id' => $owners->random(),
            'name' => 'Performance Dashboard',
            'description' => 'Dashboard for monitoring team and department performance.',
            'status' => 'archived',
            'deadline' => now()->addDays(60)->toDateString(),
        ]);
    }
}
