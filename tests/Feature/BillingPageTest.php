<?php

namespace Tests\Feature;

use App\Core\Tenancy\TenantContext;
use App\Features\ActivityLog\Models\ActivityLog;
use App\Features\Auth\Models\User;
use App\Features\Billing\Models\TenantSubscription;
use App\Features\Onboarding\Models\UserInvitation;
use App\Features\Tenancy\Models\Tenant;
use App\Mail\GeneralMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The tenant admin's billing page (Phase 1D, PR 2a): plan, status, seats and
 * billing email, always for the signed-in admin's own tenant.
 */
class BillingPageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-11 10:00:00');
        Mail::fake();

        $this->tenant = Tenant::create(['name' => 'Billing Co']);
        $this->tenant->forceFill(['billing_email' => 'accounts@billing.test'])->save();
        app(TenantContext::class)->set($this->tenant->id);
        $this->admin = $this->makeUser('admin');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeUser(string $role = 'employee', bool $active = true, ?Tenant $tenant = null, array $overrides = []): User
    {
        return app(TenantContext::class)->run(($tenant ?? $this->tenant)->id, fn () => User::create(array_merge([
            'name' => "Tenant {$role}",
            'email' => uniqid("{$role}_").'@example.com',
            'password' => 'password',
            'role' => $role,
            'is_active' => $active,
        ], $overrides)));
    }

    private function makeInvitation(User $inviter, array $state = []): void
    {
        app(TenantContext::class)->run($inviter->tenant_id, function () use ($inviter, $state) {
            UserInvitation::create([
                'email' => uniqid('invitee_').'@example.com',
                'role' => 'employee',
                'invited_by' => $inviter->id,
                'token_hash' => hash('sha256', uniqid('', true)),
                'expires_at' => now()->addDays(7),
            ])->forceFill($state)->save();
        });
    }

    private function setSubscription(array $attributes, ?Tenant $tenant = null): void
    {
        TenantSubscription::where('tenant_id', ($tenant ?? $this->tenant)->id)->sole()->forceFill($attributes)->save();
    }

    private function updateEmail(string $email, ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->admin)->put('/admin/billing/email', ['billing_email' => $email]);
    }

    // ── The page ──────────────────────────────────────────────────────────

    public function test_an_admin_sees_their_plan_seats_and_billing_email(): void
    {
        // 3 active (admin + 2) + 1 open invite = 4 of 10. Inactive users and closed invites don't count.
        $this->makeUser();
        $this->makeUser();
        $this->makeUser(active: false);
        $this->makeInvitation($this->admin);
        $this->makeInvitation($this->admin, ['revoked_at' => now()]);

        $this->actingAs($this->admin)->get('/admin/billing')->assertInertia(fn (AssertableInertia $page) => $page
            ->component('(portals)/admin/billing/page')
            ->where('billing.plan', 'Trial')
            ->where('billing.status', 'trialing')
            ->where('billing.trial_expired', false)
            ->where('billing.can_add_seats', true)
            ->where('billing.seats', 10)
            ->where('billing.active_users', 3)
            ->where('billing.pending_invitations', 1)
            ->where('billing.seats_used', 4)
            ->where('billing.seats_full', false)
            ->where('billing.billing_email', 'accounts@billing.test')
            ->where('billing.trial_ends_at', fn ($value) => Carbon::parse($value)->equalTo(now()->addDays(14))));
    }

    public function test_an_expired_trial_and_full_seats_are_flagged(): void
    {
        $this->setSubscription(['seats' => 2, 'trial_ends_at' => now()->subMinute()]);
        $this->makeUser();

        $this->actingAs($this->admin)->get('/admin/billing')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('billing.trial_expired', true)
            ->where('billing.can_add_seats', false)
            ->where('billing.seats_used', 2)
            ->where('billing.seats_full', true));
    }

    public function test_managers_and_employees_cannot_see_or_change_billing(): void
    {
        foreach (['manager', 'employee'] as $role) {
            $user = $this->makeUser($role);

            $this->actingAs($user)->get('/admin/billing')->assertForbidden();
            $this->updateEmail('taken-over@example.com', as: $user)->assertForbidden();
        }

        $this->assertSame('accounts@billing.test', $this->tenant->fresh()->billing_email);
    }

    public function test_guests_are_sent_to_login(): void
    {
        $this->get('/admin/billing')->assertRedirect(route('login'));
    }

    public function test_an_admin_only_ever_sees_their_own_tenant(): void
    {
        $other = Tenant::create(['name' => 'Other Co']);
        $other->forceFill(['billing_email' => 'theirs@other.test'])->save();
        $this->setSubscription(['status' => TenantSubscription::STATUS_ACTIVE, 'seats' => 50], $other);
        $otherAdmin = $this->makeUser('admin', tenant: $other);
        foreach (range(1, 5) as $i) {
            $this->makeUser(tenant: $other);
        }
        $this->makeInvitation($otherAdmin);

        $this->actingAs($this->admin)->get('/admin/billing')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('billing.billing_email', 'accounts@billing.test')
            ->where('billing.seats', 10)
            ->where('billing.active_users', 1)
            ->where('billing.pending_invitations', 0));

        // And the other admin's update changes only their own tenant.
        $this->updateEmail('new@other.test', as: $otherAdmin)->assertSessionHas('success');
        $this->assertSame('new@other.test', $other->fresh()->billing_email);
        $this->assertSame('accounts@billing.test', $this->tenant->fresh()->billing_email);
    }

    // ── Billing email ─────────────────────────────────────────────────────

    public function test_changing_the_billing_email_tells_the_old_address_and_is_logged(): void
    {
        $this->updateEmail('  Finance@Billing.test ')->assertSessionHas('success', 'Billing email updated.');

        $this->assertSame('finance@billing.test', $this->tenant->fresh()->billing_email);

        Mail::assertQueued(GeneralMail::class, fn (GeneralMail $mail) => $mail->hasTo('accounts@billing.test')
            && $mail->contentView === 'emails.billing.billing-email-changed'
            && $mail->data['newEmail'] === 'finance@billing.test');
        // Only the old address is written to; the new one gets nothing.
        Mail::assertQueued(GeneralMail::class, 1);

        $log = ActivityLog::where('action', 'billing_email_changed')->sole();
        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertStringContainsString('finance@billing.test', $log->description);
    }

    public function test_the_rendered_notice_names_who_changed_it_and_the_new_address(): void
    {
        $this->updateEmail('finance@billing.test');

        Mail::assertQueued(GeneralMail::class, function (GeneralMail $mail) {
            $html = $mail->render();

            return str_contains($html, 'Billing email changed')
                && str_contains($html, $this->admin->name)
                && str_contains($html, 'finance@billing.test')
                && str_contains($html, 'Billing Co');
        });
    }

    public function test_setting_a_first_billing_email_sends_nothing(): void
    {
        $this->tenant->forceFill(['billing_email' => null])->save();

        $this->updateEmail('first@billing.test')->assertSessionHas('success');

        $this->assertSame('first@billing.test', $this->tenant->fresh()->billing_email);
        Mail::assertNothingQueued();
    }

    public function test_saving_the_same_address_changes_nothing(): void
    {
        $this->updateEmail('ACCOUNTS@billing.test')->assertSessionHas('success', 'The billing email is unchanged.');

        Mail::assertNothingQueued();
        $this->assertSame(0, ActivityLog::where('action', 'billing_email_changed')->count());
    }

    public function test_the_billing_email_must_be_a_valid_address(): void
    {
        foreach (['', 'not-an-email', str_repeat('a', 250).'@example.com'] as $bad) {
            $this->updateEmail($bad)->assertSessionHasErrors('billing_email');
        }

        $this->assertSame('accounts@billing.test', $this->tenant->fresh()->billing_email);
        Mail::assertNothingQueued();
    }

    // ── Users page banner ─────────────────────────────────────────────────

    public function test_the_users_page_gets_the_seat_status_for_its_banner(): void
    {
        $this->setSubscription(['seats' => 2]);
        $this->makeInvitation($this->admin);

        $this->actingAs($this->admin)->get('/admin/users')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('seatStatus.seats', 2)
            ->where('seatStatus.used', 2)
            ->where('seatStatus.can_add_seats', true));

        $this->setSubscription(['status' => TenantSubscription::STATUS_CANCELED]);
        $this->actingAs($this->admin)->get('/admin/users')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('seatStatus.can_add_seats', false));
    }

    public function test_seat_errors_point_to_the_billing_page(): void
    {
        $this->setSubscription(['seats' => 1]);

        $this->actingAs($this->admin)->post('/admin/invitations', ['email' => 'new@example.com', 'role' => 'employee'])
            ->assertSessionHas('error', fn (string $error) => str_contains($error, 'See Billing'));
    }
}
