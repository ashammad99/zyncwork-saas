<?php

namespace App\Features\Tenancy\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A customer organisation. Not tenant-owned itself: tenant users can never
 * query this table through a scope, and only the platform side manages it.
 */
class Tenant extends Model
{
    protected $fillable = ['name'];

    // suspended_at / suspended_reason are set only by TenantSuspensionService.
    protected function casts(): array
    {
        return [
            'suspended_at' => 'datetime',
        ];
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }
}
