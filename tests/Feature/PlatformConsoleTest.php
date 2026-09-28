<?php

namespace Tests\Feature;

use App\Features\ActivityLog\Models\ActivityLog;
use App\Features\Auth\Models\User;
use App\Features\Platform\Models\PlatformAdmin;
use App\Features\Platform\Models\PlatformAuditLog;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use LogicException;
use RuntimeException;
use Tests\TestCase;

/**
 * The platform console: its own guard, host and audit trail, and a fixed
 * list of capabilities. Tenant users and platform admins never cross over.
 */
class PlatformConsoleTest extends TestCase
{
    use Concerns\CrossesPlatformHost;
    use RefreshDatabase;

    private function makeAdmin(array $capabilities = PlatformAdmin::CAPABILITIES, array $overrides = []): PlatformAdmin
    {
        $admin = PlatformAdmin::create(array_merge([
            'name' => 'Support Person',
            'email' => uniqid('staff_').'@zyncwork.test',
            'password' => 'platform-password',
            'capabilities' => $capabilities,
            'is_active' => true,
        ], $overrides));

        $this->secret = $this->enrollTwoFactor($admin);

        return $admin;
    }

    private string $secret;

    private function makeUser(string $role = 'admin'): User
    {
        return User::create([
            'name' => "Tenant {$role}",
            'email' => uniqid("{$role}_").'@example.com',
            'password' => Hash::make('password'),
            'role' => $role,
            'is_active' => true,
        ]);
    }

    public function test_platform_admin_can_log_in_and_the_login_is_audited(): void
    {
        $admin = $this->makeAdmin();

        // Password first (no session yet), then the TOTP code.
        $this->post($this->platform('/login'), ['email' => $admin->email, 'password' => 'platform-password'])
            ->assertRedirect(route('platform.two-factor.challenge'));
        $this->assertGuest('platform');

        $this->post($this->platform('/two-factor'), ['code' => app(\PragmaRX\Google2FA\Google2FA::class)->getCurrentOtp($this->secret)])
            ->assertRedirect(route('platform.tenants.index'));

        $this->assertAuthenticatedAs($admin, 'platform');
        $this->assertNotNull($admin->fresh()->last_login_at);
        $this->assertDatabaseHas('platform_audit_logs', ['action' => PlatformAuditLog::LOGIN, 'platform_admin_id' => $admin->id]);
    }

    public function test_failed_login_is_rejected_and_audited_without_an_admin(): void
    {
        $admin = $this->makeAdmin();

        $this->post($this->platform('/login'), ['email' => $admin->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest('platform');
        $this->assertDatabaseHas('platform_audit_logs', ['action' => PlatformAuditLog::LOGIN_FAILED, 'platform_admin_id' => null]);
    }

    public function test_deactivated_platform_admin_cannot_log_in(): void
    {
        $admin = $this->makeAdmin(overrides: ['is_active' => false]);

        $this->post($this->platform('/login'), ['email' => $admin->email, 'password' => 'platform-password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest('platform');
    }

    public function test_tenant_admin_credentials_do_not_work_on_the_platform_login(): void
    {
        $user = $this->makeUser('admin');

        $this->post($this->platform('/login'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest('platform');
    }

    public function test_platform_admin_credentials_do_not_work_on_the_tenant_login(): void
    {
        $admin = $this->makeAdmin();

        $this->post('/auth/login', ['email' => $admin->email, 'password' => 'platform-password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest('web');
    }

    public function test_guest_on_the_platform_host_is_sent_to_the_platform_login(): void
    {
        $this->get($this->platform('/tenants'))->assertRedirect(route('platform.login'));
    }

    public function test_signed_in_tenant_admin_is_a_guest_on_the_platform_host(): void
    {
        $this->actingAs($this->makeUser('admin'));

        $this->get($this->platform('/tenants'))->assertRedirect(route('platform.login'));
    }

    public function test_tenant_pages_do_not_exist_on_the_platform_host(): void
    {
        $this->actingAs($this->makeAdmin(), 'platform');

        $this->get($this->platform('/timesheets'))->assertNotFound();
        $this->get($this->platform('/dashboard'))->assertNotFound();
    }

    public function test_platform_pages_do_not_exist_on_the_tenant_host(): void
    {
        $this->actingAs($this->makeUser('admin'));

        $this->get('/tenants')->assertNotFound();
        $this->get('/health')->assertNotFound();
    }

    public function test_tenant_admins_no_longer_have_the_cache_and_health_pages(): void
    {
        $this->actingAs($this->makeUser('admin'));

        $this->get('/admin/cache')->assertNotFound();
        $this->get('/admin/system/health')->assertNotFound();
    }

    public function test_each_route_needs_its_capability(): void
    {
        $this->actingAs($this->makeAdmin([PlatformAdmin::VIEW_BILLING]), 'platform');

        $this->get($this->platform('/billing'))->assertOk();
        $this->get($this->platform('/health'))->assertForbidden();
        $this->post($this->platform('/cache/clear'), ['type' => 'all'])->assertForbidden();
        $this->post($this->platform("/tenants/{$this->tenant->id}/suspend"), ['reason' => 'Unpaid invoices'])->assertForbidden();
        $this->post($this->platform("/tenants/{$this->tenant->id}/impersonate"), ['user_id' => 1, 'reason' => 'Investigating a bug'])->assertForbidden();
    }

    public function test_deactivated_admin_is_signed_out_mid_session(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'platform');
        $this->get($this->platform('/tenants'))->assertOk();

        $admin->update(['is_active' => false]);

        // Including the pages that need no capability, which list tenant users.
        $this->get($this->platform("/tenants/{$this->tenant->id}"))
            ->assertRedirect(route('platform.login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest('platform');
    }

    public function test_deactivated_admin_cannot_reach_any_console_page(): void
    {
        $admin = $this->makeAdmin();
        $admin->update(['is_active' => false]);

        foreach (['/tenants', "/tenants/{$this->tenant->id}", '/health', '/billing'] as $path) {
            $this->actingAs($admin, 'platform')->get($this->platform($path))->assertRedirect(route('platform.login'));
        }
    }

    public function test_production_refuses_to_boot_without_a_platform_domain(): void
    {
        $this->app['env'] = 'production';
        config(['platform.domain' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PLATFORM_DOMAIN');

        (new AppServiceProvider($this->app))->boot();
    }

    public function test_production_boots_with_a_platform_domain(): void
    {
        $this->app['env'] = 'production';
        config(['platform.domain' => 'console.zyncwork.test']);

        (new AppServiceProvider($this->app))->boot();

        $this->addToAssertionCount(1);
    }

    public function test_the_example_env_ships_no_platform_domain(): void
    {
        // A production .env copied from the example must not get a guessable host.
        preg_match('/^PLATFORM_DOMAIN=(.*)$/m', file_get_contents(base_path('.env.example')), $match);

        $this->assertNotEmpty($match, '.env.example should list PLATFORM_DOMAIN');
        $this->assertSame('', trim($match[1]));
    }

    public function test_without_a_platform_domain_the_console_is_not_served(): void
    {
        config(['platform.domain' => null]);

        $router = $this->app['router'];
        $router->setRoutes(new RouteCollection);
        require base_path('app/Features/Platform/routes/web.php');
        $router->getRoutes()->refreshNameLookups();

        // Route::domain(null) would have put the console on every host.
        $this->assertFalse($router->has('platform.login'));
        $this->assertFalse($router->has('platform.tenants.index'));
        $this->assertTrue($router->has('impersonation.consume'));
    }

    public function test_billing_and_tenant_views_are_recorded_in_the_platform_log_only(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'platform');

        $this->get($this->platform('/billing'))->assertOk();
        $this->get($this->platform("/tenants/{$this->tenant->id}"))->assertOk();

        $this->assertDatabaseHas('platform_audit_logs', ['action' => PlatformAuditLog::BILLING_VIEWED, 'platform_admin_id' => $admin->id]);
        $this->assertDatabaseHas('platform_audit_logs', ['action' => PlatformAuditLog::TENANT_VIEWED, 'tenant_id' => $this->tenant->id]);
        $this->assertSame(0, ActivityLog::count());
    }

    public function test_audit_log_entries_cannot_be_changed_or_deleted(): void
    {
        $entry = PlatformAuditLog::create(['action' => PlatformAuditLog::LOGIN]);

        try {
            $entry->update(['action' => 'something_else']);
            $this->fail('An audit entry was updated.');
        } catch (LogicException) {
        }

        try {
            $entry->delete();
            $this->fail('An audit entry was deleted.');
        } catch (LogicException) {
        }

        $this->assertDatabaseHas('platform_audit_logs', ['id' => $entry->id, 'action' => PlatformAuditLog::LOGIN]);
    }

    public function test_platform_host_shares_the_platform_admin_and_no_tenant_user(): void
    {
        $admin = $this->makeAdmin();

        // actingAs() makes 'platform' the default guard before any middleware runs,
        // the order EnsureUserIsActive must not depend on.
        $this->actingAs($admin, 'platform')
            ->get($this->platform('/health'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.check', false)
                ->where('auth.user', null)
                ->where('platform.email', $admin->email)
                ->where('notifications', []));

        $this->assertAuthenticatedAs($admin, 'platform');
    }

    public function test_login_page_renders_without_remember_me_or_password_reset(): void
    {
        $this->get($this->platform('/login'))
            ->assertOk()
            ->assertSee('Platform sign in')
            ->assertSee(route('platform.login.attempt'), false)
            ->assertDontSee('name="remember"', false)
            ->assertDontSee(route('auth.forgot-password.index'), false);
    }

    public function test_console_pages_render_their_components(): void
    {
        $this->actingAs($this->makeAdmin(), 'platform');
        $user = $this->makeUser('employee');

        $this->get($this->platform('/tenants'))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('(platform)/tenants/Index')
            ->where('tenants.0.id', $this->tenant->id)
            ->where('tenants.0.users_count', 1));

        $this->get($this->platform("/tenants/{$this->tenant->id}"))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('(platform)/tenants/Show')
            ->where('users.0.id', $user->id)
            ->where('can.suspend', true)
            ->where('can.impersonate', true));

        $this->get($this->platform('/billing'))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('(platform)/billing/Index')
            ->where('tenants.0.plan', null));

        $this->get($this->platform('/health'))->assertInertia(fn (AssertableInertia $page) => $page->component('(platform)/health/page'));
        $this->get($this->platform('/cache'))->assertInertia(fn (AssertableInertia $page) => $page->component('(platform)/cache/page'));
    }

    public function test_tenant_detail_hides_actions_the_admin_cannot_take(): void
    {
        $this->actingAs($this->makeAdmin([PlatformAdmin::VIEW_BILLING]), 'platform');

        $this->get($this->platform("/tenants/{$this->tenant->id}"))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('can.suspend', false)
            ->where('can.impersonate', false));
    }

    public function test_tenant_users_never_pass_platform_gates(): void
    {
        $user = $this->makeUser('admin');

        foreach (PlatformAdmin::CAPABILITIES as $capability) {
            $this->assertFalse(\Illuminate\Support\Facades\Gate::forUser($user)->allows("platform.{$capability}"));
        }
    }
}
