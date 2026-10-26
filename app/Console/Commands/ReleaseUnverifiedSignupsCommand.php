<?php

namespace App\Console\Commands;

use App\Core\Tenancy\TenantContext;
use App\Features\ActivityLog\Events\ActivityLogged;
use App\Features\Auth\Models\User;
use App\Features\Tenancy\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Frees the email addresses held by public signups that were never verified.
 *
 * Emails are unique platform-wide, so an unverified signup could otherwise
 * block someone else's address for good (their employer couldn't invite them,
 * and they couldn't sign up). Once a signup's tenants.signup_expires_at has
 * passed, its accounts get a placeholder address on the reserved .invalid
 * domain and are deactivated, and the tenant is suspended. Nothing is deleted.
 *
 * Only tenants still carrying the signup marker are touched: first
 * verification clears it, and seeded or platform-created tenants never have it.
 * Runs hourly (bootstrap/app.php withSchedule).
 */
class ReleaseUnverifiedSignupsCommand extends Command
{
    protected $signature = 'onboarding:release-unverified-signups';

    protected $description = 'Release the email addresses held by signups that were not verified in time';

    public function handle(TenantContext $tenantContext): int
    {
        $released = 0;

        Tenant::whereNotNull('signup_expires_at')
            ->where('signup_expires_at', '<=', now())
            ->each(function (Tenant $tenant) use ($tenantContext, &$released) {
                $tenantContext->run($tenant->id, function () use ($tenant, &$released) {
                    DB::transaction(function () use ($tenant, &$released) {
                        // Safety net: anyone verified means the signup was real. Just drop the marker.
                        if (User::whereNotNull('email_verified_at')->exists()) {
                            $tenant->forceFill(['signup_expires_at' => null])->save();

                            return;
                        }

                        foreach (User::all() as $user) {
                            $email = $user->email;

                            // Quietly: an email change would otherwise send a verification link to the placeholder.
                            $user->forceFill([
                                'email' => "released-{$user->id}@signup.invalid",
                                'is_active' => false,
                            ])->saveQuietly();

                            event(new ActivityLogged($user, 'signup_released', "Signup was not verified in time; released {$email}.", 'security'));
                        }

                        $tenant->forceFill([
                            'signup_expires_at' => null,
                            'suspended_at' => now(),
                            'suspended_reason' => 'Signup was not verified in time.',
                        ])->save();

                        $released++;
                    });
                });
            });

        $this->info("Released {$released} unverified ".str('signup')->plural($released).'.');

        return self::SUCCESS;
    }
}
