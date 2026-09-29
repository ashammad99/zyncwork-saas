<?php

namespace App\Features\Platform\Services;

use App\Core\BaseService;
use App\Features\Platform\Models\PlatformAdmin;
use App\Features\Platform\Models\PlatformAuditLog;
use App\Features\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Suspending a tenant blocks its users' logins (LoginService) and ends their
 * open sessions on the next request (EnsureUserIsActive). Queued jobs
 * already dispatched still run.
 */
class TenantSuspensionService extends BaseService
{
    public function __construct(
        protected PlatformAuditLogger $audit
    ) {}

    public function suspend(Tenant $tenant, PlatformAdmin $admin, string $reason): void
    {
        DB::transaction(function () use ($tenant, $admin, $reason) {
            $tenant->forceFill(['suspended_at' => now(), 'suspended_reason' => $reason])->save();

            $ref = $this->audit->record(PlatformAuditLog::TENANT_SUSPENDED, $admin, [
                'tenant_id' => $tenant->id,
                'metadata' => ['reason' => $reason],
            ]);

            $this->audit->noteInTenantLog($tenant->id, null, 'tenant_suspended', "Account suspended: {$reason}", $ref);
        });
    }

    public function unsuspend(Tenant $tenant, PlatformAdmin $admin): void
    {
        DB::transaction(function () use ($tenant, $admin) {
            $tenant->forceFill(['suspended_at' => null, 'suspended_reason' => null])->save();

            $ref = $this->audit->record(PlatformAuditLog::TENANT_UNSUSPENDED, $admin, ['tenant_id' => $tenant->id]);

            $this->audit->noteInTenantLog($tenant->id, null, 'tenant_unsuspended', 'Account suspension lifted', $ref);
        });
    }
}
