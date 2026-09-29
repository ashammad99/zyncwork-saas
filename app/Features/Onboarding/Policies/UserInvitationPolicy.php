<?php

namespace App\Features\Onboarding\Policies;

use App\Features\Auth\Models\User;
use App\Features\Onboarding\Models\UserInvitation;

/**
 * Only verified tenant admins manage invitations. Other tenants' invitations
 * never get here: route model binding goes through the tenant scope (404).
 */
class UserInvitationPolicy
{
    public function create(User $user): bool
    {
        return $user->role === 'admin' && $user->hasVerifiedEmail();
    }

    public function update(User $user, UserInvitation $invitation): bool
    {
        return $this->create($user) && (int) $invitation->tenant_id === (int) $user->tenant_id;
    }

    public function delete(User $user, UserInvitation $invitation): bool
    {
        return $this->update($user, $invitation);
    }
}
