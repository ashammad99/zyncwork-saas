<?php

namespace App\Features\Onboarding\Services;

use App\Core\BaseService;
use App\Core\Tenancy\TenantContext;
use App\Features\ActivityLog\Events\ActivityLogged;
use App\Features\Auth\Models\User;
use App\Features\Billing\Exceptions\SeatLimitReached;
use App\Features\Billing\Services\BillingNotifier;
use App\Features\Billing\Services\SeatGuard;
use App\Features\Onboarding\Models\UserInvitation;
use App\Features\Tenancy\Models\Tenant;
use App\Mail\GeneralMail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Invitations are the only way to add a user to an existing tenant.
 *
 * The admin side runs in the admin's tenant (the tenant scope applies); the
 * accept side is a guest request, so it finds the invite by its token hash
 * across tenants and then acts inside the invite's tenant.
 */
class InvitationService extends BaseService
{
    public function __construct(
        protected TenantContext $tenantContext,
        protected SeatGuard $seats,
        protected BillingNotifier $billingNotifier,
    ) {}

    /**
     * The per-tenant hourly limit is a route limiter (throttle:invitations), so
     * that rejected attempts count too, not just the invites sent.
     *
     * @throws SeatLimitReached when every seat is used (an open invite holds one)
     */
    public function invite(User $inviter, array $data): UserInvitation
    {
        $token = Str::random(64);

        try {
            $invitation = DB::transaction(function () use ($inviter, $data, $token) {
                $this->seats->ensureCanAdd($inviter->tenant_id);

                return UserInvitation::create([
                    'email' => Str::lower(trim($data['email'])),
                    'role' => $data['role'],
                    'department_id' => $data['department_id'] ?? null,
                    'invited_by' => $inviter->id,
                    'token_hash' => hash('sha256', $token),
                    'expires_at' => now()->addDays((int) config('onboarding.invitations.expire_days')),
                ]);
            });
        } catch (SeatLimitReached $e) {
            // After the rollback, so the notice record isn't rolled back with it.
            if ($e->reason === SeatLimitReached::SEATS_FULL) {
                $this->billingNotifier->seatLimitReached($inviter->tenant_id);
            }

            throw $e;
        }

        $this->sendInvite($invitation, $inviter, $token);
        event(new ActivityLogged($inviter, 'user_invited', "Invited {$invitation->email} as {$invitation->role}.", 'system'));

        return $invitation;
    }

    /** A new token (the old link stops working) and a new expiry. */
    public function resend(UserInvitation $invitation, User $by): void
    {
        if ($invitation->accepted_at !== null || $invitation->revoked_at !== null) {
            throw ValidationException::withMessages(['invitation' => 'This invitation has already been used or revoked.']);
        }

        // Counted before checking (one atomic increment), so parallel clicks can't all get through.
        if (RateLimiter::hit("invitation-resend:{$invitation->id}", 60) > 1) {
            throw ValidationException::withMessages(['invitation' => 'This invitation was just sent. Please wait a minute before sending it again.']);
        }

        $token = Str::random(64);
        $expiresAt = now()->addDays((int) config('onboarding.invitations.expire_days'));

        // Conditional, like accept(): an invite accepted or revoked since it was loaded is left alone.
        if (! $this->whereStillPending($invitation)->update(['token_hash' => hash('sha256', $token), 'expires_at' => $expiresAt])) {
            throw $this->noLongerPending();
        }

        $invitation->refresh();
        $this->sendInvite($invitation, $by, $token);
        event(new ActivityLogged($by, 'user_invite_resent', "Resent the invitation to {$invitation->email}.", 'system'));
    }

    public function revoke(UserInvitation $invitation, User $by): void
    {
        // Conditional, like accept(): if the invitee accepted after this was
        // loaded, the admin is told so instead of seeing a revoke that did nothing.
        if (! $this->whereStillPending($invitation)->update(['revoked_at' => now()])) {
            throw $this->noLongerPending();
        }

        event(new ActivityLogged($by, 'user_invite_revoked', "Revoked the invitation to {$invitation->email}.", 'system'));
    }

    /**
     * An admin who is demoted or suspended can no longer bring people in: their
     * pending invitations are revoked (findOpen also refuses them meanwhile).
     *
     * @return int how many were revoked
     */
    public function revokeAllFrom(User $inviter, User $by): int
    {
        $revoked = UserInvitation::where('invited_by', $inviter->id)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        if ($revoked) {
            event(new ActivityLogged($by, 'user_invites_revoked', "Revoked {$revoked} pending ".Str::plural('invitation', $revoked)." sent by {$inviter->email}.", 'security'));
        }

        return $revoked;
    }

    /**
     * The open invite behind a token, or null when it is unknown, used,
     * revoked, expired, its tenant is suspended, or whoever sent it is no
     * longer an active admin. Callers show one message for all of these, so a
     * link never reveals which it was.
     */
    public function findOpen(string $token): ?UserInvitation
    {
        $invitation = UserInvitation::withoutTenancy()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (! $invitation || ! $invitation->isOpen()) {
            return null;
        }

        $tenant = Tenant::find($invitation->tenant_id);

        if (! $tenant || $tenant->isSuspended()) {
            return null;
        }

        // The invite carries the inviter's authority: it lapses with it.
        $inviter = User::withoutTenancy()->find($invitation->invited_by);

        return $inviter && $inviter->is_active && $inviter->role === 'admin' ? $invitation : null;
    }

    /**
     * Create the invited user (verified: the emailed link proves the address)
     * and use up the invite. Returns null when the invite is no longer open.
     *
     * @throws ValidationException when the email has become taken meanwhile, or no seat is free
     */
    public function accept(string $token, array $data): ?User
    {
        $invitation = $this->findOpen($token);

        if (! $invitation) {
            return null;
        }

        return $this->tenantContext->run($invitation->tenant_id, function () use ($invitation, $data) {
            try {
                return DB::transaction(function () use ($invitation, $data) {
                    // First, so the subscription row is locked before the invitation
                    // row, in the same order as invite(). The plan may have lapsed or
                    // lost seats since the invite was sent (handled below).
                    $this->seats->ensureCanAdd($invitation->tenant_id, excludingInvitationId: $invitation->id);

                    // Claimed atomically, so two requests with the same link create one user.
                    $claimed = UserInvitation::whereKey($invitation->id)
                        ->whereNull('accepted_at')
                        ->whereNull('revoked_at')
                        ->where('expires_at', '>', now())
                        ->update(['accepted_at' => now()]);

                    if (! $claimed) {
                        return null;
                    }

                    // Emails are unique platform-wide: another company may have signed this person up since.
                    if (User::withoutTenancy()->where('email', $invitation->email)->exists()) {
                        throw $this->emailTaken();
                    }

                    $user = User::create([
                        'name' => $data['name'],
                        'email' => $invitation->email,
                        'password' => $data['password'],
                        'role' => $invitation->role,
                        'is_active' => true,
                        'department_id' => $invitation->department_id,
                    ]);
                    $user->forceFill(['email_verified_at' => now()])->save();

                    UserInvitation::whereKey($invitation->id)->update(['accepted_user_id' => $user->id]);

                    event(new ActivityLogged($user, 'invitation_accepted', "Joined as {$user->role} by invitation.", 'security'));

                    return $user;
                });
            } catch (SeatLimitReached $e) {
                // After the rollback, so the notice record isn't rolled back with it.
                // The invitee can't tell the admins themselves, so they are emailed
                // with the reason. The invitee gets the same neutral message
                // whatever the reason: billing details are the tenant's business.
                $this->billingNotifier->invitationBlocked($invitation, $e);

                throw ValidationException::withMessages(['email' => "This company can't add new users right now. Please ask the person who invited you."]);
            } catch (UniqueConstraintViolationException) {
                // The same email was taken between the check and the insert.
                throw $this->emailTaken();
            }
        });
    }

    private function sendInvite(UserInvitation $invitation, User $inviter, string $token): void
    {
        $companyName = Tenant::find($invitation->tenant_id)?->name;

        Mail::to($invitation->email)->send(new GeneralMail(
            mailSubject: __('emails.invitation.subject', ['company' => $companyName, 'app' => config('app.name')]),
            contentView: 'emails.auth.invitation-body',
            data: [
                'companyName' => $companyName,
                'inviterName' => $inviter->name,
                'role' => $invitation->role,
                'actionUrl' => route('invitations.show', $token),
                'expiresAt' => $invitation->expires_at->toFormattedDateString(),
            ]
        ));
    }

    private function whereStillPending(UserInvitation $invitation): Builder
    {
        return UserInvitation::whereKey($invitation->id)
            ->whereNull('accepted_at')
            ->whereNull('revoked_at');
    }

    private function noLongerPending(): ValidationException
    {
        return ValidationException::withMessages(['invitation' => 'This invitation has already been used or revoked.']);
    }

    private function emailTaken(): ValidationException
    {
        return ValidationException::withMessages(['email' => 'This email address already has an account. Ask the person to use a different email.']);
    }
}
