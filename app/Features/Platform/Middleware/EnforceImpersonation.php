<?php

namespace App\Features\Platform\Middleware;

use App\Features\Platform\Models\PlatformAdmin;
use App\Features\Platform\Models\PlatformAuditLog;
use App\Features\Platform\Models\PlatformImpersonation;
use App\Features\Platform\Services\ImpersonationService;
use App\Features\Platform\Services\PlatformAuditLogger;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tenant host, during a support session (session has impersonation_id):
 * - ends the session once it expires or no longer matches the signed-in user;
 * - ends it once the platform admin is deactivated or loses the impersonate capability;
 * - blocks changing the impersonated account's own credentials, and all user management;
 * - records every write request in platform_audit_logs (route and IDs, never bodies).
 */
class EnforceImpersonation
{
    /** Routes that change the signed-in user's own email or password. */
    private const OWN_CREDENTIAL_ROUTES = [
        'admin.profile.update',
        'admin.profile.password.update',
        'user.profile.update',
        'user.profile.password.update',
    ];

    /**
     * Routes that create or change any tenant user (credentials, role, active
     * flag). Blocked outright, whoever the target: otherwise support could
     * create or take over an account and sign in outside the time-limited,
     * audited session. Add any new route that writes users or roles here.
     */
    private const USER_MANAGEMENT_ROUTES = [
        'admin.users.store',
        'admin.users.update',
    ];

    public function __construct(
        protected ImpersonationService $impersonations,
        protected PlatformAuditLogger $audit,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->hasSession() ? $request->session()->get('impersonation_id') : null;

        if (! $id) {
            return $next($request);
        }

        $impersonation = PlatformImpersonation::find($id);

        if (! $impersonation || ! $impersonation->isLive() || (int) Auth::guard('web')->id() !== (int) $impersonation->target_user_id) {
            return $this->endAndLeave($request, $impersonation);
        }

        // Checked every request: deactivating the admin or removing the capability ends it at once.
        if (! $impersonation->platformAdmin?->hasCapability(PlatformAdmin::IMPERSONATE)) {
            return $this->endAndLeave($request, $impersonation, PlatformImpersonation::END_REVOKED);
        }

        if ($this->isBlocked($request)) {
            abort(403, 'Not allowed during a support session.');
        }

        $response = $next($request);

        // A request that ended the session (stop, logout) is covered by the end entry.
        if (! $request->isMethodSafe() && (int) $request->session()->get('impersonation_id') === (int) $impersonation->id) {
            $this->audit->record(PlatformAuditLog::IMPERSONATION_ACTION, $impersonation->platformAdmin, [
                'tenant_id' => $impersonation->tenant_id,
                'target_user_id' => $impersonation->target_user_id,
                'impersonation_id' => $impersonation->id,
                'metadata' => [
                    'method' => $request->method(),
                    'route' => $request->route()?->getName(),
                    'parameters' => $this->routeKeys($request),
                    'status' => $response->getStatusCode(),
                ],
            ]);
        }

        return $response;
    }

    private function endAndLeave(Request $request, ?PlatformImpersonation $impersonation, string $reason = PlatformImpersonation::END_EXPIRED): Response
    {
        if ($impersonation) {
            $this->impersonations->end($impersonation, $reason);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $target = $impersonation
            ? route('platform.tenants.show', $impersonation->tenant_id)
            : route('login');

        return $request->header('X-Inertia') ? Inertia::location($target) : redirect()->away($target);
    }

    private function isBlocked(Request $request): bool
    {
        $name = $request->route()?->getName();

        return in_array($name, self::OWN_CREDENTIAL_ROUTES, true)
            || in_array($name, self::USER_MANAGEMENT_ROUTES, true);
    }

    /** @return array<string, mixed> */
    private function routeKeys(Request $request): array
    {
        return collect($request->route()?->parameters() ?? [])
            ->map(fn ($value) => $value instanceof Model ? $value->getKey() : (is_scalar($value) ? $value : null))
            ->all();
    }
}
