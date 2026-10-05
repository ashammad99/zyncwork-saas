<?php

namespace Tests\Feature;

use App\Features\Auth\Models\User;
use App\Features\Platform\Models\PlatformAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Every route limit is a named limiter with its own counter (AppServiceProvider).
 * A plain throttle:N,M keys only on the user or IP, so routes shared one
 * counter and a short window on one route reset another's long one.
 */
class RouteRateLimitTest extends TestCase
{
    use Concerns\CrossesPlatformHost;
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

    private function signup(int $i): \Illuminate\Testing\TestResponse
    {
        $response = $this->post('/auth/register', [
            'company_name' => "Company {$i}",
            'name' => 'Founder',
            'email' => "founder{$i}@example.com",
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);
        $this->post('/auth/logout');

        return $response;
    }

    public function test_another_routes_short_window_cannot_reset_the_signup_limit(): void
    {
        // The bypass: hit a 1-minute route first, so a shared counter would expire after a minute.
        $this->get('/invitations/'.str_repeat('a', 64))->assertOk();

        foreach (range(1, config('onboarding.signup.per_hour')) as $i) {
            $this->signup($i)->assertRedirect(route('verification.notice'));
        }

        Carbon::setTestNow(now()->addSeconds(61));

        $this->signup(99)->assertStatus(429);
        $this->assertFalse(User::withoutTenancy()->where('email', 'founder99@example.com')->exists());
    }

    public function test_the_accept_pages_have_their_own_budget(): void
    {
        foreach (range(1, config('onboarding.signup.per_hour')) as $i) {
            $this->signup($i);
        }

        // Signups used up theirs; the invite pages are unaffected.
        $this->get('/invitations/'.str_repeat('a', 64))->assertOk();
    }

    public function test_the_tenant_login_is_limited_per_ip_across_all_emails(): void
    {
        // Password spraying: one password against many addresses from one IP.
        foreach (range(1, 20) as $i) {
            $this->post('/auth/login', ['email' => "person{$i}@example.com", 'password' => 'Summer2026!'])->assertStatus(302);
        }

        $this->post('/auth/login', ['email' => 'person21@example.com', 'password' => 'Summer2026!'])->assertStatus(429);

        // Another IP is unaffected.
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])
            ->post('/auth/login', ['email' => 'person21@example.com', 'password' => 'Summer2026!'])
            ->assertStatus(302);
    }

    public function test_the_platform_password_and_code_steps_have_separate_budgets(): void
    {
        $admin = PlatformAdmin::create([
            'name' => 'Support Person', 'email' => 'staff@zyncwork.test', 'password' => 'platform-password',
            'capabilities' => [], 'is_active' => true,
        ]);
        $this->enrollTwoFactor($admin);

        foreach (range(1, 5) as $i) {
            $this->post($this->platform('/login'), ['email' => $admin->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        }

        // A shared counter would refuse this sixth request of the minute.
        $this->post($this->platform('/two-factor'), ['code' => '123456'])->assertRedirect(route('platform.login'));
        $this->post($this->platform('/login'), ['email' => $admin->email, 'password' => 'wrong'])->assertStatus(429);
    }
}
