<?php

namespace Tests\Feature;

use App\Features\Auth\Models\User;
use App\Features\Departments\Admin\Models\Department;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ManagerTimesheetPdfExportTest extends TestCase
{
    use RefreshDatabase;

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

    private function pdfUrl(): string
    {
        return '/manager/timesheets/export-pdf?date_from=2026-09-01&date_to=2026-09-30';
    }

    public function test_route_is_mounted_at_the_url_the_manager_page_links_to(): void
    {
        $this->assertSame(
            url('/manager/timesheets/export-pdf'),
            route('manager.timesheets.exportPdf')
        );
    }

    public function test_manager_can_download_pdf(): void
    {
        $department = Department::create(['name' => 'Engineering']);
        $manager = $this->makeUser(['role' => 'manager', 'department_id' => $department->id]);

        $response = $this->actingAs($manager)->get($this->pdfUrl());

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_employee_cannot_download_manager_pdf(): void
    {
        $employee = $this->makeUser();

        $this->actingAs($employee)->get($this->pdfUrl())->assertForbidden();
    }

    public function test_admin_cannot_use_manager_pdf_route(): void
    {
        $admin = $this->makeUser(['role' => 'admin']);

        $this->actingAs($admin)->get($this->pdfUrl())->assertForbidden();
    }
}
