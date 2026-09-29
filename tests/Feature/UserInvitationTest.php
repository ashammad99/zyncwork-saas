<?php

namespace Tests\Feature;

use App\Core\Tenancy\TenantContext;
use App\Features\ActivityLog\Models\ActivityLog;
use App\Features\Auth\Models\User;
use App\Features\Departments\Admin\Models\Department;
use App\Features\Onboarding\Models\UserInvitation;
use App\Features\Tenancy\Models\Tenant;
use App\Mail\GeneralMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Invitations are the only way into an existing tenant (Phase 1C): verified
 * tenant admins invite; the invitee sets their own password from a single-use
 * link that expires after 7 days.
 */
class UserInvitationTest extends TestCase
{
    use RefreshDatabase;

    protected bool $usersStartVerified = false;

    private User $admin;

    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-29 10:00:00');
        Mail::fake();

        $this->admin = $this->makeUser('admin');
        $this->department = Department::create(['name' => 'Engineering']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeUser(string $role, bool $verified = true, ?int $tenantId = null, array $overrides = []): User
    {
        return app(TenantContext::class)->run($tenantId ?? $this->tenant->id, function () use ($role, $verified, $overrides) {
            $user = User::create(array_merge([
                'name' => "Tenant {$role}",
                'email' => uniqid("{$role}_").'@example.com',
                'password' => 'password',
                'role' => $role,
                'is_active' => true,
                'department_id' => $role === 'admin' ? null : Department::value('id'),
            ], $overrides));

            if ($verified) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            return $user;
        });
    }

    /** Invite as the admin; returns the plain token from the queued email. */
    private function invite(array $data = []): string
    {
        $this->actingAs($this->admin)->post('/admin/invitations', array_merge([
            'email' => 'new.person@example.com',
            'role' => 'employee',
            'department_id' => $this->department->id,
        ], $data))->assertSessionHasNoErrors();

        return $this->lastToken();
    }

    private function lastToken(): string
    {
        $url = null;
        Mail::assertQueued(GeneralMail::class, function (GeneralMail $mail) use (&$url) {
            if ($mail->contentView === 'emails.auth.invitation-body') {
                $url = $mail->data['actionUrl'];
            }

            return true;
        });

        return basename(parse_url($url, PHP_URL_PATH));
    }

    private function accept(string $token, array $data = []): TestResponse
    {
        $this->post('/auth/logout');

        return $this->post("/invitations/{$token}", array_merge([
            'name' => 'New Person',
            'password' => 'their-own-password',
            'password_confirmation' => 'their-own-password',
        ], $data));
    }

    private function invitation(): UserInvitation
    {
        return UserInvitation::withoutTenancy()->latest('id')->firstOrFail();
    }

    // ── Who can invite ────────────────────────────────────────────────────

    public function test_an_admin_invites_by_email_and_only_a_hash_is_stored(): void
    {
        $token = $this->invite(['email' => ' New.Person@EXAMPLE.com ']);

        $invitation = $this->invitation();
        $this->assertSame('new.person@example.com', $invitation->email);
        $this->assertSame('employee', $invitation->role);
        $this->assertSame($this->department->id, $invitation->department_id);
        $this->assertSame($this->tenant->id, $invitation->tenant_id);
        $this->assertSame($this->admin->id, $invitation->invited_by);
        $this->assertEquals(now()->addDays(7), $invitation->expires_at);
        $this->assertSame(hash('sha256', $token), $invitation->token_hash);
        $this->assertSame(64, strlen($token));

        Mail::assertQueued(GeneralMail::class, fn (GeneralMail $mail) => $mail->hasTo('new.person@example.com')
            && $mail->data['companyName'] === $this->tenant->name);
        $this->assertTrue(ActivityLog::where('action', 'user_invited')->exists());
    }

    public function test_managers_and_employees_cannot_invite(): void
    {
        foreach (['manager', 'employee'] as $role) {
            $this->actingAs($this->makeUser($role))
                ->post('/admin/invitations', ['email' => "from-{$role}@example.com", 'role' => 'employee'])
                ->assertForbidden();
        }

        $this->assertSame(0, UserInvitation::count());
    }

    public function test_an_unverified_admin_cannot_invite(): void
    {
        $this->actingAs($this->makeUser('admin', verified: false))
            ->post('/admin/invitations', ['email' => 'someone@example.com', 'role' => 'employee'])
            ->assertForbidden();

        $this->assertSame(0, UserInvitation::count());
        Mail::assertNothingQueued();
    }

    public function test_an_admin_of_another_tenant_cannot_touch_this_tenants_invitations(): void
    {
        $this->invite();
        $invitation = $this->invitation();

        $otherTenant = Tenant::create(['name' => 'Other Co']);
        $otherAdmin = $this->makeUser('admin', tenantId: $otherTenant->id);

        $this->actingAs($otherAdmin)->post("/admin/invitations/{$invitation->id}/resend")->assertNotFound();
        $this->actingAs($otherAdmin)->delete("/admin/invitations/{$invitation->id}")->assertNotFound();

        $this->assertTrue($invitation->fresh()->isOpen());
    }

    public function test_the_users_page_lists_only_this_tenants_open_invitations(): void
    {
        $this->invite(['email' => 'pending@example.com']);

        $otherTenant = Tenant::create(['name' => 'Other Co']);
        app(TenantContext::class)->run($otherTenant->id, fn () => UserInvitation::create([
            'email' => 'elsewhere@example.com', 'role' => 'employee',
            'invited_by' => $this->makeUser('admin', tenantId: $otherTenant->id)->id,
            'token_hash' => hash('sha256', 'x'), 'expires_at' => now()->addDays(7),
        ]));

        $this->actingAs($this->admin)->get('/admin/users')->assertInertia(fn ($page) => $page
            ->has('invitations', 1)
            ->where('invitations.0.email', 'pending@example.com')
            ->where('invitations.0.department', 'Engineering')
            ->missing('invitations.0.token_hash'));
    }

    public function test_direct_user_creation_is_gone(): void
    {
        $this->actingAs($this->admin)->post('/admin/users', [
            'name' => 'Direct', 'email' => 'direct@example.com', 'password' => 'password123',
            'password_confirmation' => 'password123', 'role' => 'employee', 'is_active' => true, 'daily_capacity_hours' => 8,
        ])->assertStatus(405);

        $this->assertFalse(User::withoutTenancy()->where('email', 'direct@example.com')->exists());
    }

    // ── What can be invited ───────────────────────────────────────────────

    public function test_invalid_invitations_are_refused(): void
    {
        $existing = $this->makeUser('employee');
        $otherTenant = Tenant::create(['name' => 'Other Co']);
        $otherDepartment = app(TenantContext::class)->run($otherTenant->id, fn () => Department::create(['name' => 'Theirs']));
        $userElsewhere = $this->makeUser('employee', tenantId: $otherTenant->id);

        $post = fn (array $data) => $this->actingAs($this->admin)->post('/admin/invitations', $data);

        $post(['email' => $existing->email, 'role' => 'employee'])->assertInvalid(['email']);
        // Emails are unique across the platform, not just this tenant.
        $post(['email' => strtoupper($userElsewhere->email), 'role' => 'employee'])->assertInvalid(['email']);
        $post(['email' => 'x@example.com', 'role' => 'employee', 'department_id' => $otherDepartment->id])->assertInvalid(['department_id']);
        $post(['email' => 'x@example.com', 'role' => 'owner'])->assertInvalid(['role']);
        $post(['email' => 'not-an-email', 'role' => 'employee'])->assertInvalid(['email']);
        // Managers without a department fail most policies.
        $post(['email' => 'x@example.com', 'role' => 'manager'])->assertInvalid(['department_id']);

        $this->assertSame(0, UserInvitation::count());
    }

    public function test_a_second_open_invitation_to_the_same_email_is_refused(): void
    {
        $this->invite(['email' => 'twice@example.com']);

        $this->actingAs($this->admin)->post('/admin/invitations', ['email' => 'TWICE@example.com', 'role' => 'employee'])
            ->assertInvalid(['email']);

        $this->assertSame(1, UserInvitation::count());
    }

    public function test_another_tenant_may_invite_the_same_email(): void
    {
        $this->invite(['email' => 'wanted@example.com']);

        $otherTenant = Tenant::create(['name' => 'Other Co']);
        $this->actingAs($this->makeUser('admin', tenantId: $otherTenant->id))
            ->post('/admin/invitations', ['email' => 'wanted@example.com', 'role' => 'employee'])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, UserInvitation::withoutTenancy()->where('email', 'wanted@example.com')->count());
    }

    public function test_invitations_per_tenant_are_limited_per_hour(): void
    {
        config(['onboarding.invitations.per_hour' => 2]);

        $this->invite(['email' => 'one@example.com']);
        $this->invite(['email' => 'two@example.com']);

        $this->actingAs($this->admin)->post('/admin/invitations', ['email' => 'three@example.com', 'role' => 'employee'])
            ->assertInvalid(['email']);
        $this->assertSame(2, UserInvitation::count());

        Carbon::setTestNow(now()->addHour()->addSecond());
        $this->invite(['email' => 'three@example.com']);
    }

    // ── Accepting ─────────────────────────────────────────────────────────

    public function test_the_invitation_page_shows_the_company_and_email(): void
    {
        $token = $this->invite();
        $this->post('/auth/logout');

        $this->get("/invitations/{$token}")->assertOk()
            ->assertSee($this->tenant->name)
            ->assertSee('new.person@example.com');
    }

    public function test_accepting_creates_a_verified_user_in_the_inviting_tenant(): void
    {
        $token = $this->invite();

        $this->accept($token)->assertRedirect(route('dashboard'));

        $user = User::withoutTenancy()->where('email', 'new.person@example.com')->sole();
        $this->assertSame($this->tenant->id, $user->tenant_id);
        $this->assertSame('employee', $user->role);
        $this->assertSame($this->department->id, $user->department_id);
        $this->assertSame('New Person', $user->name);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertTrue(Hash::check('their-own-password', $user->password));
        $this->assertAuthenticatedAs($user, 'web');

        $invitation = $this->invitation();
        $this->assertNotNull($invitation->accepted_at);
        $this->assertSame($user->id, $invitation->accepted_user_id);

        // Verified by the invite: straight in, no verify screen.
        $this->get('/dashboard')->assertOk();
    }

    public function test_a_link_works_only_once(): void
    {
        $token = $this->invite();
        $this->accept($token);

        $this->accept($token, ['name' => 'Second Try'])->assertRedirect(route('invitations.show', $token));
        $this->get("/invitations/{$token}")->assertSee('This invitation is no longer valid');

        $this->assertSame(1, User::withoutTenancy()->where('email', 'new.person@example.com')->count());
    }

    public function test_expired_revoked_and_unknown_links_all_look_the_same(): void
    {
        $expired = $this->invite(['email' => 'expired@example.com']);
        $revoked = $this->invite(['email' => 'revoked@example.com']);
        $revokedId = $this->invitation()->id;
        $this->actingAs($this->admin)->delete("/admin/invitations/{$revokedId}")->assertRedirect();

        Carbon::setTestNow(now()->addDays(7)->addSecond());

        foreach ([$expired, $revoked, str_repeat('a', 64)] as $token) {
            $this->post('/auth/logout');
            $this->get("/invitations/{$token}")->assertOk()->assertSee('This invitation is no longer valid');
            $this->accept($token)->assertRedirect(route('invitations.show', $token));
        }

        $this->assertFalse(User::withoutTenancy()->whereIn('email', ['expired@example.com', 'revoked@example.com'])->exists());
    }

    public function test_resending_replaces_the_link_and_extends_the_expiry(): void
    {
        $old = $this->invite();
        $invitation = $this->invitation();

        Carbon::setTestNow(now()->addDays(3));
        $this->actingAs($this->admin)->post("/admin/invitations/{$invitation->id}/resend")->assertSessionHas('success');
        $new = $this->lastToken();

        $this->assertNotSame($old, $new);
        $this->assertEquals(now()->addDays(7), $invitation->fresh()->expires_at);

        $this->accept($old)->assertRedirect(route('invitations.show', $old));
        $this->accept($new)->assertRedirect(route('dashboard'));
    }

    public function test_resending_is_limited_to_once_a_minute_and_not_after_use(): void
    {
        $token = $this->invite();
        $invitation = $this->invitation();

        $this->actingAs($this->admin)->post("/admin/invitations/{$invitation->id}/resend")->assertSessionHas('success');
        $this->actingAs($this->admin)->post("/admin/invitations/{$invitation->id}/resend")->assertSessionHas('error');

        Carbon::setTestNow(now()->addMinutes(2));
        $this->accept($this->lastToken());

        $this->actingAs($this->admin)->post("/admin/invitations/{$invitation->id}/resend")->assertSessionHas('error');
        $this->actingAs($this->admin)->delete("/admin/invitations/{$invitation->id}")->assertSessionHas('error');
    }

    public function test_an_email_taken_after_the_invite_cannot_accept(): void
    {
        $token = $this->invite(['email' => 'contested@example.com']);

        // Another company signs the same person up first.
        $otherTenant = Tenant::create(['name' => 'Other Co']);
        $this->makeUser('employee', tenantId: $otherTenant->id, overrides: ['email' => 'contested@example.com']);

        $this->accept($token)->assertInvalid(['email']);

        $this->assertSame(1, User::withoutTenancy()->where('email', 'contested@example.com')->count());
        // Rolled back: the invite was not used up.
        $this->assertNull($this->invitation()->accepted_at);
        $this->assertGuest('web');
    }

    public function test_a_suspended_tenants_invitations_cannot_be_accepted(): void
    {
        $token = $this->invite();
        $this->tenant->forceFill(['suspended_at' => now(), 'suspended_reason' => 'Unpaid'])->save();

        $this->accept($token)->assertRedirect(route('invitations.show', $token));
        $this->assertFalse(User::withoutTenancy()->where('email', 'new.person@example.com')->exists());
    }

    public function test_accepting_needs_a_name_and_a_confirmed_password(): void
    {
        $token = $this->invite();

        $this->accept($token, ['name' => '', 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertInvalid(['name', 'password']);
        $this->accept($token, ['password_confirmation' => 'different'])->assertInvalid(['password']);

        $this->assertTrue($this->invitation()->isOpen());
    }

    public function test_the_form_cannot_change_the_invited_email_role_or_department(): void
    {
        $token = $this->invite(['role' => 'employee']);

        $this->accept($token, ['email' => 'hijack@example.com', 'role' => 'admin', 'department_id' => null]);

        $user = User::withoutTenancy()->where('email', 'new.person@example.com')->sole();
        $this->assertSame('employee', $user->role);
        $this->assertSame($this->department->id, $user->department_id);
        $this->assertFalse(User::withoutTenancy()->where('email', 'hijack@example.com')->exists());
    }

    public function test_accepting_is_throttled_per_ip(): void
    {
        foreach (range(1, 20) as $i) {
            $this->get('/invitations/'.str_repeat('b', 64));
        }

        $this->get('/invitations/'.str_repeat('b', 64))->assertStatus(429);
    }
}
