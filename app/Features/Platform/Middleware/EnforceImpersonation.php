<?php

namespace App\Features\Platform\Middleware;

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
 * - blocks changing the impersonated account's own credentials;
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

        if ($this->changesOwnCredentials($request, $impersonation)) {
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

    private function endAndLeave(Request $request, ?PlatformImpersonation $impersonation): Response
    {
        if ($impersonation) {
            $this->impersonations->end($impersonation, PlatformImpersonation::END_EXPIRED);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $target = $impersonation
            ? route('platform.tenants.show', $impersonation->tenant_id)
            : route('login');

        return $request->header('X-Inertia') ? Inertia::location($target) : redirect()->away($target);
    }

    private function changesOwnCredentials(Request $request, PlatformImpersonation $impersonation): bool
    {
        $name = $request->route()?->getName();

        if (in_array($name, self::OWN_CREDENTIAL_ROUTES, true)) {
            return true;
        }

        // Tenant admins can edit any user's email/password there, including their own.
        if ($name === 'admin.users.update') {
            $user = $request->route('user');
            $userId = $user instanceof Model ? $user->getKey() : $user;

            return (int) $userId === (int) $impersonation->target_user_id;
        }

        return false;
    }

    /** @return array<string, mixed> */
    private function routeKeys(Request $request): array
    {
        return collect($request->route()?->parameters() ?? [])
            ->map(fn ($value) => $value instanceof Model ? $value->getKey() : (is_scalar($value) ? $value : null))
            ->all();
    }
}
