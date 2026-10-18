<?php

namespace Tests\Feature;

use App\Features\Platform\Services\HealthStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The platform health page reads the running configuration, not env(), which
 * returns nothing useful once `php artisan config:cache` has run.
 */
class HealthStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_metrics_come_from_config(): void
    {
        config([
            'app.env' => 'staging',
            'app.debug' => true,
            'database.default' => 'mysql',
            'cache.default' => 'array',
            'queue.default' => 'database',
        ]);

        $metrics = app(HealthStatusService::class)->getSystemMetrics();

        $this->assertSame('staging', $metrics['environment']);
        $this->assertSame('Enabled', $metrics['debug_mode']);
        $this->assertSame('mysql', $metrics['database']['connection']);
        $this->assertSame('array', $metrics['cache']['driver']);
        $this->assertSame('database', $metrics['queue']);

        config(['app.debug' => false]);
        $this->assertSame('Disabled', app(HealthStatusService::class)->getSystemMetrics()['debug_mode']);
    }
}
