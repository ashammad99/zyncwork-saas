<?php

namespace App\Features\SystemSettings\Admin\Policies;

use App\Features\Auth\Models\User;

class SettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role === 'admin';
    }

    public function update(User $user): bool
    {
        return $user->role === 'admin';
    }
}
