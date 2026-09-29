<?php

namespace App\Core\Tenancy;

use RuntimeException;

/**
 * Thrown when code would read or write tenant-owned data without a tenant,
 * or write into a tenant other than the current one. Never caught by the
 * app: it signals a bug, and failing loudly is the point.
 */
class TenantIsolationException extends RuntimeException
{
    public static function missingContext(): self
    {
        return new self('No tenant is set for this request or job; tenant-owned data cannot be accessed.');
    }

    public static function crossTenantWrite(int $current, int $target): self
    {
        return new self("Refusing to write a record for tenant {$target} while acting as tenant {$current}.");
    }

    public static function tenantReassignment(): self
    {
        return new self('tenant_id of an existing record cannot be changed.');
    }
}
