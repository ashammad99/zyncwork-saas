<?php

namespace App\Features\Billing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A billing email that was sent to a tenant (see BillingNotifier). Platform-
 * side like TenantSubscription, so not BelongsToTenant.
 */
class TenantBillingNotice extends Model
{
    public const KIND_TRIAL_ENDS_3_DAYS = 'trial_ends_3d';

    public const KIND_TRIAL_ENDS_1_DAY = 'trial_ends_1d';

    public const KIND_TRIAL_EXPIRED = 'trial_expired';

    public const KIND_SEAT_LIMIT = 'seat_limit';

    public const KIND_INVITATION_BLOCKED = 'invitation_blocked';

    public $timestamps = false;

    // Written only by BillingNotifier.
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }
}
