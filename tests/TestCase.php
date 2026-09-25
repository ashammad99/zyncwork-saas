<?php

namespace Tests;

use App\Core\Tenancy\TenantContext;
use App\Features\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * The tenant that database tests act as by default, so fixtures built with
     * Model::create() get a tenant_id. actingAs() switches to the acting
     * user's tenant, as a real login does.
     */
    protected ?Tenant $tenant = null;

    /**
     * Refuse to run against anything but the test database: RefreshDatabase
     * drops every table, so a cached config or stray env var must never let
     * the suite reach the real database.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $database = $app['config']->get('database.connections.'.$app['config']->get('database.default').'.database');
        if ($database !== 'zyncwork_saas_db_test') {
            throw new \RuntimeException("Refusing to run tests against database [{$database}]; expected [zyncwork_saas_db_test].");
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $traits = class_uses_recursive($this);
        if (isset($traits[RefreshDatabase::class]) || isset($traits[DatabaseMigrations::class])) {
            $this->tenant = Tenant::create(['name' => 'Test Tenant']);
            app(TenantContext::class)->set($this->tenant->id);
        }
    }
}
