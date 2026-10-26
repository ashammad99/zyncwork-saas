<?php

namespace App\Features\Projects\Admin\Services;

use App\Core\BaseService;
use App\Features\Projects\Admin\Models\Project;

class ProjectService extends BaseService
{
    public function paginate(int $perPage = 10, array $filters = [])
    {
        return Project::query()
            ->with(['department', 'owner', 'members'])
            ->when(! empty($filters['status']), function ($query) use ($filters) {
                $query->where('status', $filters['status']);
            })
            ->when(! empty($filters['department_id']), function ($query) use ($filters) {
                $query->where('department_id', $filters['department_id']);
            })
            ->when(! empty($filters['owner_id']), function ($query) use ($filters) {
                $query->where('owner_id', $filters['owner_id']);
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function store(array $data): Project
    {
        $memberIds = $data['member_ids'] ?? [];

        unset($data['member_ids']);

        $project = Project::create($data);

        $project->members()->sync(
            collect($memberIds)->mapWithKeys(fn ($id) => [
                $id => [
                    'role' => 'contributor',
                    'joined_at' => now(),
                ],
            ])->toArray()
        );

        return $project;
    }

    public function update(Project $project, array $data): Project
    {
        $memberIds = $data['member_ids'] ?? [];

        unset($data['member_ids']);

        $project->update($data);

        $project->members()->sync(
            collect($memberIds)->mapWithKeys(fn ($id) => [
                $id => [
                    'role' => 'contributor',
                    'joined_at' => now(),
                ],
            ])->toArray()
        );

        return $project;
    }

    public function delete(Project $project): void
    {
        $project->delete();
    }
}
