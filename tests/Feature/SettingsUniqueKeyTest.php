<?php

namespace Tests\Feature;

use App\Core\Tenancy\TenantContext;
use App\Features\Auth\Models\User;
use App\Features\Tenancy\Models\Tenant;
use App\Features\SystemSettings\Admin\Models\Setting;
use App\Features\SystemSettings\Admin\Services\SettingsService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SettingsUniqueKeyTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_09_23_000000_add_unique_setting_key_to_settings_table.php');
    }

    private function tenantMigration(): object
    {
        return require database_path('migrations/2026_09_24_100002_scope_settings_unique_key_to_tenant.php');
    }

    public function test_duplicate_setting_key_is_rejected(): void
    {
        Setting::create(['setting_key' => 'site_name', 'setting_value' => 'A']);

        $this->expectException(QueryException::class);
        DB::table('settings')->insert(['tenant_id' => $this->tenant->id, 'setting_key' => 'site_name', 'setting_value' => 'B']);
    }

    public function test_each_tenant_has_its_own_settings_and_cache(): void
    {
        $service = app(SettingsService::class);
        $other = Tenant::create(['name' => 'Other']);

        $service->update(['app.name' => 'Ours']);
        $this->assertSame('Ours', Setting::get('app.name')); // warms our cache

        app(TenantContext::class)->run($other->id, function () use ($service) {
            $this->assertNull(Setting::get('app.name'));
            $service->update(['app.name' => 'Theirs']);
            $this->assertSame('Theirs', Setting::get('app.name'));
        });

        $this->assertSame('Ours', Setting::get('app.name'));
        $this->assertSame(2, DB::table('settings')->where('setting_key', 'app.name')->count());
    }

    public function test_service_update_upserts_one_row_per_key_and_flushes_cache(): void
    {
        $service = app(SettingsService::class);

        $service->update(['app.name' => 'First', 'mfa.enabled' => true]);
        $this->assertSame('First', Setting::get('app.name')); // warms the cache

        $service->update(['app.name' => 'Second', 'mfa.enabled' => false]);

        $this->assertSame(2, DB::table('settings')->count());
        $this->assertSame('Second', Setting::get('app.name'));
        $this->assertSame('0', Setting::get('mfa.enabled'));
    }

    private function makeUser(string $role): User
    {
        return User::create([
            'name' => 'Test User',
            'email' => uniqid('user_') . '@example.com',
            'password' => Hash::make('password'),
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function formPayload(string $name): array
    {
        return [
            'app.name' => $name,
            'app.timezone' => 'UTC',
            'app.locale' => 'en',
            'notifications.poll_interval_seconds' => 30,
            'timer.idle_threshold_hours' => 8,
            'timer.auto_cap_hours' => 10,
            'capacity.default_daily_hours' => 8,
            'capacity.warning_threshold_pct' => 90,
            'capacity.overload_threshold_pct' => 110,
            'export.max_date_range_days' => 365,
            'reports.burndown_enabled' => true,
            'file_upload.max_size_mb' => 10,
            'file_upload.allowed_mimes' => 'pdf,png',
            'email.from_address' => 'noreply@example.com',
            'mfa.enabled' => false,
        ];
    }

    public function test_admin_can_save_settings_form_twice_without_duplicates(): void
    {
        $admin = $this->makeUser('admin');

        // Inertia's useForm posts JSON with flat dotted keys.
        $this->actingAs($admin)->patchJson('/admin/settings', $this->formPayload('One'))->assertValid();
        $this->actingAs($admin)->patchJson('/admin/settings', $this->formPayload('Two'))->assertValid();

        $this->assertSame(1, DB::table('settings')->where('setting_key', 'app.name')->count());
        $this->assertSame(15, DB::table('settings')->count());
        $this->assertSame('Two', Setting::get('app.name'));
        $this->assertSame('1', Setting::get('reports.burndown_enabled'));
        $this->assertSame('0', Setting::get('mfa.enabled'));
    }

    public function test_invalid_settings_report_errors_under_flat_keys(): void
    {
        $admin = $this->makeUser('admin');
        $payload = $this->formPayload('One');
        $payload['app.timezone'] = 'Mars/Olympus';
        unset($payload['email.from_address']);

        $this->actingAs($admin)->patchJson('/admin/settings', $payload)
            ->assertInvalid(['app.timezone', 'email.from_address'])
            ->assertValid(['app.name']);

        $this->assertSame(0, DB::table('settings')->count());
    }

    public function test_non_admins_cannot_save_settings(): void
    {
        foreach (['manager', 'employee'] as $role) {
            $this->actingAs($this->makeUser($role))
                ->patchJson('/admin/settings', $this->formPayload('Nope'))
                ->assertForbidden();
        }

        $this->assertSame(0, DB::table('settings')->count());
    }

    public function test_migration_keeps_newest_row_per_key(): void
    {
        // Roll back to the pre-tenancy index, then replay the dedupe migration.
        $tenantMigration = $this->tenantMigration();
        $tenantMigration->down();
        $migration = $this->migration();
        $migration->down();

        $t = $this->tenant->id;
        DB::table('settings')->insert([
            ['tenant_id' => $t, 'setting_key' => 'site_name', 'setting_value' => 'old'],
            ['tenant_id' => $t, 'setting_key' => 'timezone', 'setting_value' => 'UTC'],
            ['tenant_id' => $t, 'setting_key' => 'site_name', 'setting_value' => 'new'],
        ]);

        $migration->up();
        $tenantMigration->up();

        $this->assertSame(2, DB::table('settings')->count());
        $this->assertSame('new', DB::table('settings')->where('setting_key', 'site_name')->value('setting_value'));
        $this->assertSame('UTC', DB::table('settings')->where('setting_key', 'timezone')->value('setting_value'));
    }
}
