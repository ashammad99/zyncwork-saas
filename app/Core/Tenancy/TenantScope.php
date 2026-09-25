<?php

namespace App\Core\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Limits every query on a tenant-owned model to the current tenant.
 *
 * Fails closed: with no tenant set the query throws instead of running
 * unfiltered. Code that genuinely needs to cross tenants must opt out
 * explicitly with ->withoutTenancy().
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Qualified so joins (e.g. time_logs ⋈ tasks) stay unambiguous.
        $builder->where(
            $model->qualifyColumn('tenant_id'),
            app(TenantContext::class)->idOrFail()
        );
    }
}
