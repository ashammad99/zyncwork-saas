<?php

namespace App\Features\TenantExport\Services;

use App\Core\BaseService;
use App\Features\ActivityLog\Events\ActivityLogged;
use App\Features\Auth\Models\User;
use App\Features\Billing\Models\TenantSubscription;
use App\Features\TenantExport\Exceptions\ExportNotAllowed;
use App\Features\TenantExport\Jobs\BuildTenantExport;
use App\Features\TenantExport\Models\TenantExport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * Starting and handing out tenant data exports.
 *
 * Limits: one export queued or running per tenant at a time, and at most
 * config('exports.per_day') started in any 24 hours. Both are checked under
 * a lock on the tenant's subscription row (the row SeatGuard locks too), so
 * two clicks at once can't both start one.
 */
class TenantExportService extends BaseService
{
    /** @throws ExportNotAllowed */
    public function request(User $admin): TenantExport
    {
        $export = DB::transaction(function () use ($admin) {
            TenantSubscription::where('tenant_id', $admin->tenant_id)->lockForUpdate()->first();

            if (TenantExport::active()->exists()) {
                throw new ExportNotAllowed('An export is already being prepared. You will get an email when it is ready.');
            }

            $perDay = (int) config('exports.per_day');
            if (TenantExport::where('created_at', '>', now()->subDay())->count() >= $perDay) {
                throw new ExportNotAllowed("Your organisation can start {$perDay} exports in 24 hours. Please try again later.");
            }

            $export = (new TenantExport)->forceFill([
                'requested_by' => $admin->id,
                'status' => TenantExport::STATUS_QUEUED,
            ]);
            $export->save();

            return $export;
        });

        event(new ActivityLogged($admin, 'tenant_export_requested', 'Requested a full data export.', 'security'));

        // After the commit, so the worker always finds the row.
        BuildTenantExport::dispatch($export->id);

        return $export;
    }

    /** True when the tenant may start an export now (for the billing page button). */
    public function canRequest(): bool
    {
        return ! TenantExport::active()->exists()
            && TenantExport::where('created_at', '>', now()->subDay())->count() < (int) config('exports.per_day');
    }

    /**
     * A signed download link for the admin who requested the export. It stops
     * working after config('exports.link_hours'), or when the file is deleted,
     * whichever comes first.
     */
    public function downloadUrl(TenantExport $export): string
    {
        $expires = now()->addHours((int) config('exports.link_hours'));

        return URL::temporarySignedRoute('admin.exports.download', $export->expires_at?->lt($expires) ? $export->expires_at : $expires, ['export' => $export->id]);
    }
}
