<?php

namespace App\Features\Billing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A plan a tenant can be on. Platform-wide, not tenant-owned.
 *
 * Pricing is per seat: a paid tenant's limit is the quantity it pays for
 * (TenantSubscription::$seats), so included_seats is null on paid plans.
 */
class Plan extends Model
{
    protected $fillable = ['key', 'name', 'included_seats', 'provider_price_id', 'is_trial', 'is_active', 'sort'];

    protected function casts(): array
    {
        return [
            'included_seats' => 'integer',
            'is_trial' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
