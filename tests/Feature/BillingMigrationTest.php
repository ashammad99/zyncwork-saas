<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The upgrade to Phase 1D billing on a database with existing tenants: each
 * gets a 14-day trial with 10 seats from the day it ships, and a billing
 * email from its oldest active admin. DatabaseMigrations, like
 * TenantBackfillMigrationTest, since it rolls migrations back and forth.
 */
class BillingMigrationTest extends TestCase
{
    use DatabaseMigrations;

    private const FIRST_BILLING_MIGRATION = '2026_10_07_100000_create_plans_table';

    private function rollBackBilling(): void
    {
        $steps = DB::table('migrations')->where('migration', '>=', self::FIRST_BILLING_MIGRATION)->count();
        Artisan::call('migrate:rollback', ['--step' => $steps, '--force' => true]);
    }

    private function insertUser(int $tenantId, string $email, string $role, bool $active = true): void
    {
        DB::table('users')->insert([
            'tenant_id' => $tenantId, 'name' => $email, 'email' => $email, 'password' => 'x',
            'role' => $role, 'is_active' => $active, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_existing_tenants_get_a_trial_and_a_billing_email(): void
    {
        $this->rollBackBilling();
        $this->assertFalse(Schema::hasTable('tenant_subscriptions'));
        $this->assertFalse(Schema::hasColumn('tenants', 'billing_email'));

        // Rows as the app before Phase 1D would have them.
        $withAdmins = DB::table('tenants')->insertGetId(['name' => 'Has Admins', 'created_at' => now(), 'updated_at' => now()]);
        $this->insertUser($withAdmins, 'old-but-gone@example.com', 'admin', active: false);
        $this->insertUser($withAdmins, 'employee@example.com', 'employee');
        $this->insertUser($withAdmins, 'first-active@example.com', 'admin');
        $this->insertUser($withAdmins, 'second-active@example.com', 'admin');
        $noAdmin = DB::table('tenants')->insertGetId(['name' => 'No Admin', 'created_at' => now(), 'updated_at' => now()]);
        $this->insertUser($noAdmin, 'only-employee@example.com', 'employee');

        Carbon::setTestNow('2026-10-07 10:00:00');
        Artisan::call('migrate', ['--force' => true]);
        Carbon::setTestNow();

        $trialPlan = DB::table('plans')->where('key', 'trial')->value('id');

        // Every tenant, including the TestCase default one.
        $this->assertSame(DB::table('tenants')->count(), DB::table('tenant_subscriptions')->count());

        foreach ([$withAdmins, $noAdmin] as $tenantId) {
            $subscription = DB::table('tenant_subscriptions')->where('tenant_id', $tenantId)->sole();
            $this->assertSame($trialPlan, $subscription->plan_id);
            $this->assertSame('trialing', $subscription->status);
            $this->assertSame(10, (int) $subscription->seats);
            $this->assertTrue(Carbon::parse($subscription->trial_ends_at)->equalTo(Carbon::parse('2026-10-21 10:00:00')));
        }

        $this->assertSame('first-active@example.com', DB::table('tenants')->where('id', $withAdmins)->value('billing_email'));
        $this->assertNull(DB::table('tenants')->where('id', $noAdmin)->value('billing_email'));
    }

    public function test_the_plans_ship_with_the_schema(): void
    {
        $plans = DB::table('plans')->orderBy('sort')->get()->keyBy('key');

        $this->assertSame(['trial', 'standard'], $plans->keys()->all());
        $this->assertSame(10, (int) $plans['trial']->included_seats);
        $this->assertTrue((bool) $plans['trial']->is_trial);
        $this->assertNull($plans['standard']->included_seats);

        // Placeholder price until the real one is set: 10.00 USD per seat per month.
        $this->assertSame(1000, (int) $plans['standard']->price_amount);
        $this->assertSame('USD', $plans['standard']->currency);
        $this->assertSame('month', $plans['standard']->interval);
        $this->assertNull($plans['trial']->price_amount);
    }

    public function test_plan_prices_roll_back_cleanly(): void
    {
        Artisan::call('migrate:rollback', ['--path' => 'database/migrations/2026_10_26_100000_add_prices_to_plans_table.php', '--force' => true]);

        foreach (['price_amount', 'currency', 'interval'] as $column) {
            $this->assertFalse(Schema::hasColumn('plans', $column));
        }
        $this->assertSame(2, DB::table('plans')->count());

        Artisan::call('migrate', ['--force' => true]);
        $this->assertSame(1000, (int) DB::table('plans')->where('key', 'standard')->value('price_amount'));
    }

    public function test_billing_rolls_back_cleanly(): void
    {
        $this->rollBackBilling();

        $this->assertFalse(Schema::hasTable('plans'));
        $this->assertFalse(Schema::hasTable('tenant_subscriptions'));
        $this->assertFalse(Schema::hasColumn('tenants', 'billing_email'));
        $this->assertFalse(Schema::hasTable('tenant_billing_notices'));

        Artisan::call('migrate', ['--force' => true]);
        $this->assertTrue(Schema::hasTable('tenant_subscriptions'));
        $this->assertTrue(Schema::hasTable('tenant_billing_notices'));
    }
}
