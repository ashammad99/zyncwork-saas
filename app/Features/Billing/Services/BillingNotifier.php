<?php

namespace App\Features\Billing\Services;

use App\Core\BaseService;
use App\Core\Tenancy\TenantContext;
use App\Features\Auth\Models\User;
use App\Features\Billing\Exceptions\SeatLimitReached;
use App\Features\Billing\Models\TenantBillingNotice;
use App\Features\Billing\Models\TenantSubscription;
use App\Features\Onboarding\Models\UserInvitation;
use App\Features\SystemSettings\Admin\Models\Setting;
use App\Features\Tenancy\Models\Tenant;
use App\Mail\GeneralMail;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Billing emails to a tenant: trial reminders, trial ended, seat limit
 * reached, and an invitation that couldn't be accepted.
 *
 * Each goes to the tenant's billing_email, or, when that is empty, to its
 * active, verified admins. Nothing is sent to a suspended tenant or to a
 * signup that hasn't verified yet.
 *
 * Every email is recorded in tenant_billing_notices before it is queued; the
 * unique key there makes a repeat a no-op, so each occurrence is sent at most
 * once (if queueing fails after the row is written, that one is lost rather
 * than sent twice). Call these outside any transaction that may roll back, or
 * the record and the queued email go with it.
 */
class BillingNotifier extends BaseService
{
    public function __construct(
        protected TenantContext $tenantContext,
    ) {}

    /**
     * Trial reminders 3 days and 1 day before the end, and "trial ended" once
     * it has passed. Run hourly by billing:send-reminders. Only the latest
     * stage that applies is sent, so a tenant found 20 hours before the end
     * gets the 1-day reminder, not both.
     *
     * @return int how many tenants were emailed
     */
    public function sendTrialReminders(): int
    {
        $now = now();
        $sent = 0;

        TenantSubscription::with('tenant')
            ->where('status', TenantSubscription::STATUS_TRIALING)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '>', $now->copy()->subDays((int) config('billing.trial_expired_notice_days')))
            ->where('trial_ends_at', '<=', $now->copy()->addDays(3))
            ->lazyById()
            ->each(function (TenantSubscription $subscription) use ($now, &$sent) {
                $endsAt = $subscription->trial_ends_at;

                $kind = match (true) {
                    $endsAt->lte($now) => TenantBillingNotice::KIND_TRIAL_EXPIRED,
                    $endsAt->lte($now->copy()->addDay()) => TenantBillingNotice::KIND_TRIAL_ENDS_1_DAY,
                    default => TenantBillingNotice::KIND_TRIAL_ENDS_3_DAYS,
                };

                $expired = $kind === TenantBillingNotice::KIND_TRIAL_EXPIRED;
                $date = $this->formatDate($subscription->tenant, $endsAt);

                // Keyed by the end time, so an extended trial gets its reminders again.
                $sent += (int) $this->notify($subscription->tenant, $kind, $endsAt->format('Y-m-d H:i:s'),
                    __($expired ? 'billing.trial_expired.subject' : 'billing.trial_ending.subject', ['app' => config('app.name'), 'date' => $date]),
                    $expired ? 'emails.billing.trial-expired' : 'emails.billing.trial-ending',
                    ['date' => $date],
                );
            });

        return $sent;
    }

    /**
     * An admin was refused an invite or a reactivation because every seat is
     * used. At most one email per tenant per 24 hours, however many refusals.
     */
    public function seatLimitReached(int $tenantId): void
    {
        $tenant = Tenant::find($tenantId);
        $seats = (int) TenantSubscription::where('tenant_id', $tenantId)->value('seats');

        if (! $tenant || ! $this->shouldNotify($tenant)) {
            return;
        }

        // The tenant row is locked so two refusals at once can't both pass the cooldown check.
        $claimed = DB::transaction(function () use ($tenant) {
            Tenant::whereKey($tenant->id)->lockForUpdate()->first();

            $recent = TenantBillingNotice::where('tenant_id', $tenant->id)
                ->where('kind', TenantBillingNotice::KIND_SEAT_LIMIT)
                ->where('sent_at', '>', now()->subDay())
                ->exists();

            return ! $recent && $this->claim($tenant, TenantBillingNotice::KIND_SEAT_LIMIT, now()->format('Y-m-d H:i:s.u'));
        });

        if ($claimed) {
            $this->send($tenant,
                __('billing.seat_limit.subject', ['app' => config('app.name'), 'company' => $tenant->name]),
                'emails.billing.seat-limit',
                ['seats' => $seats],
            );
        }
    }

    /**
     * Someone tried to accept an invitation and the plan refused it (no free
     * seat, trial ended, plan inactive). They can't tell the admins
     * themselves, so the tenant is told, once per invitation.
     */
    public function invitationBlocked(UserInvitation $invitation, SeatLimitReached $reason): void
    {
        $tenant = Tenant::find($invitation->tenant_id);

        if (! $tenant) {
            return;
        }

        $this->notify($tenant, TenantBillingNotice::KIND_INVITATION_BLOCKED, (string) $invitation->id,
            __('billing.invitation_blocked.subject', ['app' => config('app.name'), 'company' => $tenant->name]),
            'emails.billing.invitation-blocked',
            [
                'invitedEmail' => $invitation->email,
                'reason' => __('billing.invitation_blocked.reasons.'.$reason->reason),
                'expiresAt' => $this->formatDate($tenant, $invitation->expires_at),
            ],
        );
    }

    /**
     * billing_email, or the tenant's active, verified admins when it is empty.
     *
     * @return array<int, string>
     */
    public function recipients(Tenant $tenant): array
    {
        if (filled($tenant->billing_email)) {
            return [$tenant->billing_email];
        }

        return $this->tenantContext->run($tenant->id, fn () => User::where('role', 'admin')
            ->where('is_active', true)
            ->whereNotNull('email_verified_at')
            ->orderBy('id')
            ->pluck('email')
            ->all());
    }

    /**
     * The date in the tenant's own timezone (its "Default Timezone" setting)
     * or UTC, named next to it: "28 October 2026 (UTC)".
     */
    private function formatDate(Tenant $tenant, CarbonInterface $date): string
    {
        $zone = $this->tenantContext->run($tenant->id, fn () => Setting::get('app.timezone'));

        if (! is_string($zone) || ! in_array($zone, timezone_identifiers_list(), true)) {
            $zone = 'UTC';
        }

        return __('billing.date_in_zone', [
            'date' => $date->copy()->setTimezone($zone)->translatedFormat('j F Y'),
            'zone' => $zone,
        ]);
    }

    private function shouldNotify(Tenant $tenant): bool
    {
        return ! $tenant->isSuspended() && $tenant->signup_expires_at === null;
    }

    /** @return bool whether it was sent (false: skipped, or already sent for this occurrence) */
    private function notify(Tenant $tenant, string $kind, string $periodKey, string $subject, string $view, array $data): bool
    {
        if (! $this->shouldNotify($tenant) || ! $this->claim($tenant, $kind, $periodKey)) {
            return false;
        }

        return $this->send($tenant, $subject, $view, $data);
    }

    /** Records the notice; false when it was already recorded (the unique key). */
    private function claim(Tenant $tenant, string $kind, string $periodKey): bool
    {
        // With nobody to send to, nothing is recorded, so it goes out once someone can receive it.
        if ($this->recipients($tenant) === []) {
            return false;
        }

        return DB::table('tenant_billing_notices')->insertOrIgnore([
            'tenant_id' => $tenant->id,
            'kind' => $kind,
            'period_key' => $periodKey,
            'sent_at' => now(),
        ]) === 1;
    }

    private function send(Tenant $tenant, string $subject, string $view, array $data): bool
    {
        foreach ($this->recipients($tenant) as $email) {
            Mail::to($email)->send(new GeneralMail(
                mailSubject: $subject,
                contentView: $view,
                data: array_merge($data, [
                    'companyName' => $tenant->name,
                    'actionUrl' => route('admin.billing.index'),
                ]),
            ));
        }

        return true;
    }
}
