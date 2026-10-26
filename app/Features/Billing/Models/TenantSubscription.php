<?php

namespace App\Features\Billing\Models;

use App\Features\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A tenant's plan and billing state: what seat limits are enforced against.
 *
 * Platform-side like Tenant, so not BelongsToTenant: tenant-facing code
 * reaches it only through the signed-in user's Tenant::subscription(). A
 * billing provider will write into it from webhooks; nothing reads the
 * provider's own tables.
 */
class TenantSubscription extends Model
{
    public const STATUS_TRIALING = 'trialing';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAST_DUE = 'past_due';

    public const STATUS_CANCELED = 'canceled';

    public const STATUS_NONE = 'none';

    // tenant_id and status changes come only from billing code, never from a request.
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'seats' => 'integer',
            'trial_ends_at' => 'datetime',
            'current_period_ends_at' => 'datetime',
            'grace_ends_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function trialExpired(): bool
    {
        return $this->status === self::STATUS_TRIALING
            && ($this->trial_ends_at === null || ! $this->trial_ends_at->isFuture());
    }

    /**
     * Whether the tenant may take on another user. Lapsed tenants (past_due,
     * canceled, none, an expired trial) keep their existing users but can't
     * add any until billing is sorted out.
     */
    public function canAddSeats(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            || ($this->status === self::STATUS_TRIALING && ! $this->trialExpired());
    }
}
