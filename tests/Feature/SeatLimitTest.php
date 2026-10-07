<?php

namespace Tests\Feature;

use App\Core\Tenancy\TenantContext;
use App\Features\Auth\Models\User;
use App\Features\Billing\Models\TenantSubscription;
use App\Features\Onboarding\Models\UserInvitation;
use App\Features\Tenancy\Models\Tenant;
use App\Mail\GeneralMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Seat limits (Phase 1D): a tenant can't hold more users than its plan's
 * seats. Seats used = active users + open invitations. Checked when inviting,
 * accepting and reactivating; never when deactivating or editing.
 */
class SeatLimitTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-07 10:00:00');
        Mail::fake();

        // Created after setTestNow, so its trial runs from now.
        $this->tenant = Tenant::create(['name' => 'Seat Co']);
        app(TenantContext::class)->set($this->tenant->id);
        $this->admin = $this->makeUser('admin');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeUser(string $role = 'employee', bool $active = true, ?int $tenantId = null): User
    {
        return app(TenantContext::class)->run($tenantId ?? $this->tenant->id, fn () => User::create([
            'name' => "Tenant {$role}",
            'email' => uniqid("{$role}_").'@example.com',
            'password' => 'password',
            'role' => $role,
            'is_active' => $active,
        ]));
    }

    private function subscription(?Tenant $tenant = null): TenantSubscription
    {
        return TenantSubscription::where('tenant_id', ($tenant ?? $this->tenant)->id)->sole();
    }

    private function setSubscription(array $attributes, ?Tenant $tenant = null): void
    {
        $this->subscription($tenant)->forceFill($attributes)->save();
    }

    private function makeInvitation(array $state = [], ?User $inviter = null): UserInvitation
    {
        $inviter ??= $this->admin;

        return app(TenantContext::class)->run($inviter->tenant_id, function () use ($state, $inviter) {
            $invitation = UserInvitation::create([
                'email' => uniqid('invitee_').'@example.com',
                'role' => 'employee',
                'invited_by' => $inviter->id,
                'token_hash' => hash('sha256', uniqid('', true)),
                'expires_at' => now()->addDays(7),
            ]);
            $invitation->forceFill($state)->save();

            return $invitation;
        });
    }

    private function invite(string $email = 'new.person@example.com'): TestResponse
    {
        return $this->actingAs($this->admin)->post('/admin/invitations', ['email' => $email, 'role' => 'employee']);
    }

    private function assertInvited(TestResponse $response): void
    {
        $response->assertSessionHas('success')->assertSessionHasNoErrors();
    }

    private function assertRefused(TestResponse $response, string $message = 'seat'): void
    {
        $response->assertSessionHas('error', fn (string $error) => str_contains($error, $message));
    }

    private function lastToken(): string
    {
        $url = null;
        Mail::assertQueued(GeneralMail::class, function (GeneralMail $mail) use (&$url) {
            $url = $mail->data['actionUrl'] ?? $url;

            return true;
        });

        return basename(parse_url($url, PHP_URL_PATH));
    }

    private function accept(string $token): TestResponse
    {
        $this->post('/auth/logout');

        return $this->post("/invitations/{$token}", [
            'name' => 'New Person',
            'password' => 'their-own-password',
            'password_confirmation' => 'their-own-password',
        ]);
    }

    private function updateUser(User $user, array $changes): TestResponse
    {
        return $this->actingAs($this->admin)->put("/admin/users/{$user->id}", $changes + [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => $user->is_active,
            'daily_capacity_hours' => 8,
        ]);
    }

    // ── The trial every tenant starts on ──────────────────────────────────

    public function test_a_new_tenant_starts_a_14_day_trial_with_10_seats(): void
    {
        $subscription = $this->subscription();

        $this->assertSame('trial', $subscription->plan->key);
        $this->assertSame(TenantSubscription::STATUS_TRIALING, $subscription->status);
        $this->assertSame(10, $subscription->seats);
        $this->assertTrue($subscription->trial_ends_at->equalTo(now()->addDays(14)));
        $this->assertTrue($subscription->canAddSeats());
    }

    public function test_signup_starts_the_trial_and_sets_the_billing_email(): void
    {
        $this->post('/auth/register', [
            'company_name' => 'Acme Inc', 'name' => 'Jane Founder', 'email' => 'jane@acme.test',
            'password' => 'secret-password', 'password_confirmation' => 'secret-password',
        ])->assertRedirect(route('verification.notice'));

        $tenant = Tenant::where('name', 'Acme Inc')->sole();
        $this->assertSame('jane@acme.test', $tenant->billing_email);
        $this->assertSame(TenantSubscription::STATUS_TRIALING, $this->subscription($tenant)->status);
        $this->assertSame(10, $this->subscription($tenant)->seats);
    }

    // ── Inviting ──────────────────────────────────────────────────────────

    public function test_inviting_is_allowed_up_to_the_last_seat_and_refused_after(): void
    {
        // Admin + 8 = 9 of 10.
        foreach (range(1, 8) as $i) {
            $this->makeUser();
        }

        $this->assertInvited($this->invite('tenth@example.com'));
        $this->assertRefused($this->invite('eleventh@example.com'), '10 seats');

        $this->assertSame(['tenth@example.com'], UserInvitation::pluck('email')->all());
    }

    public function test_an_open_invitation_holds_a_seat(): void
    {
        $this->setSubscription(['seats' => 2]);
        $this->makeInvitation();

        $this->assertRefused($this->invite());
        $this->assertSame(1, UserInvitation::count());
    }

    public function test_revoked_expired_and_accepted_invitations_hold_no_seat(): void
    {
        $this->setSubscription(['seats' => 2]);
        $this->makeInvitation(['revoked_at' => now()]);
        $this->makeInvitation(['expires_at' => now()->subMinute()]);
        $this->makeInvitation(['accepted_at' => now()]);

        // Only the admin uses a seat.
        $this->assertInvited($this->invite());
        $this->assertRefused($this->invite('another@example.com'));
    }

    public function test_deactivated_users_hold_no_seat(): void
    {
        $this->setSubscription(['seats' => 2]);
        $this->makeUser(active: false);
        $this->makeUser(active: false);

        $this->assertInvited($this->invite());
    }

    // ── Accepting ─────────────────────────────────────────────────────────

    public function test_an_invite_holding_the_last_seat_can_be_accepted(): void
    {
        $this->setSubscription(['seats' => 2]);
        $this->assertInvited($this->invite());

        // 2 of 2 used, but the invite's own seat becomes the new user's.
        $this->accept($this->lastToken())->assertRedirect(route('dashboard'));

        $this->assertTrue(User::withoutTenancy()->where('email', 'new.person@example.com')->exists());
    }

    public function test_accepting_is_refused_when_seats_ran_out_meanwhile(): void
    {
        $this->setSubscription(['seats' => 2]);
        $this->assertInvited($this->invite());
        $token = $this->lastToken();

        // Someone else took the seat since: the plan was cut to the admin alone.
        $this->setSubscription(['seats' => 1]);

        $this->accept($token)->assertSessionHasErrors(['email' => 'This company has no free seats right now, so the invitation cannot be accepted yet. Please ask the person who invited you.']);

        $this->assertGuest('web');
        $this->assertFalse(User::withoutTenancy()->where('email', 'new.person@example.com')->exists());
        // Rolled back: the invite can still be accepted once a seat is free.
        $this->assertTrue(UserInvitation::withoutTenancy()->sole()->isOpen());

        $this->setSubscription(['seats' => 2]);
        $this->accept($token)->assertRedirect(route('dashboard'));
    }

    public function test_accepting_is_refused_when_the_plan_lapsed_meanwhile(): void
    {
        $this->assertInvited($this->invite());
        $this->setSubscription(['status' => TenantSubscription::STATUS_CANCELED]);

        $this->accept($this->lastToken())->assertSessionHasErrors('email');
        $this->assertFalse(User::withoutTenancy()->where('email', 'new.person@example.com')->exists());
    }

    // ── Reactivating, deactivating, editing ───────────────────────────────

    public function test_reactivating_a_user_needs_a_free_seat(): void
    {
        $this->setSubscription(['seats' => 2]);
        $active = $this->makeUser();
        $suspended = $this->makeUser(active: false);

        $this->assertRefused($this->updateUser($suspended, ['is_active' => true]));
        $this->assertFalse($suspended->fresh()->is_active);

        $this->updateUser($active, ['is_active' => false])->assertSessionHas('success');
        $this->updateUser($suspended, ['is_active' => true])->assertSessionHas('success');
        $this->assertTrue($suspended->fresh()->is_active);
    }

    public function test_deactivating_and_other_edits_are_never_blocked(): void
    {
        // Over the limit and lapsed: the worst case.
        $this->setSubscription(['seats' => 1, 'status' => TenantSubscription::STATUS_CANCELED]);
        $first = $this->makeUser();
        $second = $this->makeUser();
        $inactive = $this->makeUser(active: false);

        $this->updateUser($first, ['name' => 'Renamed'])->assertSessionHas('success');
        $this->assertSame('Renamed', $first->fresh()->name);

        $this->updateUser($second, ['is_active' => false])->assertSessionHas('success');
        $this->assertFalse($second->fresh()->is_active);

        // Editing a user who stays inactive takes no seat either.
        $this->updateUser($inactive, ['name' => 'Still Inactive'])->assertSessionHas('success');
        $this->assertSame('Still Inactive', $inactive->fresh()->name);
    }

    // ── Plan status ───────────────────────────────────────────────────────

    public function test_lapsed_plans_cannot_add_seats(): void
    {
        $suspended = $this->makeUser(active: false);

        foreach ([TenantSubscription::STATUS_PAST_DUE, TenantSubscription::STATUS_CANCELED, TenantSubscription::STATUS_NONE] as $status) {
            $this->setSubscription(['status' => $status]);

            $this->assertRefused($this->invite("{$status}@example.com"), 'not active');
            $this->assertRefused($this->updateUser($suspended, ['is_active' => true]), 'not active');
        }

        $this->assertSame(0, UserInvitation::count());
        $this->assertFalse($suspended->fresh()->is_active);
    }

    public function test_an_expired_trial_cannot_add_seats(): void
    {
        Carbon::setTestNow(now()->addDays(14)->subSecond());
        $this->assertInvited($this->invite('in-time@example.com'));

        Carbon::setTestNow(now()->addSecond());
        $this->assertRefused($this->invite('too-late@example.com'), 'trial has ended');
    }

    public function test_an_active_paid_plan_uses_its_paid_seat_count(): void
    {
        // Past the trial date, which no longer matters once paid.
        Carbon::setTestNow(now()->addDays(30));
        $this->setSubscription(['status' => TenantSubscription::STATUS_ACTIVE, 'seats' => 3]);

        $this->assertInvited($this->invite('one@example.com'));
        $this->assertInvited($this->invite('two@example.com'));
        $this->assertRefused($this->invite('three@example.com'), '3 seats');
    }

    public function test_a_tenant_without_a_subscription_cannot_add_seats(): void
    {
        $this->subscription()->delete();

        $this->assertRefused($this->invite(), 'no active plan');
        $this->assertSame(0, UserInvitation::count());
    }

    // ── Per tenant ────────────────────────────────────────────────────────

    public function test_other_tenants_users_and_invitations_never_count(): void
    {
        $other = Tenant::create(['name' => 'Other Co']);
        $otherAdmin = $this->makeUser('admin', tenantId: $other->id);
        foreach (range(1, 12) as $i) {
            $this->makeUser(tenantId: $other->id);
        }
        $this->makeInvitation(inviter: $otherAdmin);

        // This tenant: admin + 8 = 9 of 10, whatever the other tenant holds.
        foreach (range(1, 8) as $i) {
            $this->makeUser();
        }
        $this->assertInvited($this->invite());

        // And the other tenant's admin is held to the other tenant's own count.
        $this->admin = $otherAdmin;
        $this->assertRefused($this->invite('theirs@example.com'));
    }
}
