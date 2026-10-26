<?php

namespace Tests\Feature;

use App\Core\Tenancy\TenantContext;
use App\Features\ActivityLog\Models\ActivityLog;
use App\Features\Auth\Models\User;
use App\Features\Tenancy\Models\Tenant;
use App\Mail\GeneralMail;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * A signup that isn't verified within 24 hours gives up its email address
 * (onboarding:release-unverified-signups), so nobody can hold someone else's
 * address by signing up with it and never verifying.
 */
class ReleaseUnverifiedSignupsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $usersStartVerified = false;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-29 10:00:00');
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function signup(string $email = 'victim@corp.test', string $company = 'Squatter Inc'): User
    {
        $this->post('/auth/register', [
            'company_name' => $company,
            'name' => 'Someone',
            'email' => $email,
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ])->assertRedirect(route('verification.notice'));
        $this->post('/auth/logout');

        return User::withoutTenancy()->where('email', $email)->sole();
    }

    private function release(): void
    {
        Artisan::call('onboarding:release-unverified-signups');
    }

    public function test_an_unverified_signup_is_released_after_24_hours(): void
    {
        $user = $this->signup();
        $tenant = Tenant::find($user->tenant_id);
        $this->assertEquals(now()->addHours(24), $tenant->signup_expires_at);

        Carbon::setTestNow(now()->addHours(24)->addSecond());
        $this->release();

        $user = User::withoutTenancy()->find($user->id);
        $this->assertSame("released-{$user->id}@signup.invalid", $user->email);
        $this->assertFalse($user->is_active);

        $tenant->refresh();
        $this->assertTrue($tenant->isSuspended());
        $this->assertNull($tenant->signup_expires_at);
        $this->assertSame(1, app(TenantContext::class)->run($tenant->id, fn () => ActivityLog::where('action', 'signup_released')->count()));
    }

    public function test_the_email_can_then_be_used_by_a_new_signup_or_an_invite(): void
    {
        $this->signup();
        Carbon::setTestNow(now()->addHours(25));
        $this->release();

        // The real owner signs up...
        $this->signup('victim@corp.test', 'Real Employer')->forceFill(['email_verified_at' => now()])->save();
        $this->assertSame(1, User::withoutTenancy()->where('email', 'victim@corp.test')->count());
    }

    public function test_the_released_address_can_be_invited(): void
    {
        $this->signup();
        Carbon::setTestNow(now()->addHours(25));
        $this->release();

        // Signing out after signup cleared the tenant context; the employer is the test tenant.
        app(TenantContext::class)->set($this->tenant->id);
        $admin = User::create(['name' => 'Employer Admin', 'email' => 'boss@corp.test', 'password' => 'password', 'role' => 'admin', 'is_active' => true]);
        $admin->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($admin)->post('/admin/invitations', ['email' => 'victim@corp.test', 'role' => 'employee'])
            ->assertSessionHasNoErrors();
    }

    public function test_the_old_account_can_no_longer_sign_in(): void
    {
        $this->signup();
        Carbon::setTestNow(now()->addHours(25));
        $this->release();

        $this->post('/auth/login', ['email' => 'victim@corp.test', 'password' => 'secret-password'])->assertSessionHasErrors('email');
        $this->assertGuest('web');
    }

    public function test_nothing_is_released_before_24_hours(): void
    {
        $user = $this->signup();

        Carbon::setTestNow(now()->addHours(23));
        $this->release();

        $this->assertSame('victim@corp.test', User::withoutTenancy()->find($user->id)->email);
        $this->assertFalse(Tenant::find($user->tenant_id)->isSuspended());
    }

    public function test_a_verified_signup_is_never_released(): void
    {
        $user = $this->signup();
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($url);

        $this->assertNull(Tenant::find($user->tenant_id)->signup_expires_at);

        // Even after later changing their email (unverified again).
        app(TenantContext::class)->run($user->tenant_id, fn () => User::find($user->id)->update(['email' => 'changed@corp.test']));

        Carbon::setTestNow(now()->addDays(3));
        $this->release();

        $user = User::withoutTenancy()->find($user->id);
        $this->assertSame('changed@corp.test', $user->email);
        $this->assertTrue($user->is_active);
    }

    public function test_tenants_that_did_not_come_from_signup_are_never_touched(): void
    {
        // The test tenant has no signup marker; this user is unverified, e.g. after an email change.
        $user = User::create(['name' => 'Long-time User', 'email' => 'old-timer@example.com', 'password' => 'password', 'role' => 'employee', 'is_active' => true]);
        User::withoutTenancy()->whereKey($user->id)->update(['created_at' => now()->subYear()]);

        Carbon::setTestNow(now()->addDays(3));
        $this->release();

        $this->assertSame('old-timer@example.com', $user->fresh()->email);
        $this->assertTrue($user->fresh()->is_active);
    }

    public function test_a_marked_tenant_with_any_verified_user_is_only_unmarked(): void
    {
        $user = $this->signup();
        User::withoutTenancy()->whereKey($user->id)->update(['email_verified_at' => now()]);

        Carbon::setTestNow(now()->addHours(25));
        $this->release();

        $this->assertSame('victim@corp.test', User::withoutTenancy()->find($user->id)->email);
        $this->assertNull(Tenant::find($user->tenant_id)->signup_expires_at);
        $this->assertFalse(Tenant::find($user->tenant_id)->isSuspended());
    }

    public function test_releasing_sends_no_email(): void
    {
        $this->signup();
        $before = Mail::queued(GeneralMail::class)->count();

        Carbon::setTestNow(now()->addHours(25));
        $this->release();

        $this->assertSame($before, Mail::queued(GeneralMail::class)->count());
    }

    public function test_the_command_runs_hourly(): void
    {
        // withSchedule registers when Artisan starts, as with schedule:run on the server.
        Artisan::call('schedule:list');

        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains((string) $event->command, 'onboarding:release-unverified-signups'));

        $this->assertNotNull($event);
        $this->assertSame('0 * * * *', $event->expression);
    }
}
