<?php

namespace Tests\Feature;

use App\Core\Tenancy\TenantContext;
use App\Features\ActivityLog\Models\ActivityLog;
use App\Features\Auth\Models\User;
use App\Features\Departments\Admin\Models\Department;
use App\Features\Onboarding\Models\UserInvitation;
use App\Features\Onboarding\Services\InvitationService;
use App\Features\Tenancy\Models\Tenant;
use App\Mail\GeneralMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
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

    public function test_the_policy_alone_allows_only_verified_admins(): void
    {
        // Route middleware and EnsureEmailIsVerified normally refuse these first;
        // the policy must hold on its own too.
        $this->assertTrue($this->admin->can('create', UserInvitation::class));
        $this->assertFalse($this->makeUser('admin', verified: false)->can('create', UserInvitation::class));
        $this->assertFalse($this->makeUser('manager')->can('create', UserInvitation::class));
        $this->assertFalse($this->makeUser('employee')->can('create', UserInvitation::class));

        $this->invite();
        $invitation = $this->invitation();
        $otherTenant = Tenant::create(['name' => 'Other Co']);
        $otherAdmin = $this->makeUser('admin', tenantId: $otherTenant->id);

        $this->assertTrue($this->admin->can('update', $invitation));
        $this->assertTrue($this->admin->can('delete', $invitation));
        $this->assertFalse($otherAdmin->can('update', $invitation));
        $this->assertFalse($otherAdmin->can('delete', $invitation));
        $this->assertFalse($this->makeUser('manager')->can('delete', $invitation));
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
            ->assertStatus(302)
            ->assertSessionHas('error');
        $this->assertSame(2, UserInvitation::count());

        Carbon::setTestNow(now()->addHour()->addSecond());
        $this->invite(['email' => 'three@example.com']);
    }

    public function test_rejected_invite_attempts_count_towards_the_limit(): void
    {
        config(['onboarding.invitations.per_hour' => 3]);
        $elsewhere = $this->makeUser('employee', tenantId: Tenant::create(['name' => 'Other Co'])->id);

        // Testing whether addresses exist elsewhere: every attempt is rejected, and each one counts.
        foreach (range(1, 3) as $i) {
            $this->actingAs($this->admin)->post('/admin/invitations', ['email' => $elsewhere->email, 'role' => 'employee'])
                ->assertInvalid(['email']);
        }

        $this->actingAs($this->admin)->post('/admin/invitations', ['email' => $elsewhere->email, 'role' => 'employee'])
            ->assertSessionHas('error')
            ->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post('/admin/invitations', ['email' => 'real.person@example.com', 'role' => 'employee'])
            ->assertSessionHas('error');
        $this->assertSame(0, UserInvitation::count());
    }

    public function test_the_taken_email_message_does_not_say_where(): void
    {
        $sameTenant = $this->makeUser('employee');
        $elsewhere = $this->makeUser('employee', tenantId: Tenant::create(['name' => 'Other Co'])->id);

        foreach ([$sameTenant->email, $elsewhere->email] as $email) {
            $this->actingAs($this->admin)->post('/admin/invitations', ['email' => $email, 'role' => 'employee'])
                ->assertInvalid(['email' => "This email address can't be invited."]);
        }
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

    // ── Races between the admin and the invitee ───────────────────────────

    public function test_revoking_an_invite_accepted_meanwhile_is_refused(): void
    {
        $token = $this->invite();
        $loadedByAdmin = $this->invitation();

        // The invitee accepts after the admin's copy was loaded...
        $this->accept($token)->assertRedirect(route('dashboard'));

        // ...so the revoke must not report success on an account that now exists.
        $this->expectException(ValidationException::class);
        try {
            app(TenantContext::class)->run($this->tenant->id, fn () => app(InvitationService::class)->revoke($loadedByAdmin, $this->admin));
        } finally {
            $this->assertNull($this->invitation()->revoked_at);
        }
    }

    public function test_resending_an_invite_accepted_meanwhile_is_refused(): void
    {
        $token = $this->invite();
        $loadedByAdmin = $this->invitation();
        $this->accept($token);

        $this->expectException(ValidationException::class);
        try {
            app(TenantContext::class)->run($this->tenant->id, fn () => app(InvitationService::class)->resend($loadedByAdmin, $this->admin));
        } finally {
            $this->assertSame(hash('sha256', $token), $this->invitation()->token_hash);
        }
    }

    // ── The inviter's authority ───────────────────────────────────────────

    public function test_invites_stop_working_once_the_inviter_is_no_longer_an_active_admin(): void
    {
        foreach ([['role' => 'manager'], ['is_active' => false]] as $i => $change) {
            $inviter = $this->makeUser('admin');
            $this->admin = $inviter;
            $token = $this->invite(['email' => "lapsed{$i}@example.com"]);

            // Changed directly, as any path might: findOpen checks the inviter itself.
            User::withoutTenancy()->whereKey($inviter->id)->update($change + ['department_id' => $this->department->id]);

            $this->post('/auth/logout');
            $this->get("/invitations/{$token}")->assertSee('This invitation is no longer valid');
            $this->accept($token)->assertRedirect(route('invitations.show', $token));
            $this->assertFalse(User::withoutTenancy()->where('email', "lapsed{$i}@example.com")->exists());
        }
    }

    public function test_demoting_or_suspending_an_admin_revokes_their_pending_invites(): void
    {
        $otherAdmin = $this->makeUser('admin');

        foreach ([['role' => 'manager', 'is_active' => true], ['role' => 'admin', 'is_active' => false]] as $i => $change) {
            $inviter = $this->makeUser('admin');
            $this->admin = $inviter;
            $this->invite(['email' => "theirs{$i}@example.com"]);
            $this->admin = $otherAdmin;
            $this->invite(['email' => "kept{$i}@example.com"]);

            $this->actingAs($otherAdmin)->put("/admin/users/{$inviter->id}", $change + [
                'name' => $inviter->name,
                'email' => $inviter->email,
                'daily_capacity_hours' => 8,
                'department_id' => $this->department->id,
            ])->assertSessionHas('success');

            $this->assertNotNull(UserInvitation::where('email', "theirs{$i}@example.com")->sole()->revoked_at);
            $this->assertTrue(UserInvitation::where('email', "kept{$i}@example.com")->sole()->isOpen());
        }

        $this->assertSame(2, ActivityLog::where('action', 'user_invites_revoked')->count());
    }

    public function test_other_edits_to_an_admin_leave_their_invites_open(): void
    {
        $inviter = $this->makeUser('admin');
        $this->admin = $inviter;
        $this->invite(['email' => 'still-open@example.com']);

        $this->actingAs($this->makeUser('admin'))->put("/admin/users/{$inviter->id}", [
            'name' => 'Renamed Admin', 'email' => $inviter->email, 'role' => 'admin', 'is_active' => true, 'daily_capacity_hours' => 8,
        ])->assertSessionHas('success');

        $this->assertTrue(UserInvitation::where('email', 'still-open@example.com')->sole()->isOpen());
    }

    public function test_accepting_is_throttled_per_ip(): void
    {
        foreach (range(1, 20) as $i) {
            $this->get('/invitations/'.str_repeat('b', 64));
        }

        $this->get('/invitations/'.str_repeat('b', 64))->assertStatus(429);
    }
}
