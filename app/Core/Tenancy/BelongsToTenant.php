<?php

namespace App\Core\Tenancy;

use App\Features\Tenancy\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Marks a model as tenant-owned.
 *
 * - Reads: TenantScope filters every query to the current tenant (fails closed).
 * - Creates: tenant_id is filled from the current tenant and must match it.
 * - Updates: tenant_id can never change.
 *
 * tenant_id is deliberately not fillable; it only ever comes from the context.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function (Model $model) {
            $current = app(TenantContext::class)->idOrFail();

            if ($model->tenant_id !== null && (int) $model->tenant_id !== $current) {
                throw TenantIsolationException::crossTenantWrite($current, (int) $model->tenant_id);
            }

            $model->tenant_id = $current;
        });

        static::updating(function (Model $model) {
            if ($model->isDirty('tenant_id')) {
                throw TenantIsolationException::tenantReassignment();
            }
        });
    }

    /**
     * Fill tenant_id on construction as well, so it is set even when model
     * events are disabled (e.g. seeders using WithoutModelEvents). The
     * NOT NULL column is the backstop when no tenant is set at all.
     */
    public function initializeBelongsToTenant(): void
    {
        $tenantId = app(TenantContext::class)->id();

        if ($tenantId !== null && ! array_key_exists('tenant_id', $this->attributes)) {
            $this->attributes['tenant_id'] = $tenantId;
        }
    }

    /**
     * Queued jobs restore serialized models through this query, and Laravel
     * builds it without any global scopes. Keep the tenant scope here, or a
     * job running as one tenant could load (and act on) another tenant's rows.
     */
    public function newQueryForRestoration($ids)
    {
        return parent::newQueryForRestoration($ids)->withGlobalScope(TenantScope::class, new TenantScope);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Explicit opt-out of tenant filtering for this query only. */
    public function scopeWithoutTenancy(Builder $query): Builder
    {
        return $query->withoutGlobalScope(TenantScope::class);
    }
}
