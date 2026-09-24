<?php

namespace App\Features\UserManagement\Admin\Services;

use App\Core\BaseService;
use App\Features\Auth\Models\User;
use App\Features\ActivityLog\Events\ActivityLogged;
use Illuminate\Support\Facades\Auth;

class UserService extends BaseService
{
    /**
     * Get paginated users.
     */
    public function getPaginatedUsers(array $filters = [])
    {
        $query = User::with('department');

        if (!empty($filters['search'])) {
            $query->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('email', 'like', '%' . $filters['search'] . '%');
        }

        return $query->orderBy('created_at', 'desc')->paginate(15)->withQueryString();
    }

    /**
     * Create a new user.
     */
    public function createUser(array $data): User
    {

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => \Illuminate\Support\Facades\Hash::make($data['password']),
            'role' => $data['role'],
            'is_active' => $data['is_active'],
            'daily_capacity_hours' => $data['daily_capacity_hours'],
            'department_id'        => $data['department_id'] ?? null,

        ]);

        event(new ActivityLogged(Auth::user(), 'user_created', "Created new user account: {$user->email}", 'system'));

        return $user;
    }

    /**
     * Update user details and privileges.
     */
    public function updateUser(User $user, array $data): User
    {
        $wasActive = $user->is_active;

        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'role' => $data['role'],
            'is_active' => $data['is_active'],
            'daily_capacity_hours' => $data['daily_capacity_hours'],
            'department_id'        => $data['department_id'] ?? null,


        ]);

        if ($wasActive && !$user->is_active) {
            event(new ActivityLogged(Auth::user(), 'user_suspended', "Suspended user account: {$user->email}", 'security'));
        } elseif (!$wasActive && $user->is_active) {
            event(new ActivityLogged(Auth::user(), 'user_activated', "Activated user account: {$user->email}", 'system'));
        } else {
            event(new ActivityLogged(Auth::user(), 'user_updated', "Updated profile/privileges for user: {$user->email}", 'system'));
        }

        return $user;
    }
}
