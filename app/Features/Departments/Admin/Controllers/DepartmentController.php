<?php

namespace App\Features\Departments\Admin\Controllers;

use App\Core\BaseController;
use App\Features\Auth\Models\User;
use App\Features\Departments\Admin\Models\Department;
use App\Features\Departments\Admin\Requests\StoreDepartmentRequest;
use App\Features\Departments\Admin\Requests\UpdateDepartmentRequest;
use Inertia\Inertia;
use App\Features\Departments\Admin\Services\DepartmentService;
class DepartmentController extends BaseController
{
    public function __construct(
        protected DepartmentService $departmentService
    ) {}

    public function index()
    {
        return Inertia::render('(portals)/admin/departments/page', [
            'departments' => $this->departmentService->paginate(10),
            'managers' => User::select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    public function store(StoreDepartmentRequest $request)
    {
        $this->departmentService->create($request->validated());

        return back()->with('success', 'Department created successfully.');
    }

    public function update(UpdateDepartmentRequest $request, Department $department)
    {
        $this->departmentService->update($department, $request->validated());

        return back()->with('success', 'Department updated successfully.');
    }

    public function destroy(Department $department)
    {
        $this->departmentService->delete($department);

        return back()->with('success', 'Department deleted successfully.');
    }
}
