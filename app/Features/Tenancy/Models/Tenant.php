<?php

namespace App\Features\Tenancy\Models;

use App\Features\Billing\Models\Plan;
use App\Features\Billing\Models\TenantSubscription;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A customer organisation. Not tenant-owned itself: tenant users can never
 * query this table through a scope, and only the platform side manages it.
 */
class Tenant extends Model
{
    protected $fillable = ['name'];

    /**
     * Every tenant starts on the trial, however it was created (signup,
     * seeder, tests), so SeatGuard always finds a subscription row.
     */
    protected static function booted(): void
    {
        static::created(function (Tenant $tenant) {
            $trial = Plan::where('key', config('billing.trial_plan'))->firstOrFail();

            (new TenantSubscription)->forceFill([
                'tenant_id' => $tenant->id,
                'plan_id' => $trial->id,
                'status' => TenantSubscription::STATUS_TRIALING,
                'seats' => $trial->included_seats,
                'trial_ends_at' => now()->addDays((int) config('billing.trial_days')),
            ])->save();
        });
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(TenantSubscription::class);
    }

    // suspended_at / suspended_reason are set only by TenantSuspensionService,
    // and by ReleaseUnverifiedSignupsCommand for signups that never verified.
    // signup_expires_at is set by SignupService and cleared on first verification.
    // billing_email is set by SignupService (the first admin's email).
    protected function casts(): array
    {
        return [
            'suspended_at' => 'datetime',
            'signup_expires_at' => 'datetime',
        ];
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }
}
