<?php

namespace App\Features\CacheManagement\Admin\Services;

use App\Core\BaseService;
use Illuminate\Support\Facades\Artisan;
use App\Features\ActivityLog\Events\ActivityLogged;
use Illuminate\Support\Facades\Auth;

class CacheService extends BaseService
{
    /**
     * Clear caches based on type and log the activity.
     *
     * @param string $type
     * @return string
     * @throws \Exception
     */
    public function clearCaches(string $type): string
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

        event(new ActivityLogged(Auth::user(), 'cache_cleared', "Admin cleared cache: {$type}", 'system'));

        return $msg;
    }
}
