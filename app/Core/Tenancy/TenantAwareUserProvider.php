<?php

namespace App\Core\Tenancy;

use Illuminate\Auth\EloquentUserProvider;

/**
 * Loads users for the web guard before any tenant is known.
 *
 * Login, "remember me", session restore and password reset all look a user up
 * by id, email or token first; the tenant is then taken from that user.
 * Emails are unique across the whole platform, so this can only ever
 * return the one account being authenticated.
 */
class TenantAwareUserProvider extends EloquentUserProvider
{
    protected function newModelQuery($model = null)
    {
        return parent::newModelQuery($model)->withoutGlobalScope(TenantScope::class);
    }
}
