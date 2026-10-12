<?php

namespace App\Features\TenantExport\Jobs;

use App\Features\ActivityLog\Events\ActivityLogged;
use App\Features\TenantExport\Models\TenantExport;
use App\Features\TenantExport\Services\TenantExportBuilder;
use App\Features\TenantExport\Services\TenantExportService;
use App\Mail\GeneralMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Builds one tenant export in the background and emails the admin who asked.
 *
 * Runs as the tenant it was dispatched for (TenancyServiceProvider restores it
 * from the payload). The export is looked up through the tenant scope, so a
 * job running as any other tenant finds nothing and does nothing.
 */
class BuildTenantExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    // A failed export is recorded on its row; the admin can start another.
    public int $tries = 1;

    public int $timeout;

    public function __construct(
        public int $exportId,
    ) {
        $this->timeout = (int) config('exports.job_timeout');
    }

    public function handle(TenantExportBuilder $builder, TenantExportService $exports): void
    {
        $export = TenantExport::find($this->exportId);

        if (! $export || $export->status !== TenantExport::STATUS_QUEUED) {
            Log::warning('BuildTenantExport: export not found for this tenant, or already handled.', ['export_id' => $this->exportId]);

            return;
        }

        $export->forceFill(['status' => TenantExport::STATUS_RUNNING, 'started_at' => now()])->save();

        try {
            $result = $builder->build($export);
        } catch (Throwable $e) {
            $this->markFailed($export, $e);

            return;
        }

        $export->forceFill([
            'status' => TenantExport::STATUS_READY,
            'file_path' => $result['path'],
            'size_bytes' => $result['size'],
            'row_counts' => $result['row_counts'],
            'finished_at' => now(),
            'expires_at' => now()->addDays((int) config('exports.keep_days')),
        ])->save();

        $requester = $export->requester;
        Mail::to($requester->email)->send(new GeneralMail(
            mailSubject: __('export.ready.subject', ['app' => config('app.name')]),
            contentView: 'emails.export.ready',
            data: [
                'name' => $requester->name,
                'actionUrl' => $exports->downloadUrl($export),
                'linkHours' => (int) config('exports.link_hours'),
                'keepDays' => (int) config('exports.keep_days'),
            ]
        ));
    }

    private function markFailed(TenantExport $export, Throwable $e): void
    {
        // Reported for us; the admin only sees that it failed.
        report($e);

        $export->forceFill([
            'status' => TenantExport::STATUS_FAILED,
            'error' => mb_substr($e->getMessage(), 0, 1000),
            'finished_at' => now(),
        ])->save();

        $requester = $export->requester;
        event(new ActivityLogged($requester, 'tenant_export_failed', 'A data export could not be completed.', 'system'));

        Mail::to($requester->email)->send(new GeneralMail(
            mailSubject: __('export.failed.subject', ['app' => config('app.name')]),
            contentView: 'emails.export.failed',
            data: ['name' => $requester->name]
        ));
    }
}
