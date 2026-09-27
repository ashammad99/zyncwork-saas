<?php

namespace Tests\Feature;

use App\Core\Tenancy\TenantContext;
use App\Features\ActivityLog\Models\ActivityLog;
use App\Features\Auth\Models\User;
use App\Features\Platform\Models\PlatformAdmin;
use App\Features\Platform\Models\PlatformAuditLog;
use App\Features\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TenantSuspensionTest extends TestCase
{
    use Concerns\CrossesPlatformHost;
    use RefreshDatabase;

    private PlatformAdmin $admin;

    private Tenant $otherTenant;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-27 10:00:00');

        $this->admin = PlatformAdmin::create([
            'name' => 'Support Person',
            'email' => 'staff@zyncwork.test',
            'password' => 'platform-password',
            'capabilities' => PlatformAdmin::CAPABILITIES,
            'is_active' => true,
        ]);
        $this->otherTenant = Tenant::create(['name' => 'Other Tenant']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeUser(string $role = 'employee', ?int $tenantId = null): User
    {
        return app(TenantContext::class)->run($tenantId ?? $this->tenant->id, fn () => User::create([
            'name' => "Tenant {$role}",
            'email' => uniqid("{$role}_").'@example.com',
            'password' => Hash::make('password'),
            'role' => $role,
            'is_active' => true,
        ]));
    }

    private function suspend(Tenant $tenant, string $reason = 'Unpaid invoices'): void
    {
        $this->actingAs($this->admin, 'platform')
            ->post($this->platform("/tenants/{$tenant->id}/suspend"), ['reason' => $reason])
            ->assertSessionHas('success');

        // Next requests in this test are tenant requests again.
        $this->backToTenantHost();
    }

    public function test_suspending_records_it_on_both_sides(): void
    {
        $this->suspend($this->tenant);

        $tenant = $this->tenant->fresh();
        $this->assertTrue($tenant->isSuspended());
        $this->assertSame('Unpaid invoices', $tenant->suspended_reason);

        $entry = PlatformAuditLog::where('action', PlatformAuditLog::TENANT_SUSPENDED)->sole();
        $this->assertSame($this->admin->id, $entry->platform_admin_id);
        $this->assertSame($this->tenant->id, $entry->tenant_id);

        $line = app(TenantContext::class)->run($this->tenant->id, fn () => ActivityLog::sole());
        $this->assertNull($line->user_id);
        $this->assertSame(config('platform.actor_label'), $line->platform_actor);
        $this->assertStringContainsString("ref #{$entry->id}", $line->description);

        // Nothing leaks into the other tenant's log.
        $this->assertSame(0, app(TenantContext::class)->run($this->otherTenant->id, fn () => ActivityLog::count()));
    }

    public function test_signed_in_users_of_a_suspended_tenant_are_logged_out(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->get('/timesheets')->assertOk();

        $this->suspend($this->tenant);

        // A new request loads the user (and tenant) from the session afresh; $user
        // still holds the tenant it cached before the suspension.
        $this->actingAs($user->fresh())->get('/timesheets')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Your organisation\'s account has been suspended. Please contact support.']);
        $this->assertGuest('web');
    }

    public function test_users_of_a_suspended_tenant_cannot_log_in(): void
    {
        $user = $this->makeUser('admin');
        $this->suspend($this->tenant);

        $this->post('/auth/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest('web');
    }

    public function test_other_tenants_are_unaffected(): void
    {
        $other = $this->makeUser('employee', $this->otherTenant->id);
        $this->suspend($this->tenant);

        $this->actingAs($other)->get('/timesheets')->assertOk();
        $this->assertAuthenticatedAs($other, 'web');
    }

    public function test_unsuspending_restores_access_and_is_recorded(): void
    {
        $user = $this->makeUser('admin');
        $this->suspend($this->tenant);

        $this->actingAs($this->admin, 'platform')
            ->post($this->platform("/tenants/{$this->tenant->id}/unsuspend"))
            ->assertSessionHas('success');
        $this->backToTenantHost();

        $this->assertFalse($this->tenant->fresh()->isSuspended());
        $this->assertNull($this->tenant->fresh()->suspended_reason);
        $this->assertDatabaseHas('platform_audit_logs', ['action' => PlatformAuditLog::TENANT_UNSUSPENDED, 'tenant_id' => $this->tenant->id]);
        $this->assertSame(2, app(TenantContext::class)->run($this->tenant->id, fn () => ActivityLog::whereNull('user_id')->count()));

        $this->post('/auth/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user, 'web');
    }

    public function test_suspending_twice_is_refused_and_a_reason_is_required(): void
    {
        $this->actingAs($this->admin, 'platform');

        $this->post($this->platform("/tenants/{$this->tenant->id}/suspend"), ['reason' => ''])
            ->assertSessionHasErrors('reason');
        $this->assertFalse($this->tenant->fresh()->isSuspended());

        $this->post($this->platform("/tenants/{$this->tenant->id}/suspend"), ['reason' => 'Unpaid invoices']);
        $this->post($this->platform("/tenants/{$this->tenant->id}/suspend"), ['reason' => 'Again'])
            ->assertSessionHas('error');

        $this->assertSame(1, PlatformAuditLog::where('action', PlatformAuditLog::TENANT_SUSPENDED)->count());
    }

    public function test_suspending_needs_the_capability(): void
    {
        $this->admin->update(['capabilities' => [PlatformAdmin::VIEW_BILLING, PlatformAdmin::IMPERSONATE]]);

        $this->actingAs($this->admin, 'platform')
            ->post($this->platform("/tenants/{$this->tenant->id}/suspend"), ['reason' => 'Unpaid invoices'])
            ->assertForbidden();

        $this->assertFalse($this->tenant->fresh()->isSuspended());
    }
}
