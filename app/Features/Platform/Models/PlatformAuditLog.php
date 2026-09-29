<?php

namespace App\Features\Platform\Models;

use App\Features\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Append-only record of everything done on the platform side.
 *
 * Not tenant-owned and never shown to tenants; a tenant sees a one-line
 * summary in its own activity log instead (PlatformAuditLogger).
 */
class PlatformAuditLog extends Model
{
    public const LOGIN = 'login';

    public const LOGIN_FAILED = 'login_failed';

    public const LOGOUT = 'logout';

    public const TWO_FACTOR_FAILED = 'two_factor.failed';

    public const TWO_FACTOR_ENABLED = 'two_factor.enabled';

    public const TWO_FACTOR_RECOVERY_USED = 'two_factor.recovery_used';

    public const TWO_FACTOR_RESET = 'two_factor.reset';

    public const HEALTH_VIEWED = 'health.viewed';

    public const CACHE_CLEARED = 'cache.cleared';

    public const BILLING_VIEWED = 'billing.viewed';

    public const TENANT_VIEWED = 'tenant.viewed';

    public const TENANT_SUSPENDED = 'tenant.suspended';

    public const TENANT_UNSUSPENDED = 'tenant.unsuspended';

    public const IMPERSONATION_REQUESTED = 'impersonation.requested';

    public const IMPERSONATION_STARTED = 'impersonation.started';

    public const IMPERSONATION_ACTION = 'impersonation.action';

    public const IMPERSONATION_ENDED = 'impersonation.ended';

    public const UPDATED_AT = null;

    protected $fillable = [
        'platform_admin_id',
        'action',
        'tenant_id',
        'target_user_id',
        'impersonation_id',
        'metadata',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Platform audit log entries cannot be changed.'));
        static::deleting(fn () => throw new LogicException('Platform audit log entries cannot be deleted.'));
    }

    public function platformAdmin(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
