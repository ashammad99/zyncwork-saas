<?php

namespace App\Features\Departments\Admin\Services;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Core\BaseService;
use App\Features\Departments\Admin\Models\Department;

class DepartmentService extends BaseService
{
    public function paginate(int $perPage = 10): LengthAwarePaginator
    {
        return Department::with('manager')
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $data): Department
    {
        return Department::create([
            'manager_id' => $data['manager_id'] ?? null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'color' => $data['color'] ?? null,
        ]);
    }

    public function update(Department $department, array $data): Department
    {
        $department->update([
            'manager_id' => $data['manager_id'] ?? null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'color' => $data['color'] ?? null,
        ]);

        return $department->refresh();
    }

    public function delete(Department $department): void
    {
        $department->delete();
    }
}
