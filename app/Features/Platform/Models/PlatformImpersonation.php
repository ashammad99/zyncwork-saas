<?php

namespace App\Features\Platform\Models;

use App\Features\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One support session: a platform admin acting as a tenant user.
 *
 * Holds the live state checked on every tenant request (EnforceImpersonation);
 * the history of what happened is in platform_audit_logs.
 */
class PlatformImpersonation extends Model
{
    public const END_STOPPED = 'stopped';

    public const END_EXPIRED = 'expired';

    public const END_LOGGED_OUT = 'logged_out';

    public const END_REPLACED = 'replaced';

    protected $fillable = [
        'platform_admin_id',
        'tenant_id',
        'target_user_id',
        'reason',
        'token_hash',
        'token_expires_at',
        'ip_address',
        'user_agent',
    ];

    protected $hidden = [
        'token_hash',
    ];

    protected function casts(): array
    {
        return [
            'token_expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'expires_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function platformAdmin(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Handed off, not ended, and within its time limit. */
    public function isLive(): bool
    {
        return $this->consumed_at !== null
            && $this->ended_at === null
            && $this->expires_at !== null
            && $this->expires_at->isFuture();
    }
}
