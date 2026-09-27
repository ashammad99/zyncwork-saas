<?php

namespace App\Features\Platform\Services;

use App\Core\BaseService;
use App\Features\Platform\Models\PlatformAdmin;
use App\Features\Platform\Models\PlatformAuditLog;
use Illuminate\Support\Facades\Artisan;

class CacheService extends BaseService
{
    public function __construct(
        protected PlatformAuditLogger $audit
    ) {}

    /**
     * Clear caches based on type and log the activity.
     *
     * @param string $type
     * @return string
     * @throws \Exception
     */
    public function clearCaches(string $type, PlatformAdmin $admin): string
    {
        switch ($type) {
            case 'view':
                Artisan::call('view:clear');
                $msg = 'View cache cleared successfully.';
                break;
            case 'config':
                Artisan::call('config:clear');
                $msg = 'Configuration cache cleared successfully.';
                break;
            case 'route':
                Artisan::call('route:clear');
                $msg = 'Route cache cleared successfully.';
                break;
            case 'application':
                Artisan::call('cache:clear');
                $msg = 'Application cache cleared successfully.';
                break;
            case 'all':
                Artisan::call('optimize:clear');
                $msg = 'All caches (Optimization) cleared successfully.';
                break;
            default:
                throw new \Exception('Invalid cache type selected.');
        }

        // Caches are shared by every tenant, so this is a platform action, not a tenant one.
        $this->audit->record(PlatformAuditLog::CACHE_CLEARED, $admin, ['metadata' => ['type' => $type]]);

        return $msg;
    }
}
