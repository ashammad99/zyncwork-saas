<?php

namespace App\Features\SystemHealth\Admin\Services;

use App\Core\BaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class HealthStatusService extends BaseService
{
    /**
     * Gather and return all system health metrics.
     *
     * @return array
     */
    public function getSystemMetrics(): array
    {
        return [
            'app_version' => env('APP_VERSION', '1.0.0'),
            'laravel_version' => app()->version(),
            'php_version' => phpversion(),
            'environment' => env('APP_ENV', 'production'),
            'debug_mode' => env('APP_DEBUG', false) ? 'Enabled' : 'Disabled',
            'server_os' => php_uname('s') . ' ' . php_uname('r'),
            'database' => [
                'connection' => env('DB_CONNECTION', 'mysql'),
                'status' => $this->checkDatabaseConnection(),
            ],
            'cache' => [
                'driver' => env('CACHE_DRIVER', 'file'),
                'status' => $this->checkCacheConnection(),
            ],
            'queue' => env('QUEUE_CONNECTION', 'sync'),
            'timezone' => config('app.timezone'),
            'memory_limit' => ini_get('memory_limit'),
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
