<?php

namespace App\Features\Onboarding\Services;

use App\Core\BaseService;
use App\Core\Tenancy\TenantContext;
use App\Features\ActivityLog\Events\ActivityLogged;
use App\Features\Auth\Models\User;
use App\Features\Tenancy\Models\Tenant;
use Illuminate\Support\Facades\DB;

/**
 * Public company signup: a new tenant and its first admin.
 *
 * The admin starts unverified. EnsureEmailIsVerified keeps the account to
 * the verify screen until the emailed link is clicked.
 */
class SignupService extends BaseService
{
    public function __construct(
        protected TenantContext $tenantContext,
    ) {}

    public function register(array $data): User
    {
        $user = DB::transaction(function () use ($data) {
            $tenant = Tenant::create(['name' => trim($data['company_name'])]);

            return $this->tenantContext->run($tenant->id, function () use ($tenant, $data) {
                $user = User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => $data['password'],
                    'role' => 'admin',
                    'is_active' => true,
                ]);

                event(new ActivityLogged($user, 'tenant_created', "{$tenant->name} signed up; {$user->email} is its first admin.", 'system'));

                return $user;
            });
        });

        // After the commit, so the queued email never refers to a rolled-back user.
        $this->tenantContext->run($user->tenant_id, fn () => $user->sendEmailVerificationNotification());

        return $user;
    }
}
