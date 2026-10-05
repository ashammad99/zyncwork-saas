<?php

namespace App\Features\Projects\Manager\Policies;

use App\Features\Auth\Models\User;
use App\Features\Projects\Admin\Models\Project;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProjectPolicy
{
    use HandlesAuthorization;

    /**
     * Admin bypasses all checks.
     */
    public function before(User $user): ?bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->role === 'manager' && $user->department_id !== null;
    }

    public function view(User $user, Project $project): bool
    {
        return $user->role === 'manager'
            && $user->department_id === $project->department_id;
    }

    public function create(User $user): bool
    {
        return $user->role === 'manager' && $user->department_id !== null;
    }

    public function update(User $user, Project $project): bool
    {
        return $user->role === 'manager'
            && $user->department_id === $project->department_id;
    }

    public function delete(User $user, Project $project): bool
    {
        return $user->role === 'manager'
            && $user->department_id === $project->department_id;
    }
}
