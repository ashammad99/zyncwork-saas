<?php

namespace App\Console\Commands;

use App\Features\TenantExport\Models\TenantExport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes export files once they have been kept config('exports.keep_days'),
 * and marks exports stuck in queued/running for longer than
 * config('exports.stale_minutes') as failed (a crashed worker would otherwise
 * block the tenant's next export for good).
 *
 * Runs across all tenants, so it opts out of the tenant scope explicitly.
 */
class PruneTenantExportsCommand extends Command
{
    protected $signature = 'exports:prune';

    protected $description = 'Delete expired tenant export files and fail stuck exports';

    public function handle(): int
    {
        $deleted = 0;
        TenantExport::withoutTenancy()
            ->where('status', TenantExport::STATUS_READY)
            ->where('expires_at', '<=', now())
            ->lazyById()
            ->each(function (TenantExport $export) use (&$deleted) {
                if ($export->file_path) {
                    Storage::disk('local')->delete($export->file_path);
                }
                $export->forceFill(['status' => TenantExport::STATUS_EXPIRED, 'file_path' => null])->save();
                $deleted++;
            });

        $stuck = TenantExport::withoutTenancy()
            ->whereIn('status', TenantExport::ACTIVE_STATUSES)
            ->where('created_at', '<=', now()->subMinutes((int) config('exports.stale_minutes')))
            ->update([
                'status' => TenantExport::STATUS_FAILED,
                'error' => 'Timed out: the export did not finish in time.',
                'finished_at' => now(),
            ]);

        $this->info("Deleted {$deleted} expired export file(s); marked {$stuck} stuck export(s) as failed.");

        return self::SUCCESS;
    }
}
