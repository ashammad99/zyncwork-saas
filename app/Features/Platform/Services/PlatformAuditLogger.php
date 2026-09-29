<?php

namespace App\Features\Platform\Services;

use App\Core\BaseService;
use App\Core\Tenancy\TenantContext;
use App\Features\ActivityLog\Models\ActivityLog;
use App\Features\Platform\Models\PlatformAdmin;
use App\Features\Platform\Models\PlatformAuditLog;

/**
 * Writes both halves of the platform audit trail:
 * - the full record in platform_audit_logs (platform-only);
 * - a short line in the tenant's own activity log whenever platform staff
 *   touch that tenant, so the tenant can see it happened.
 */
class PlatformAuditLogger extends BaseService
{
    /**
     * @param  array{tenant_id?: int|null, target_user_id?: int|null, impersonation_id?: int|null, metadata?: array|null}  $context
     */
    public function record(string $action, ?PlatformAdmin $admin, array $context = []): PlatformAuditLog
    {
        return PlatformAuditLog::create([
            'platform_admin_id' => $admin?->id,
            'action' => $action,
            'tenant_id' => $context['tenant_id'] ?? null,
            'target_user_id' => $context['target_user_id'] ?? null,
            'impersonation_id' => $context['impersonation_id'] ?? null,
            'metadata' => $context['metadata'] ?? null,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Add one line to the tenant's activity log, attributed to platform
     * support rather than to a staff member by name. The reference points
     * support back at the full platform_audit_logs entry.
     *
     * Written directly rather than through ActivityLogged, whose listener
     * needs a tenant user, and inside the tenant's context, since a
     * platform request has no tenant of its own.
     */
    public function noteInTenantLog(int $tenantId, ?int $userId, string $action, string $description, PlatformAuditLog $ref): void
    {
        app(TenantContext::class)->run($tenantId, fn () => ActivityLog::create([
            'user_id' => $userId,
            'platform_actor' => config('platform.actor_label'),
            'action' => $action,
            'description' => "{$description} (ref #{$ref->id})",
            'subject_type' => 'platform',
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]));
    }
}
