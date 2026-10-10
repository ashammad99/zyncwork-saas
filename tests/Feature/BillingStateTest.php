<?php

namespace Tests\Feature;

use App\Core\Tenancy\TenantContext;
use App\Features\Auth\Models\User;
use App\Features\Billing\Models\Plan;
use App\Features\Billing\Models\TenantSubscription;
use App\Features\Onboarding\Models\UserInvitation;
use App\Features\Platform\Models\PlatformAdmin;
use App\Features\Tenancy\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * The platform billing page: each tenant's plan, status and seats, read from
 * tenant_subscriptions. Seats used count active users plus open invitations,
 * per tenant, across the whole platform.
 */
class BillingStateTest extends TestCase
{
    use Concerns\CrossesPlatformHost;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-07 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeAdmin(array $capabilities = [PlatformAdmin::VIEW_BILLING]): PlatformAdmin
    {
        $admin = PlatformAdmin::create([
            'name' => 'Support Person',
            'email' => uniqid('staff_').'@zyncwork.test',
            'password' => 'platform-password',
            'capabilities' => $capabilities,
            'is_active' => true,
        ]);
        $this->enrollTwoFactor($admin);

        return $admin;
    }

    private function makeUser(Tenant $tenant, string $role = 'employee', bool $active = true): User
    {
        return app(TenantContext::class)->run($tenant->id, fn () => User::create([
            'name' => "Tenant {$role}",
            'email' => uniqid("{$role}_").'@example.com',
            'password' => 'password',
            'role' => $role,
            'is_active' => $active,
        ]));
    }

    private function makeInvitation(User $inviter, array $state = []): void
    {
        app(TenantContext::class)->run($inviter->tenant_id, function () use ($inviter, $state) {
            UserInvitation::create([
                'email' => uniqid('invitee_').'@example.com',
                'role' => 'employee',
                'invited_by' => $inviter->id,
                'token_hash' => hash('sha256', uniqid('', true)),
                'expires_at' => now()->addDays(7),
            ])->forceFill($state)->save();
        });
    }

    public function test_each_tenants_plan_status_and_seats_are_shown(): void
    {
        // "Acme" sorts before the default "Test Tenant".
        $acme = Tenant::create(['name' => 'Acme']);
        $acme->forceFill(['billing_email' => 'billing@acme.test'])->save();
        TenantSubscription::where('tenant_id', $acme->id)->sole()->forceFill([
            'plan_id' => Plan::where('key', 'standard')->value('id'),
            'status' => TenantSubscription::STATUS_PAST_DUE,
            'seats' => 2,
            'grace_ends_at' => now()->addDays(7),
        ])->save();

        // Acme: 2 active + 1 open invite = 3 of 2 (over). Inactive users and used invites don't count.
        $acmeAdmin = $this->makeUser($acme, 'admin');
        $this->makeUser($acme);
        $this->makeUser($acme, active: false);
        $this->makeInvitation($acmeAdmin);
        $this->makeInvitation($acmeAdmin, ['revoked_at' => now()]);
        $this->makeInvitation($acmeAdmin, ['expires_at' => now()->subMinute()]);

        // The default tenant: 1 active user, its trial running.
        $this->makeUser($this->tenant, 'admin');

        $this->actingAs($this->makeAdmin(), 'platform');

        $this->get($this->platform('/billing'))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('(platform)/billing/Index')
            ->has('tenants', 2)
            ->where('tenants.0.name', 'Acme')
            ->where('tenants.0.plan', 'Standard')
            ->where('tenants.0.status', 'past_due')
            ->where('tenants.0.trial_expired', false)
            ->where('tenants.0.seats', 2)
            ->where('tenants.0.seats_used', 3)
            ->where('tenants.0.billing_email', 'billing@acme.test')
            ->where('tenants.0.grace_ends_at', fn ($value) => Carbon::parse($value)->equalTo(now()->addDays(7)))
            ->where('tenants.1.id', $this->tenant->id)
            ->where('tenants.1.plan', 'Trial')
            ->where('tenants.1.status', 'trialing')
            ->where('tenants.1.seats', 10)
            ->where('tenants.1.seats_used', 1));
    }

    public function test_an_expired_trial_is_flagged(): void
    {
        $this->actingAs($this->makeAdmin(), 'platform');
        TenantSubscription::where('tenant_id', $this->tenant->id)->sole()
            ->forceFill(['trial_ends_at' => now()->subMinute()])->save();

        $this->get($this->platform('/billing'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('tenants.0.status', 'trialing')
            ->where('tenants.0.trial_expired', true));
    }

    public function test_the_page_needs_the_view_billing_capability(): void
    {
        $others = array_values(array_diff(PlatformAdmin::CAPABILITIES, [PlatformAdmin::VIEW_BILLING]));
        $this->actingAs($this->makeAdmin($others), 'platform');

        $this->get($this->platform('/billing'))->assertForbidden();
    }
}
