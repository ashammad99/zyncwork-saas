<?php

namespace Tests;

use App\Core\Tenancy\TenantContext;
use App\Features\Auth\Models\User;
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

        if ($this->usersStartVerified) {
            // Like UserFactory's default: fixtures built with User::create() are
            // verified, or EnsureEmailIsVerified would block every request.
            // email_verified_at is deliberately not fillable, hence the event.
            User::creating(function (User $user) {
                $user->email_verified_at ??= now();
            });
        }
    }

    /**
     * Onboarding tests set this to false: there the app itself decides who is
     * verified (signup starts unverified, accepting an invite verifies).
     */
    protected bool $usersStartVerified = true;
}
