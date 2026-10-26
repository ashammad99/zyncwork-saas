<?php

namespace App\Features\Billing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A plan a tenant can be on. Platform-wide, not tenant-owned.
 *
 * Pricing is per seat: a paid tenant's limit is the quantity it pays for
 * (TenantSubscription::$seats), so included_seats is null on paid plans.
 * price_amount is per seat per interval, in minor units (cents); it is the
 * public pricing page's copy of the provider's price and must match it.
 */
class Plan extends Model
{
    public const INTERVAL_MONTH = 'month';

    public const INTERVAL_YEAR = 'year';

    protected $fillable = ['key', 'name', 'included_seats', 'price_amount', 'currency', 'interval', 'provider_price_id', 'is_trial', 'is_active', 'sort'];

    protected function casts(): array
    {
        return [
            'included_seats' => 'integer',
            'price_amount' => 'integer',
            'is_trial' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The plan every new tenant starts on. Signup (Tenant) and the public
     * pricing page both read it here, so the trial they describe is the same.
     */
    public static function trialPlan(): self
    {
        return static::where('key', config('billing.trial_plan'))->firstOrFail();
    }
}
