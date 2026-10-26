<?php

namespace App\Features\Platform\Services;

use App\Core\BaseService;
use App\Features\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthStatusService extends BaseService
{
    /**
     * Gather and return all system health metrics.
     */
    public function getSystemMetrics(): array
    {
        return [
            // No config key for this one yet: after config:cache it always shows the default.
            'app_version' => env('APP_VERSION', '1.0.0'),
            'laravel_version' => app()->version(),
            'php_version' => phpversion(),
            'environment' => config('app.env'),
            'debug_mode' => config('app.debug') ? 'Enabled' : 'Disabled',
            'server_os' => php_uname('s').' '.php_uname('r'),
            'database' => [
                'connection' => config('database.default'),
                'status' => $this->checkDatabaseConnection(),
            ],
            'cache' => [
                'driver' => config('cache.default'),
                'status' => $this->checkCacheConnection(),
            ],
            'queue' => config('queue.default'),
            'timezone' => config('app.timezone'),
            'memory_limit' => ini_get('memory_limit'),
            'tenants' => [
                'total' => Tenant::count(),
                'suspended' => Tenant::whereNotNull('suspended_at')->count(),
            ],
            'failed_jobs' => DB::table('failed_jobs')->count(),
        ];
    }

    /**
     * Test the DB connection.
     */
    private function checkDatabaseConnection(): string
    {
        try {
            DB::connection()->getPdo();

            return 'Connected';
        } catch (\Exception $e) {
            return 'Error';
        }
    }

    /**
     * Test the Cache connection.
     */
    private function checkCacheConnection(): string
    {
        try {
            Cache::store()->get('health_check');

            return 'Connected';
        } catch (\Exception $e) {
            return 'Error';
        }
    }
}
