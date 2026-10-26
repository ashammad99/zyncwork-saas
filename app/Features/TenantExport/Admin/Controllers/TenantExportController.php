<?php

namespace App\Features\TenantExport\Admin\Controllers;

use App\Core\BaseController;
use App\Features\ActivityLog\Events\ActivityLogged;
use App\Features\TenantExport\Exceptions\ExportNotAllowed;
use App\Features\TenantExport\Models\TenantExport;
use App\Features\TenantExport\Services\TenantExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Tenant admins start a full data export and download it. The export is
 * found through the tenant scope (another tenant's id is a 404), and only the
 * admin who asked for it may download it, through a signed, expiring link.
 */
class TenantExportController extends BaseController
{
    public function __construct(
        protected TenantExportService $exports,
    ) {}

    public function store(Request $request)
    {
        try {
            $this->exports->request($request->user());
        } catch (ExportNotAllowed $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Your export is being prepared. We will email you a download link when it is ready.');
    }

    public function download(Request $request, TenantExport $export)
    {
        abort_unless((int) $export->requested_by === (int) $request->user()->id, 403, 'Only the admin who requested this export can download it.');
        abort_unless($export->isDownloadable() && Storage::disk('local')->exists($export->file_path), 410, 'This export is no longer available. Please start a new one.');

        $export->forceFill(['downloaded_at' => now()])->save();
        event(new ActivityLogged($request->user(), 'tenant_export_downloaded', "Downloaded data export #{$export->id}.", 'security'));

        return Storage::disk('local')->download($export->file_path, 'zyncwork-export-'.$export->created_at->format('Y-m-d').'.zip');
    }
}
