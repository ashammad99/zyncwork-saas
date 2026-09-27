<?php

namespace App\Features\Platform\Services;

use App\Core\BaseService;
use App\Core\Tenancy\TenantContext;
use App\Features\Auth\Models\User;
use App\Features\Platform\Models\PlatformAdmin;
use App\Features\Platform\Models\PlatformAuditLog;
use App\Features\Platform\Models\PlatformImpersonation;
use App\Features\Tenancy\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Support sessions: a platform admin acting as one tenant user.
 *
 * The platform and tenant apps live on different hosts with separate
 * sessions, so the hand-off is a one-time token: the platform side creates
 * it (request), the tenant side swaps it for a web-guard login (consume).
 * The platform session never reaches the tenant host, and vice versa.
 */
class ImpersonationService extends BaseService
{
    public function __construct(
        protected PlatformAuditLogger $audit,
        protected TenantContext $tenantContext,
    ) {}

    /**
     * Create a pending session and return the plain hand-off token.
     *
     * @throws ValidationException when the tenant is suspended or the user inactive
     */
    public function request(PlatformAdmin $admin, Tenant $tenant, int $userId, string $reason): string
    {
        $user = $this->tenantContext->run($tenant->id, fn () => User::findOrFail($userId));

        $this->ensureAllowed($tenant, $user);

        $token = Str::random(64);

        DB::transaction(function () use ($admin, $tenant, $user, $reason, $token) {
            $impersonation = PlatformImpersonation::create([
                'platform_admin_id' => $admin->id,
                'tenant_id' => $tenant->id,
                'target_user_id' => $user->id,
                'reason' => $reason,
                'token_hash' => hash('sha256', $token),
                'token_expires_at' => now()->addSeconds(config('platform.impersonation.token_ttl')),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            $this->audit->record(PlatformAuditLog::IMPERSONATION_REQUESTED, $admin, [
                'tenant_id' => $tenant->id,
                'target_user_id' => $user->id,
                'impersonation_id' => $impersonation->id,
                'metadata' => ['reason' => $reason, 'user_email' => $user->email],
            ]);
        });

        return $token;
    }

    /**
     * Swap a hand-off token for a web-guard login as the target user.
     * Returns null when the token is unknown, used, expired, or no longer allowed.
     */
    public function consume(string $token, Request $request): ?PlatformImpersonation
    {
        $impersonation = PlatformImpersonation::where('token_hash', hash('sha256', $token))->first();

        if (! $impersonation || $impersonation->token_expires_at->isPast()) {
            return null;
        }

        // Claim the token atomically so it can only ever be used once.
        $claimed = PlatformImpersonation::whereKey($impersonation->id)
            ->whereNull('consumed_at')
            ->whereNull('ended_at')
            ->update([
                'consumed_at' => now(),
                'expires_at' => now()->addMinutes(config('platform.impersonation.max_minutes')),
            ]);

        if (! $claimed) {
            return null;
        }

        $impersonation->refresh();
        $tenant = Tenant::find($impersonation->tenant_id);
        $user = $this->tenantContext->run($impersonation->tenant_id, fn () => User::find($impersonation->target_user_id));

        if (! $tenant || ! $user || $tenant->isSuspended() || ! $user->is_active) {
            $this->end($impersonation, PlatformImpersonation::END_EXPIRED);

            return null;
        }

        // Start from a clean tenant session: whatever this browser was signed in as is dropped.
        if ($previous = $request->session()->get('impersonation_id')) {
            if ($old = PlatformImpersonation::find($previous)) {
                $this->end($old, PlatformImpersonation::END_REPLACED);
            }
        }
        $request->session()->invalidate();

        // Not LoginService: no last_login_* update and no "user logged in" entry.
        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->put('impersonation_id', $impersonation->id);

        $ref = $this->audit->record(PlatformAuditLog::IMPERSONATION_STARTED, $impersonation->platformAdmin, [
            'tenant_id' => $impersonation->tenant_id,
            'target_user_id' => $user->id,
            'impersonation_id' => $impersonation->id,
        ]);

        $this->audit->noteInTenantLog(
            $impersonation->tenant_id,
            $user->id,
            'support_session_started',
            "Support session started as {$user->name}. Reason: {$impersonation->reason}",
            $ref
        );

        return $impersonation;
    }

    /** Idempotent: a session is only ever ended (and logged) once. */
    public function end(PlatformImpersonation $impersonation, string $reason): void
    {
        $ended = PlatformImpersonation::whereKey($impersonation->id)
            ->whereNull('ended_at')
            ->update(['ended_at' => now(), 'end_reason' => $reason]);

        if (! $ended) {
            return;
        }

        $impersonation->refresh();

        $actions = PlatformAuditLog::where('impersonation_id', $impersonation->id)
            ->where('action', PlatformAuditLog::IMPERSONATION_ACTION)
            ->count();

        $ref = $this->audit->record(PlatformAuditLog::IMPERSONATION_ENDED, $impersonation->platformAdmin, [
            'tenant_id' => $impersonation->tenant_id,
            'target_user_id' => $impersonation->target_user_id,
            'impersonation_id' => $impersonation->id,
            'metadata' => ['end_reason' => $reason, 'actions' => $actions],
        ]);

        // A token that was never used gave no access, so the tenant has nothing to see.
        if ($impersonation->consumed_at !== null) {
            $this->audit->noteInTenantLog(
                $impersonation->tenant_id,
                $impersonation->target_user_id,
                'support_session_ended',
                "Support session ended ({$reason}); {$actions} ".Str::plural('change', $actions).' made',
                $ref
            );
        }
    }

    private function ensureAllowed(Tenant $tenant, User $user): void
    {
        if ($tenant->isSuspended()) {
            throw ValidationException::withMessages(['user_id' => 'This tenant is suspended.']);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages(['user_id' => 'This user is inactive.']);
        }
    }
}
