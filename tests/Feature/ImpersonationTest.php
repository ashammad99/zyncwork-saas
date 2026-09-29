<?php

namespace Tests\Feature;

use App\Core\Tenancy\TenantContext;
use App\Features\ActivityLog\Models\ActivityLog;
use App\Features\Auth\Models\User;
use App\Features\Onboarding\Models\UserInvitation;
use App\Features\Platform\Models\PlatformAdmin;
use App\Features\Platform\Models\PlatformAuditLog;
use App\Features\Platform\Models\PlatformImpersonation;
use App\Features\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Support sessions: requested on the platform host, handed off with a
 * one-time token, time-limited, and audited on both sides.
 */
class ImpersonationTest extends TestCase
{
    use Concerns\CrossesPlatformHost;
    use RefreshDatabase;

    private PlatformAdmin $admin;

    private User $target;

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
        $this->enrollTwoFactor($this->admin);
        $this->target = $this->makeUser('employee');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeUser(string $role, ?int $tenantId = null, array $overrides = []): User
    {
        return app(TenantContext::class)->run($tenantId ?? $this->tenant->id, fn () => User::create(array_merge([
            'name' => "Tenant {$role}",
            'email' => uniqid("{$role}_").'@example.com',
            'password' => Hash::make('password'),
            'role' => $role,
            'is_active' => true,
        ], $overrides)));
    }

    /** Request a session on the platform host; returns the hand-off path on the tenant host. */
    private function requestSession(?User $target = null): string
    {
        $target ??= $this->target;

        $response = $this->actingAs($this->admin, 'platform')
            ->post($this->platform("/tenants/{$target->tenant_id}/impersonate"), [
                'user_id' => $target->id,
                'reason' => 'Customer reported a broken timesheet',
            ])
            ->assertRedirect();

        // A separate browser session on the tenant host.
        $this->backToTenantHost();
        $this->flushSession();

        $path = parse_url($response->headers->get('Location'), PHP_URL_PATH);
        $this->assertStringStartsWith('/impersonation/', $path);

        return $path;
    }

    private function beginSupportSession(): PlatformImpersonation
    {
        $this->get($this->requestSession())->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->target, 'web');

        return PlatformImpersonation::sole();
    }

    private function tenantLog(): \Illuminate\Support\Collection
    {
        return app(TenantContext::class)->run($this->tenant->id, fn () => ActivityLog::orderBy('id')->get());
    }

    public function test_requesting_a_session_needs_the_capability_and_a_reason(): void
    {
        $this->admin->update(['capabilities' => [PlatformAdmin::VIEW_BILLING]]);
        $this->actingAs($this->admin, 'platform')
            ->post($this->platform("/tenants/{$this->tenant->id}/impersonate"), ['user_id' => $this->target->id, 'reason' => 'Investigating a bug'])
            ->assertForbidden();

        $this->admin->update(['capabilities' => PlatformAdmin::CAPABILITIES]);
        $this->post($this->platform("/tenants/{$this->tenant->id}/impersonate"), ['user_id' => $this->target->id, 'reason' => 'short'])
            ->assertSessionHasErrors('reason');

        $this->assertSame(0, PlatformImpersonation::count());
    }

    public function test_request_creates_a_pending_session_and_stores_only_a_token_hash(): void
    {
        $path = $this->requestSession();
        $token = basename($path);

        $impersonation = PlatformImpersonation::sole();
        $this->assertNull($impersonation->consumed_at);
        $this->assertSame(hash('sha256', $token), $impersonation->token_hash);
        $this->assertDatabaseMissing('platform_impersonations', ['token_hash' => $token]);
        $this->assertDatabaseHas('platform_audit_logs', ['action' => PlatformAuditLog::IMPERSONATION_REQUESTED, 'impersonation_id' => $impersonation->id]);

        // Nothing happened in the tenant yet, so nothing shows in its log.
        $this->assertCount(0, $this->tenantLog());
    }

    public function test_a_user_of_another_tenant_cannot_be_targeted_through_this_tenant(): void
    {
        $otherTenant = Tenant::create(['name' => 'Other Tenant']);
        $stranger = $this->makeUser('admin', $otherTenant->id);

        $this->actingAs($this->admin, 'platform')
            ->post($this->platform("/tenants/{$this->tenant->id}/impersonate"), ['user_id' => $stranger->id, 'reason' => 'Investigating a bug'])
            ->assertNotFound();

        $this->assertSame(0, PlatformImpersonation::count());
    }

    public function test_suspended_tenants_and_inactive_users_cannot_be_impersonated(): void
    {
        $inactive = $this->makeUser('employee', overrides: ['is_active' => false]);
        $this->actingAs($this->admin, 'platform')
            ->post($this->platform("/tenants/{$this->tenant->id}/impersonate"), ['user_id' => $inactive->id, 'reason' => 'Investigating a bug'])
            ->assertSessionHasErrors('user_id');

        $this->tenant->forceFill(['suspended_at' => now(), 'suspended_reason' => 'Unpaid'])->save();
        $this->post($this->platform("/tenants/{$this->tenant->id}/impersonate"), ['user_id' => $this->target->id, 'reason' => 'Investigating a bug'])
            ->assertSessionHasErrors('user_id');

        $this->assertSame(0, PlatformImpersonation::count());
    }

    public function test_hand_off_signs_in_as_the_target_and_is_recorded_on_both_sides(): void
    {
        $impersonation = $this->beginSupportSession();

        $this->assertTrue($impersonation->isLive());
        $this->assertEquals(now()->addMinutes(config('platform.impersonation.max_minutes')), $impersonation->expires_at);
        $this->assertDatabaseHas('platform_audit_logs', ['action' => PlatformAuditLog::IMPERSONATION_STARTED, 'impersonation_id' => $impersonation->id]);

        $line = $this->tenantLog()->sole();
        $this->assertSame('support_session_started', $line->action);
        $this->assertSame($this->target->id, $line->user_id);
        $this->assertSame(config('platform.actor_label'), $line->platform_actor);
        $this->assertStringContainsString('Customer reported a broken timesheet', $line->description);

        // Signing in this way is not a real login.
        $this->assertNull($this->target->fresh()->last_login_at);
    }

    public function test_the_hand_off_token_works_only_once(): void
    {
        $path = $this->requestSession();
        $this->get($path)->assertRedirect(route('dashboard'));

        Auth::guard('web')->logout();
        $this->flushSession();

        $this->get($path)->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->assertGuest('web');
    }

    public function test_an_unused_token_expires(): void
    {
        $path = $this->requestSession();

        Carbon::setTestNow(now()->addSeconds(config('platform.impersonation.token_ttl') + 1));

        $this->get($path)->assertRedirect(route('login'));
        $this->assertGuest('web');
        $this->assertNull(PlatformImpersonation::sole()->consumed_at);
    }

    public function test_write_requests_are_audited_without_their_bodies(): void
    {
        $impersonation = $this->beginSupportSession();

        $this->post('/user/notification/read-all', ['secret' => 'do-not-store']);

        $entry = PlatformAuditLog::where('action', PlatformAuditLog::IMPERSONATION_ACTION)->sole();
        $this->assertSame($impersonation->id, $entry->impersonation_id);
        $this->assertSame('POST', $entry->metadata['method']);
        $this->assertSame('user.notification.read-all', $entry->metadata['route']);
        $this->assertStringNotContainsString('do-not-store', json_encode($entry->metadata));

        // Page views are not audited.
        $this->get('/timesheets')->assertOk();
        $this->assertSame(1, PlatformAuditLog::where('action', PlatformAuditLog::IMPERSONATION_ACTION)->count());
    }

    public function test_the_impersonated_users_own_credentials_cannot_be_changed(): void
    {
        $this->beginSupportSession();

        $this->put('/user/profile/password', [
            'current_password' => 'password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])->assertForbidden();

        $this->assertTrue(Hash::check('password', $this->target->fresh()->password));
    }

    public function test_the_session_ends_when_its_time_limit_passes(): void
    {
        $impersonation = $this->beginSupportSession();

        Carbon::setTestNow(now()->addMinutes(config('platform.impersonation.max_minutes') + 1));

        $this->get('/timesheets')->assertRedirect(route('platform.tenants.show', $this->tenant->id));
        $this->assertGuest('web');

        $impersonation->refresh();
        $this->assertSame(PlatformImpersonation::END_EXPIRED, $impersonation->end_reason);
        $this->assertDatabaseHas('platform_audit_logs', ['action' => PlatformAuditLog::IMPERSONATION_ENDED, 'impersonation_id' => $impersonation->id]);
        $this->assertSame(['support_session_started', 'support_session_ended'], $this->tenantLog()->pluck('action')->all());
    }

    public function test_stopping_ends_the_session_once(): void
    {
        $impersonation = $this->beginSupportSession();

        $this->post('/impersonation/stop')->assertRedirect(route('platform.tenants.show', $this->tenant->id));
        $this->assertGuest('web');

        $impersonation->refresh();
        $this->assertSame(PlatformImpersonation::END_STOPPED, $impersonation->end_reason);
        $this->assertSame(1, PlatformAuditLog::where('action', PlatformAuditLog::IMPERSONATION_ENDED)->count());
        $this->assertSame(1, $this->tenantLog()->where('action', 'support_session_ended')->count());
    }

    public function test_logging_out_ends_the_session(): void
    {
        $impersonation = $this->beginSupportSession();

        $this->post('/auth/logout');
        $this->assertGuest('web');

        $this->assertSame(PlatformImpersonation::END_LOGGED_OUT, $impersonation->fresh()->end_reason);
    }

    public function test_pages_show_who_is_acting_during_a_session(): void
    {
        $this->beginSupportSession();

        $this->get('/timesheets')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('impersonation.admin_name', 'Support Person')
            ->where('impersonation.user_name', $this->target->name)
            ->where('auth.user.id', $this->target->id));
    }

    public function test_user_management_is_blocked_while_acting_as_a_tenant_admin(): void
    {
        $tenantAdmin = $this->makeUser('admin');
        $other = $this->makeUser('employee');
        $this->get($this->requestSession($tenantAdmin))->assertRedirect();
        $this->assertAuthenticatedAs($tenantAdmin, 'web');

        // A valid reset token for another user: the block, not a bad token, must stop it.
        Mail::fake();
        DB::table('password_reset_tokens')->insert(['email' => $other->email, 'token' => Hash::make('valid-token'), 'created_at' => now()]);

        // Likewise a valid, open invitation (new users join only by invitation).
        $invitation = app(TenantContext::class)->run($this->tenant->id, fn () => UserInvitation::create([
            'email' => 'invited@example.com',
            'role' => 'admin',
            'invited_by' => $tenantAdmin->id,
            'token_hash' => hash('sha256', 'valid-invite-token'),
            'expires_at' => now()->addDays(7),
        ]));

        $blocked = [
            ['POST', '/auth/forgot-password', ['email' => $tenantAdmin->email]],
            ['POST', '/auth/reset-password', ['token' => 'valid-token', 'email' => $other->email, 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123']],
            ['POST', '/admin/invitations', ['email' => 'backdoor@example.com', 'role' => 'admin']],
            ['POST', "/admin/invitations/{$invitation->id}/resend", []],
            ['DELETE', "/admin/invitations/{$invitation->id}", []],
            ['GET', '/invitations/valid-invite-token', []],
            ['POST', '/invitations/valid-invite-token', ['name' => 'Backdoor', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123']],
            ['POST', '/auth/register', ['company_name' => 'Backdoor Inc', 'name' => 'Backdoor', 'email' => 'backdoor@example.com', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123']],
            ['PUT', "/admin/users/{$other->id}", ['name' => $other->name, 'email' => 'taken-over@example.com', 'role' => 'admin', 'is_active' => true, 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123']],
            ['PUT', "/admin/users/{$tenantAdmin->id}", ['name' => $tenantAdmin->name, 'email' => 'self@example.com', 'role' => 'admin', 'is_active' => true]],
            ['PUT', '/admin/profile', ['name' => 'Renamed', 'email' => 'profile@example.com']],
            ['PUT', '/admin/profile/password', ['current_password' => 'password', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123']],
        ];

        foreach ($blocked as [$method, $uri, $data]) {
            $this->call($method, $uri, $data)->assertForbidden();
        }

        app(TenantContext::class)->run($this->tenant->id, function () use ($other, $tenantAdmin) {
            $this->assertFalse(User::where('email', 'backdoor@example.com')->exists());
            $this->assertSame('employee', $other->fresh()->role);
            $this->assertSame($other->email, $other->fresh()->email);
            $this->assertTrue(Hash::check('password', $other->fresh()->password));
            $this->assertSame($tenantAdmin->email, $tenantAdmin->fresh()->email);
            $this->assertTrue(Hash::check('password', $tenantAdmin->fresh()->password));
        });

        Mail::assertNothingSent();
        $this->assertSame(1, DB::table('password_reset_tokens')->count());

        // No invitation was created, resent, revoked or used.
        app(TenantContext::class)->run($this->tenant->id, function () use ($invitation) {
            $this->assertSame(1, UserInvitation::count());
            $this->assertTrue($invitation->fresh()->isOpen());
            $this->assertSame(hash('sha256', 'valid-invite-token'), $invitation->fresh()->token_hash);
            $this->assertFalse(User::where('email', 'invited@example.com')->exists());
        });
        $this->assertSame(1, \App\Features\Tenancy\Models\Tenant::count());

        // Blocked requests are refused before they run, so none is logged as a change.
        $this->assertSame(0, PlatformAuditLog::where('action', PlatformAuditLog::IMPERSONATION_ACTION)->count());
        // The session itself carries on.
        $this->get('/admin/users')->assertOk();
    }

    public function test_every_user_management_write_route_is_blocked(): void
    {
        $tenantAdmin = $this->makeUser('admin');
        $this->get($this->requestSession($tenantAdmin))->assertRedirect();

        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route) => str_starts_with((string) $route->getName(), 'admin.users.')
                || str_starts_with((string) $route->getName(), 'admin.invitations.')
                || str_starts_with((string) $route->getName(), 'invitations.'))
            ->reject(fn ($route) => in_array('GET', $route->methods(), true));

        // update, invite, resend, revoke, accept.
        $this->assertCount(5, $routes);

        // A real invitation: route model binding runs first, so a made-up id would 404 instead.
        $invitation = app(TenantContext::class)->run($this->tenant->id, fn () => UserInvitation::create([
            'email' => 'invited@example.com',
            'role' => 'employee',
            'invited_by' => $tenantAdmin->id,
            'token_hash' => hash('sha256', 'any-token'),
            'expires_at' => now()->addDays(7),
        ]));

        foreach ($routes as $route) {
            $uri = str_replace(['{user}', '{invitation}', '{token}'], [(string) $tenantAdmin->id, (string) $invitation->id, 'any-token'], $route->uri());
            $this->call($route->methods()[0], "/{$uri}")->assertForbidden();
        }
    }

    public function test_a_support_session_does_not_bypass_email_verification(): void
    {
        $this->target->forceFill(['email_verified_at' => null])->saveQuietly();
        $this->beginSupportSession();

        $this->get('/timesheets')->assertRedirect(route('verification.notice'));
        $this->post('/user/notification/read-all')->assertForbidden();
    }

    public function test_deactivating_the_admin_ends_a_running_session(): void
    {
        $impersonation = $this->beginSupportSession();

        $this->admin->update(['is_active' => false]);

        $this->get('/timesheets')->assertRedirect(route('platform.tenants.show', $this->tenant->id));
        $this->assertGuest('web');
        $this->assertSame(PlatformImpersonation::END_REVOKED, $impersonation->fresh()->end_reason);
        $this->assertSame(1, $this->tenantLog()->where('action', 'support_session_ended')->count());
    }

    public function test_removing_the_impersonate_capability_ends_a_running_session(): void
    {
        $impersonation = $this->beginSupportSession();

        $this->admin->update(['capabilities' => [PlatformAdmin::VIEW_BILLING]]);

        $this->post('/user/notification/read-all')->assertRedirect(route('platform.tenants.show', $this->tenant->id));
        $this->assertGuest('web');
        $this->assertSame(PlatformImpersonation::END_REVOKED, $impersonation->fresh()->end_reason);
        // The blocked write never ran, so nothing was recorded as done in the session.
        $this->assertSame(0, PlatformAuditLog::where('action', PlatformAuditLog::IMPERSONATION_ACTION)->count());
    }

    public function test_a_token_is_refused_once_the_admin_is_deactivated(): void
    {
        $path = $this->requestSession();

        $this->admin->update(['is_active' => false]);

        $this->get($path)->assertRedirect(route('login'));
        $this->assertGuest('web');
        $this->assertSame(PlatformImpersonation::END_REVOKED, PlatformImpersonation::sole()->end_reason);
        // It never gave access, so the tenant's log has nothing.
        $this->assertCount(0, $this->tenantLog());
    }

    public function test_resetting_the_admins_password_ends_running_and_pending_sessions(): void
    {
        $running = $this->beginSupportSession();

        Artisan::call('platform:admin', ['email' => $this->admin->email, '--reset-password' => true]);

        $this->assertSame(PlatformImpersonation::END_REVOKED, $running->fresh()->end_reason);
        $this->get('/timesheets')->assertRedirect(route('platform.tenants.show', $this->tenant->id));
        $this->assertGuest('web');

        $path = $this->requestSession();
        Artisan::call('platform:admin', ['email' => $this->admin->email, '--reset-password' => true]);

        $this->get($path)->assertRedirect(route('login'));
        $this->assertGuest('web');
        $this->assertSame(PlatformImpersonation::END_REVOKED, PlatformImpersonation::latest('id')->first()->end_reason);
    }

    public function test_resetting_the_admins_two_factor_ends_running_and_pending_sessions(): void
    {
        $running = $this->beginSupportSession();

        Artisan::call('platform:admin', ['email' => $this->admin->email, '--reset-2fa' => true]);

        $this->assertSame(PlatformImpersonation::END_REVOKED, $running->fresh()->end_reason);
        $this->get('/timesheets')->assertRedirect(route('platform.tenants.show', $this->tenant->id));
        $this->assertGuest('web');

        $this->enrollTwoFactor($this->admin);
        $path = $this->requestSession();
        Artisan::call('platform:admin', ['email' => $this->admin->email, '--reset-2fa' => true]);

        $this->get($path)->assertRedirect(route('login'));
        $this->assertGuest('web');
        $this->assertSame(PlatformImpersonation::END_REVOKED, PlatformImpersonation::latest('id')->first()->end_reason);
    }

    /** As when PLATFORM_DOMAIN is unset: the console routes are not registered (routes/web.php). */
    private function dropConsoleRoutes(): void
    {
        $router = $this->app['router'];
        $routes = new RouteCollection;

        foreach ($router->getRoutes()->getRoutes() as $route) {
            if (! str_starts_with((string) $route->getName(), 'platform.')) {
                $routes->add($route);
            }
        }

        $router->setRoutes($routes);
        $this->app['url']->setRoutes($routes);
    }

    public function test_an_ending_session_goes_to_the_tenant_login_without_the_console(): void
    {
        $impersonation = $this->beginSupportSession();
        $this->dropConsoleRoutes();

        Carbon::setTestNow(now()->addMinutes(config('platform.impersonation.max_minutes') + 1));

        $this->get('/timesheets')->assertRedirect(route('login'));
        $this->assertGuest('web');
        $this->assertSame(PlatformImpersonation::END_EXPIRED, $impersonation->fresh()->end_reason);
    }

    public function test_stopping_goes_to_the_tenant_login_without_the_console(): void
    {
        $impersonation = $this->beginSupportSession();
        $this->dropConsoleRoutes();

        $this->post('/impersonation/stop')->assertRedirect(route('login'));
        $this->assertGuest('web');
        $this->assertSame(PlatformImpersonation::END_STOPPED, $impersonation->fresh()->end_reason);
    }

    public function test_suspending_the_tenant_ends_access_during_a_session(): void
    {
        $this->beginSupportSession();

        $this->tenant->forceFill(['suspended_at' => now(), 'suspended_reason' => 'Unpaid'])->save();

        $this->get('/timesheets')->assertRedirect(route('login'));
        $this->assertGuest('web');
    }
}
