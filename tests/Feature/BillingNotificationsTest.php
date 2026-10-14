<?php

namespace Tests\Feature;

use App\Core\Tenancy\TenantContext;
use App\Features\Auth\Models\User;
use App\Features\Billing\Models\TenantBillingNotice;
use App\Features\Billing\Models\TenantSubscription;
use App\Features\Onboarding\Models\UserInvitation;
use App\Features\Tenancy\Models\Tenant;
use App\Mail\GeneralMail;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Billing emails (Phase 1D, PR 2b): trial reminders 3 days and 1 day before
 * the end, trial ended, seat limit reached (at most one per tenant per 24h),
 * and an invitation refused by the plan. Each goes to billing_email, or to
 * the tenant's active, verified admins, once per occurrence, and never to a
 * suspended tenant or an unverified signup.
 */
class BillingNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-14 10:00:00');
        Mail::fake();

        // Created after setTestNow, so its trial ends 2026-10-28 10:00.
        $this->tenant = $this->makeTenant('Acme', 'billing@acme.test');
        app(TenantContext::class)->set($this->tenant->id);
        $this->admin = $this->makeUser($this->tenant, 'admin');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeTenant(string $name, ?string $billingEmail): Tenant
    {
        $tenant = Tenant::create(['name' => $name]);
        $tenant->forceFill(['billing_email' => $billingEmail])->save();

        return $tenant;
    }

    private function makeUser(Tenant $tenant, string $role = 'employee', bool $active = true, bool $verified = true): User
    {
        return app(TenantContext::class)->run($tenant->id, function () use ($role, $active, $verified) {
            $user = User::create([
                'name' => "Tenant {$role}",
                'email' => uniqid("{$role}_").'@example.com',
                'password' => 'password',
                'role' => $role,
                'is_active' => $active,
            ]);

            if (! $verified) {
                $user->forceFill(['email_verified_at' => null])->save();
            }

            return $user;
        });
    }

    private function setSubscription(array $attributes, ?Tenant $tenant = null): void
    {
        TenantSubscription::where('tenant_id', ($tenant ?? $this->tenant)->id)->sole()->forceFill($attributes)->save();
    }

    /** Like the scheduler: no tenant context. */
    private function runReminders(): void
    {
        $context = app(TenantContext::class);
        $previous = $context->id();
        $context->forget();

        $this->artisan('billing:send-reminders')->assertSuccessful();

        $context->set($previous);
    }

    /** @return Collection<int, GeneralMail> */
    private function mails(string $view): Collection
    {
        return Mail::queued(GeneralMail::class, fn (GeneralMail $mail) => $mail->contentView === $view);
    }

    /** @return array<int, string> one entry per queued email, its recipient */
    private function recipientsOf(string $view): array
    {
        return $this->mails($view)
            ->map(fn (GeneralMail $mail) => $mail->to[0]['address'])
            ->sort()->values()->all();
    }

    private function invite(string $email = 'new.person@example.com'): TestResponse
    {
        return $this->actingAs($this->admin)->post('/admin/invitations', ['email' => $email, 'role' => 'employee']);
    }

    private function reactivate(User $user): TestResponse
    {
        return $this->actingAs($this->admin)->put("/admin/users/{$user->id}", [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => true,
            'daily_capacity_hours' => 8,
        ]);
    }

    private function makeInvitation(string $token): UserInvitation
    {
        return app(TenantContext::class)->run($this->tenant->id, fn () => UserInvitation::create([
            'email' => 'invitee@example.com',
            'role' => 'employee',
            'invited_by' => $this->admin->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(7),
        ]));
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

    // ── Trial reminders ───────────────────────────────────────────────────

    public function test_the_3_day_reminder_goes_to_the_billing_email_once(): void
    {
        Carbon::setTestNow('2026-10-25 11:00:00'); // 2 days 23 hours left

        $this->runReminders();
        $this->runReminders();
        Carbon::setTestNow('2026-10-25 12:00:00');
        $this->runReminders();

        $this->assertSame(['billing@acme.test'], $this->recipientsOf('emails.billing.trial-ending'));
        $mail = $this->mails('emails.billing.trial-ending')->first();
        $this->assertSame('Your '.config('app.name').' trial ends on 28 October 2026', $mail->mailSubject);
        $this->assertSame('Acme', $mail->data['companyName']);
        $this->assertSame(route('admin.billing.index'), $mail->data['actionUrl']);
        $this->assertSame(1, TenantBillingNotice::where('kind', TenantBillingNotice::KIND_TRIAL_ENDS_3_DAYS)->count());
    }

    public function test_nothing_is_sent_more_than_3_days_before_the_end(): void
    {
        Carbon::setTestNow('2026-10-25 09:59:59');

        $this->runReminders();

        Mail::assertNothingQueued();
        $this->assertSame(0, TenantBillingNotice::count());
    }

    public function test_the_1_day_reminder_follows_the_3_day_one(): void
    {
        Carbon::setTestNow('2026-10-25 11:00:00');
        $this->runReminders();
        Carbon::setTestNow('2026-10-27 11:00:00'); // 23 hours left
        $this->runReminders();
        $this->runReminders();

        $this->assertCount(2, $this->mails('emails.billing.trial-ending'));
        $this->assertEqualsCanonicalizing(
            [TenantBillingNotice::KIND_TRIAL_ENDS_3_DAYS, TenantBillingNotice::KIND_TRIAL_ENDS_1_DAY],
            TenantBillingNotice::pluck('kind')->all()
        );
    }

    public function test_a_tenant_found_late_gets_only_the_latest_reminder(): void
    {
        Carbon::setTestNow('2026-10-27 20:00:00'); // 14 hours left: the 3-day one is no longer useful

        $this->runReminders();

        $this->assertSame([TenantBillingNotice::KIND_TRIAL_ENDS_1_DAY], TenantBillingNotice::pluck('kind')->all());
        $this->assertCount(1, $this->mails('emails.billing.trial-ending'));
    }

    public function test_the_trial_ended_email_is_sent_once_after_the_end(): void
    {
        Carbon::setTestNow('2026-10-28 10:00:00'); // exactly the end
        $this->runReminders();
        Carbon::setTestNow('2026-10-29 10:00:00');
        $this->runReminders();

        $this->assertSame(['billing@acme.test'], $this->recipientsOf('emails.billing.trial-expired'));
        $this->assertCount(0, $this->mails('emails.billing.trial-ending'));
    }

    public function test_no_trial_ended_email_for_a_trial_that_ended_long_ago(): void
    {
        Carbon::setTestNow('2026-10-31 10:00:01'); // just over trial_expired_notice_days (3) after the end

        $this->runReminders();

        Mail::assertNothingQueued();
    }

    public function test_an_extended_trial_gets_new_reminders(): void
    {
        Carbon::setTestNow('2026-10-25 11:00:00');
        $this->runReminders();

        $this->setSubscription(['trial_ends_at' => Carbon::parse('2026-11-04 10:00:00')]);
        Carbon::setTestNow('2026-11-01 11:00:00');
        $this->runReminders();
        $this->runReminders();

        $this->assertCount(2, $this->mails('emails.billing.trial-ending'));
        $this->assertSame(['2026-10-28 10:00:00', '2026-11-04 10:00:00'], TenantBillingNotice::orderBy('id')->pluck('period_key')->all());
    }

    public function test_no_trial_emails_for_a_paid_subscription(): void
    {
        $this->setSubscription(['status' => TenantSubscription::STATUS_ACTIVE]);

        foreach (['2026-10-25 11:00:00', '2026-10-27 11:00:00', '2026-10-28 11:00:00'] as $time) {
            Carbon::setTestNow($time);
            $this->runReminders();
        }

        Mail::assertNothingQueued();
    }

    public function test_the_notices_table_refuses_a_second_row_for_the_same_occurrence(): void
    {
        $row = ['tenant_id' => $this->tenant->id, 'kind' => TenantBillingNotice::KIND_TRIAL_EXPIRED, 'period_key' => '2026-10-28 10:00:00', 'sent_at' => now()];
        DB::table('tenant_billing_notices')->insert($row);

        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('tenant_billing_notices')->insert($row);
    }

    // ── Recipients and skips ─────────────────────────────────────────────

    public function test_without_a_billing_email_the_active_verified_admins_get_it(): void
    {
        $this->tenant->forceFill(['billing_email' => null])->save();
        $secondAdmin = $this->makeUser($this->tenant, 'admin');
        $this->makeUser($this->tenant, 'admin', active: false);
        $this->makeUser($this->tenant, 'admin', verified: false);
        $this->makeUser($this->tenant, 'manager');
        $this->makeUser($this->tenant, 'employee');

        Carbon::setTestNow('2026-10-25 11:00:00');
        $this->runReminders();

        $this->assertEqualsCanonicalizing([$this->admin->email, $secondAdmin->email], $this->recipientsOf('emails.billing.trial-ending'));
    }

    public function test_with_nobody_to_send_to_nothing_is_recorded_so_it_goes_out_later(): void
    {
        $this->tenant->forceFill(['billing_email' => null])->save();
        $this->admin->forceFill(['email_verified_at' => null])->save();

        Carbon::setTestNow('2026-10-25 11:00:00');
        $this->runReminders();
        Mail::assertNothingQueued();
        $this->assertSame(0, TenantBillingNotice::count());

        $this->admin->forceFill(['email_verified_at' => now()])->save();
        $this->runReminders();

        $this->assertSame([$this->admin->email], $this->recipientsOf('emails.billing.trial-ending'));
    }

    public function test_suspended_tenants_get_nothing(): void
    {
        $this->tenant->forceFill(['suspended_at' => now()])->save();

        Carbon::setTestNow('2026-10-25 11:00:00');
        $this->runReminders();

        Mail::assertNothingQueued();
        $this->assertSame(0, TenantBillingNotice::count());
    }

    public function test_unverified_signups_get_nothing(): void
    {
        $this->tenant->forceFill(['signup_expires_at' => now()->addDays(30)])->save();

        Carbon::setTestNow('2026-10-25 11:00:00');
        $this->runReminders();

        Mail::assertNothingQueued();
    }

    public function test_each_tenant_gets_only_its_own_reminder(): void
    {
        $other = $this->makeTenant('Globex', null);
        $otherAdmin = $this->makeUser($other, 'admin');
        $this->setSubscription(['trial_ends_at' => Carbon::parse('2026-10-26 09:00:00')], $other);

        Carbon::setTestNow('2026-10-25 11:00:00'); // Acme: 3 days; Globex: 1 day
        $this->runReminders();
        $this->runReminders();

        $mails = $this->mails('emails.billing.trial-ending');
        $this->assertCount(2, $mails);
        $byRecipient = $mails->mapWithKeys(fn (GeneralMail $mail) => [$mail->to[0]['address'] => $mail]);
        $this->assertSame('Acme', $byRecipient['billing@acme.test']->data['companyName']);
        $this->assertSame('Globex', $byRecipient[$otherAdmin->email]->data['companyName']);
        // Acme's admin isn't a recipient (Acme has a billing email); Globex's billing goes only to Globex's admin.
        $this->assertArrayNotHasKey($this->admin->email, $byRecipient->all());
        $this->assertSame([TenantBillingNotice::KIND_TRIAL_ENDS_3_DAYS], TenantBillingNotice::where('tenant_id', $this->tenant->id)->pluck('kind')->all());
        $this->assertSame([TenantBillingNotice::KIND_TRIAL_ENDS_1_DAY], TenantBillingNotice::where('tenant_id', $other->id)->pluck('kind')->all());
    }

    // ── Seat limit ────────────────────────────────────────────────────────

    public function test_a_refused_invite_sends_the_seat_limit_email_at_most_once_a_day(): void
    {
        $this->setSubscription(['seats' => 1]); // the admin uses it

        $this->invite()->assertSessionHas('error');
        $this->invite('second@example.com')->assertSessionHas('error');
        Carbon::setTestNow('2026-10-15 09:59:00'); // 23h59m later
        $this->invite('third@example.com')->assertSessionHas('error');

        $this->assertSame(['billing@acme.test'], $this->recipientsOf('emails.billing.seat-limit'));
        $this->assertSame(1, $this->mails('emails.billing.seat-limit')->first()->data['seats']);

        Carbon::setTestNow('2026-10-15 10:00:01'); // over 24h after the first
        $this->invite('fourth@example.com')->assertSessionHas('error');

        $this->assertCount(2, $this->mails('emails.billing.seat-limit'));
    }

    public function test_a_refused_reactivation_sends_the_seat_limit_email(): void
    {
        $this->setSubscription(['seats' => 1]);
        $inactive = $this->makeUser($this->tenant, active: false);

        $this->reactivate($inactive)->assertSessionHas('error');

        $this->assertSame(['billing@acme.test'], $this->recipientsOf('emails.billing.seat-limit'));
        $this->assertFalse($inactive->fresh()->is_active);
    }

    public function test_an_invite_refused_for_an_ended_trial_sends_no_seat_limit_email(): void
    {
        $this->setSubscription(['trial_ends_at' => now()->subMinute()]);

        $this->invite()->assertSessionHas('error');

        $this->assertCount(0, $this->mails('emails.billing.seat-limit'));
    }

    public function test_the_seat_limit_cooldown_is_per_tenant(): void
    {
        $other = $this->makeTenant('Globex', 'billing@globex.test');
        $otherAdmin = $this->makeUser($other, 'admin');
        $this->setSubscription(['seats' => 1]);
        $this->setSubscription(['seats' => 1], $other);

        $this->invite()->assertSessionHas('error');
        $this->actingAs($otherAdmin)->post('/admin/invitations', ['email' => 'x@example.com', 'role' => 'employee'])
            ->assertSessionHas('error');

        $this->assertEqualsCanonicalizing(['billing@acme.test', 'billing@globex.test'], $this->recipientsOf('emails.billing.seat-limit'));
    }

    public function test_a_successful_invite_sends_no_billing_email(): void
    {
        $this->invite()->assertSessionHas('success');

        $this->assertSame(0, TenantBillingNotice::count());
        $this->assertCount(0, $this->mails('emails.billing.seat-limit'));
    }

    // ── Invitation blocked ───────────────────────────────────────────────

    public function test_an_invitation_refused_by_the_plan_tells_the_tenant_once(): void
    {
        $this->setSubscription(['seats' => 2]);
        $this->makeInvitation('the-token');
        $this->makeUser($this->tenant); // admin + this user fill both seats

        $this->accept('the-token')->assertSessionHasErrors('email');
        $this->accept('the-token')->assertSessionHasErrors('email');

        $this->assertSame(['billing@acme.test'], $this->recipientsOf('emails.billing.invitation-blocked'));
        $mail = $this->mails('emails.billing.invitation-blocked')->first();
        $this->assertSame('invitee@example.com', $mail->data['invitedEmail']);
        $this->assertSame('every seat is in use.', $mail->data['reason']);
        // The invitee's refusal isn't an admin's: no seat-limit email.
        $this->assertCount(0, $this->mails('emails.billing.seat-limit'));
        $this->assertNull(app(TenantContext::class)->run($this->tenant->id, fn () => UserInvitation::sole()->accepted_at));
    }

    public function test_the_invitation_blocked_email_names_an_ended_trial(): void
    {
        $this->makeInvitation('the-token');
        $this->setSubscription(['trial_ends_at' => now()->subMinute()]);

        $this->accept('the-token')->assertSessionHasErrors('email');

        $this->assertSame('the trial has ended.', $this->mails('emails.billing.invitation-blocked')->sole()->data['reason']);
    }

    public function test_no_invitation_blocked_email_for_a_suspended_tenant(): void
    {
        // findOpen refuses invitations of a suspended tenant before the seat check, so nothing is sent.
        $this->setSubscription(['seats' => 1]);
        $this->makeInvitation('the-token');
        $this->tenant->forceFill(['suspended_at' => now()])->save();

        $this->accept('the-token');

        $this->assertCount(0, $this->mails('emails.billing.invitation-blocked'));
    }
}
