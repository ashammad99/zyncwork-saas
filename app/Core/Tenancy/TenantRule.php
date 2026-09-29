<?php

namespace App\Core\Tenancy;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\Unique;

/**
 * exists/unique rules limited to the current tenant.
 *
 * Laravel's database rules query tables directly, so model global scopes do
 * not apply to them: a plain 'exists:departments,id' accepts another
 * tenant's department. Use these for every tenant-owned table.
 *
 * users.email is the exception: it is unique across the whole platform, so
 * keep plain 'unique:users,email' for it.
 */
class TenantRule
{
    public static function exists(string $table, string $column = 'id'): Exists
    {
        return Rule::exists($table, $column)
            ->where('tenant_id', app(TenantContext::class)->idOrFail());
    }

    public static function unique(string $table, string $column = 'NULL'): Unique
    {
        return Rule::unique($table, $column)
            ->where('tenant_id', app(TenantContext::class)->idOrFail());
    }
}
