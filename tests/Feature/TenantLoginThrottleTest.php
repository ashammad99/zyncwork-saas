<?php

namespace Tests\Feature;

use App\Features\Auth\Models\User;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The tenant login limits failed attempts per email and IP (LoginService).
 */
class TenantLoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::create([
            'name' => 'Tenant Employee',
            'email' => 'employee@example.com',
            'password' => Hash::make('password'),
            'role' => 'employee',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function failFiveTimes(): void
    {
        foreach (range(1, 5) as $i) {
            $this->post('/auth/login', ['email' => $this->user->email, 'password' => 'wrong-password'])
                ->assertSessionHasErrors(['email' => __('auth.failed')]);
        }
    }

    public function test_five_failures_block_even_the_right_password(): void
    {
        $this->failFiveTimes();

        $this->post('/auth/login', ['email' => $this->user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertStringStartsWith('Too many login attempts', session('errors')->first('email'));
        $this->assertGuest('web');
    }

    public function test_an_attempt_is_counted_before_the_password_is_checked(): void
    {
        $key = "{$this->user->email}|127.0.0.1";
        $countWhileChecking = null;

        // Attempting fires just before the (slow) password check. Parallel guesses can only
        // all be held to the limit if each one is counted by then, not after it fails.
        Event::listen(Attempting::class, function () use ($key, &$countWhileChecking) {
            $countWhileChecking = RateLimiter::attempts($key);
        });

        $this->post('/auth/login', ['email' => $this->user->email, 'password' => 'wrong-password']);

        $this->assertSame(1, $countWhileChecking);
    }

    public function test_the_block_lifts_after_a_minute(): void
    {
        Carbon::setTestNow('2026-09-29 10:00:00');
        $this->failFiveTimes();

        Carbon::setTestNow(now()->addSeconds(61));

        $this->post('/auth/login', ['email' => $this->user->email, 'password' => 'password'])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($this->user, 'web');
    }

    public function test_the_limit_is_per_ip_so_others_cannot_lock_a_user_out(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1']);
        $this->failFiveTimes();

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2']);
        $this->post('/auth/login', ['email' => $this->user->email, 'password' => 'password'])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($this->user, 'web');
    }

    public function test_the_limit_ignores_email_case(): void
    {
        $this->failFiveTimes();

        $this->post('/auth/login', ['email' => strtoupper($this->user->email), 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest('web');
    }

    public function test_a_successful_login_clears_earlier_failures(): void
    {
        foreach (range(1, 4) as $i) {
            $this->post('/auth/login', ['email' => $this->user->email, 'password' => 'wrong-password']);
        }

        $this->post('/auth/login', ['email' => $this->user->email, 'password' => 'password'])->assertSessionHasNoErrors();
        $this->post('/auth/logout');

        // A fresh allowance of five: the next four failures don't block the right password.
        foreach (range(1, 4) as $i) {
            $this->post('/auth/login', ['email' => $this->user->email, 'password' => 'wrong-password']);
        }
        $this->post('/auth/login', ['email' => $this->user->email, 'password' => 'password'])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($this->user, 'web');
    }
}
