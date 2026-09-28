<?php

namespace Tests\Feature;

use App\Features\Platform\Models\PlatformAdmin;
use App\Features\Platform\Models\PlatformAuditLog;
use App\Features\Platform\Services\PlatformTwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * TOTP is required for every platform admin: the password alone never
 * signs anyone in, codes work once, and setup is forced at first sign-in.
 */
class PlatformTwoFactorTest extends TestCase
{
    use Concerns\CrossesPlatformHost;
    use RefreshDatabase;

    private PlatformAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = PlatformAdmin::create([
            'name' => 'Support Person',
            'email' => 'staff@zyncwork.test',
            'password' => 'platform-password',
            'capabilities' => PlatformAdmin::CAPABILITIES,
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function otp(string $secret): string
    {
        return app(Google2FA::class)->getCurrentOtp($secret);
    }

    private function passwordStep(): \Illuminate\Testing\TestResponse
    {
        return $this->post($this->platform('/login'), ['email' => $this->admin->email, 'password' => 'platform-password']);
    }

    /** @return array{0: string, 1: list<string>} secret and recovery codes */
    private function enrollWithRecoveryCodes(): array
    {
        $service = app(PlatformTwoFactorService::class);
        $secret = $service->generateSecret();
        $codes = $service->enable($this->admin, $secret, app(Google2FA::class)->getTimestamp() - 5);

        return [$secret, $codes];
    }

    public function test_the_password_alone_does_not_sign_in(): void
    {
        $this->enrollTwoFactor($this->admin);

        $this->passwordStep()->assertRedirect(route('platform.two-factor.challenge'));

        $this->assertGuest('platform');
        $this->get($this->platform('/tenants'))->assertRedirect(route('platform.login'));
        $this->assertDatabaseMissing('platform_audit_logs', ['action' => PlatformAuditLog::LOGIN]);
    }

    public function test_a_valid_code_completes_sign_in(): void
    {
        $secret = $this->enrollTwoFactor($this->admin);
        $this->passwordStep();

        $this->post($this->platform('/two-factor'), ['code' => $this->otp($secret)])
            ->assertRedirect(route('platform.tenants.index'));

        $this->assertAuthenticatedAs($this->admin, 'platform');
        $this->assertNotNull($this->admin->fresh()->last_login_at);
        $this->assertSame('totp', PlatformAuditLog::where('action', PlatformAuditLog::LOGIN)->sole()->metadata['method']);
    }

    public function test_a_wrong_code_is_rejected_and_audited(): void
    {
        $secret = $this->enrollTwoFactor($this->admin);
        $this->passwordStep();

        $wrong = $this->otp($secret) === '000000' ? '111111' : '000000';

        $this->post($this->platform('/two-factor'), ['code' => $wrong])
            ->assertRedirect(route('platform.login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest('platform');
        $this->assertDatabaseHas('platform_audit_logs', ['action' => PlatformAuditLog::TWO_FACTOR_FAILED, 'platform_admin_id' => $this->admin->id]);
    }

    public function test_a_wrong_code_ends_the_pending_sign_in(): void
    {
        $secret = $this->enrollTwoFactor($this->admin);
        $this->passwordStep();

        $wrong = $this->otp($secret) === '000000' ? '111111' : '000000';
        $this->post($this->platform('/two-factor'), ['code' => $wrong]);

        // Even the right code now needs the password again.
        $this->post($this->platform('/two-factor'), ['code' => $this->otp($secret)])->assertRedirect(route('platform.login'));
        $this->assertGuest('platform');
    }

    public function test_wrong_codes_lock_the_account_whatever_the_ip(): void
    {
        $secret = $this->enrollTwoFactor($this->admin);
        $wrong = $this->otp($secret) === '000000' ? '111111' : '000000';

        // A new IP each time, so the per-IP route throttles never trigger.
        foreach (range(1, config('platform.two_factor.max_attempts')) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$i}"]);
            $this->passwordStep()->assertRedirect(route('platform.two-factor.challenge'));
            $this->post($this->platform('/two-factor'), ['code' => $wrong])->assertRedirect(route('platform.login'));
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.1.1']);
        $this->passwordStep();
        $this->post($this->platform('/two-factor'), ['code' => $this->otp($secret)])
            ->assertRedirect(route('platform.login'))
            ->assertSessionHasErrors(['email' => 'Too many incorrect codes. Try again in 15 minutes.']);
        $this->assertGuest('platform');
        $this->assertSame(config('platform.two_factor.max_attempts'), PlatformAuditLog::where('action', PlatformAuditLog::TWO_FACTOR_FAILED)->count());

        // Recovery codes are locked out too.
        [, $codes] = $this->enrollWithRecoveryCodes();
        $this->passwordStep();
        $this->post($this->platform('/two-factor'), ['recovery_code' => $codes[0]])->assertRedirect(route('platform.login'));
        $this->assertGuest('platform');

        Carbon::setTestNow(now()->addMinutes(config('platform.two_factor.lockout_minutes') + 1));

        $this->passwordStep();
        $this->post($this->platform('/two-factor'), ['recovery_code' => $codes[0]])->assertRedirect(route('platform.tenants.index'));
        $this->assertAuthenticatedAs($this->admin, 'platform');
    }

    public function test_an_attempt_is_counted_before_its_code_is_checked(): void
    {
        $this->enrollTwoFactor($this->admin);
        $key = "platform-two-factor:{$this->admin->id}";
        $countWhileChecking = null;

        // Parallel requests can only all be held to the limit if each one is
        // counted before its (slow, locked) code check, not after it.
        $this->partialMock(PlatformTwoFactorService::class, function ($mock) use ($key, &$countWhileChecking) {
            $mock->shouldReceive('verify')->andReturnUsing(function () use ($key, &$countWhileChecking) {
                $countWhileChecking = RateLimiter::attempts($key);

                return false;
            });
        });

        $this->passwordStep();
        $this->post($this->platform('/two-factor'), ['code' => '123456'])->assertRedirect(route('platform.login'));

        $this->assertSame(1, $countWhileChecking);
        $this->assertSame(1, RateLimiter::attempts($key));
    }

    public function test_attempts_already_in_flight_count_towards_the_limit(): void
    {
        $secret = $this->enrollTwoFactor($this->admin);

        // Five requests that have been counted but not finished yet.
        foreach (range(1, config('platform.two_factor.max_attempts')) as $i) {
            RateLimiter::hit("platform-two-factor:{$this->admin->id}", config('platform.two_factor.lockout_minutes') * 60);
        }

        $this->passwordStep();
        $this->post($this->platform('/two-factor'), ['code' => $this->otp($secret)])
            ->assertRedirect(route('platform.login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest('platform');
    }

    public function test_a_correct_code_clears_earlier_failures(): void
    {
        $secret = $this->enrollTwoFactor($this->admin);
        $wrong = $this->otp($secret) === '000000' ? '111111' : '000000';

        foreach (range(1, config('platform.two_factor.max_attempts') - 1) as $i) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.0.2.{$i}"]);
            $this->passwordStep();
            $this->post($this->platform('/two-factor'), ['code' => $wrong]);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.3.1']);
        $this->passwordStep();
        $this->post($this->platform('/two-factor'), ['code' => $this->otp($secret)])->assertRedirect(route('platform.tenants.index'));

        $this->assertSame(0, RateLimiter::attempts("platform-two-factor:{$this->admin->id}"));
    }

    public function test_a_password_reset_ends_a_sign_in_waiting_for_its_code(): void
    {
        $secret = $this->enrollTwoFactor($this->admin);
        $this->passwordStep()->assertRedirect(route('platform.two-factor.challenge'));

        Artisan::call('platform:admin', ['email' => $this->admin->email, '--reset-password' => true]);

        $this->post($this->platform('/two-factor'), ['code' => $this->otp($secret)])->assertRedirect(route('platform.login'));
        $this->assertGuest('platform');
        $this->assertDatabaseMissing('platform_audit_logs', ['action' => PlatformAuditLog::LOGIN]);
    }

    public function test_a_session_that_never_went_through_sign_in_is_signed_out(): void
    {
        $this->enrollTwoFactor($this->admin);

        // be() sets the guard without the fingerprint a real sign-in stores.
        $this->be($this->admin, 'platform');

        $this->get($this->platform('/tenants'))
            ->assertRedirect(route('platform.login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest('platform');
    }

    public function test_a_later_sign_in_never_sees_an_earlier_admins_setup_secret(): void
    {
        // Admin A starts setup and leaves the browser at the QR code.
        $this->passwordStep();
        preg_match('/<code[^>]*>([A-Z2-7 ]+)<\/code>/', $this->get($this->platform('/two-factor/setup'))->getContent(), $first);

        // Admin B signs in on the same browser.
        $other = PlatformAdmin::create([
            'name' => 'Other Person',
            'email' => 'other@zyncwork.test',
            'password' => 'other-password',
            'capabilities' => [],
            'is_active' => true,
        ]);
        $this->post($this->platform('/login'), ['email' => $other->email, 'password' => 'other-password'])
            ->assertRedirect(route('platform.two-factor.setup'));
        preg_match('/<code[^>]*>([A-Z2-7 ]+)<\/code>/', $this->get($this->platform('/two-factor/setup'))->getContent(), $second);

        $this->assertNotEmpty($first[1]);
        $this->assertNotSame($first[1], $second[1]);
    }

    public function test_a_code_cannot_be_used_twice(): void
    {
        $secret = $this->enrollTwoFactor($this->admin);
        $code = $this->otp($secret);

        $this->passwordStep();
        $this->post($this->platform('/two-factor'), ['code' => $code])->assertRedirect(route('platform.tenants.index'));
        $this->post($this->platform('/logout'));

        $this->passwordStep();
        $this->post($this->platform('/two-factor'), ['code' => $code])
            ->assertRedirect(route('platform.login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest('platform');
    }

    public function test_a_recovery_code_signs_in_once(): void
    {
        [, $codes] = $this->enrollWithRecoveryCodes();

        $this->passwordStep();
        $this->post($this->platform('/two-factor'), ['recovery_code' => strtolower($codes[0])])
            ->assertRedirect(route('platform.tenants.index'));
        $this->assertAuthenticatedAs($this->admin, 'platform');

        $used = PlatformAuditLog::where('action', PlatformAuditLog::TWO_FACTOR_RECOVERY_USED)->sole();
        $this->assertSame(count($codes) - 1, $used->metadata['remaining']);

        $this->post($this->platform('/logout'));
        $this->passwordStep();
        $this->post($this->platform('/two-factor'), ['recovery_code' => $codes[0]])
            ->assertRedirect(route('platform.login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest('platform');
    }

    public function test_the_code_step_expires(): void
    {
        $secret = $this->enrollTwoFactor($this->admin);
        $this->passwordStep();

        Carbon::setTestNow(now()->addMinutes(config('platform.two_factor.pending_minutes') + 1));

        $this->post($this->platform('/two-factor'), ['code' => $this->otp($secret)])
            ->assertRedirect(route('platform.login'));
        $this->assertGuest('platform');
    }

    public function test_the_second_step_needs_a_correct_password_first(): void
    {
        $this->get($this->platform('/two-factor'))->assertRedirect(route('platform.login'));
        $this->get($this->platform('/two-factor/setup'))->assertRedirect(route('platform.login'));
        $this->post($this->platform('/two-factor'), ['code' => '123456'])->assertRedirect(route('platform.login'));
    }

    public function test_an_admin_deactivated_between_the_steps_cannot_finish(): void
    {
        $secret = $this->enrollTwoFactor($this->admin);
        $this->passwordStep();

        $this->admin->update(['is_active' => false]);

        $this->post($this->platform('/two-factor'), ['code' => $this->otp($secret)])->assertRedirect(route('platform.login'));
        $this->assertGuest('platform');
    }

    public function test_setup_is_forced_at_first_sign_in(): void
    {
        $this->passwordStep()->assertRedirect(route('platform.two-factor.setup'));

        // The challenge sends an unenrolled admin to setup, and nothing else is reachable.
        $this->get($this->platform('/two-factor'))->assertRedirect(route('platform.two-factor.setup'));
        $this->get($this->platform('/tenants'))->assertRedirect(route('platform.login'));

        $response = $this->get($this->platform('/two-factor/setup'))
            ->assertOk()
            ->assertSee('<svg', false);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));

        // The secret lives in the session only until a code confirms it.
        $this->assertNull($this->admin->fresh()->two_factor_secret);
    }

    public function test_confirming_setup_enables_two_factor_and_shows_recovery_codes_once(): void
    {
        $this->passwordStep();
        $page = $this->get($this->platform('/two-factor/setup'))->getContent();
        // The manual-entry key, shown in groups of four.
        preg_match('/<code[^>]*>([A-Z2-7 ]+)<\/code>/', $page, $match);
        $secret = str_replace(' ', '', $match[1]);

        $this->post($this->platform('/two-factor/setup'), ['code' => '000000' === $this->otp($secret) ? '111111' : '000000'])
            ->assertSessionHasErrors('code');
        $this->assertFalse($this->admin->fresh()->hasTwoFactor());

        $this->post($this->platform('/two-factor/setup'), ['code' => $this->otp($secret)])
            ->assertRedirect(route('platform.two-factor.recovery-codes'));

        $admin = $this->admin->fresh();
        $this->assertTrue($admin->hasTwoFactor());
        $this->assertSame($secret, $admin->two_factor_secret);
        $this->assertCount(config('platform.two_factor.recovery_codes'), $admin->two_factor_recovery_codes);
        $this->assertAuthenticatedAs($this->admin, 'platform');
        $this->assertDatabaseHas('platform_audit_logs', ['action' => PlatformAuditLog::TWO_FACTOR_ENABLED, 'platform_admin_id' => $this->admin->id]);

        $page = $this->get($this->platform('/two-factor/recovery-codes'))->assertOk();
        $this->assertStringContainsString('no-store', $page->headers->get('Cache-Control'));
        $this->assertSame(config('platform.two_factor.recovery_codes'), preg_match_all('/[A-Z0-9]{5}-[A-Z0-9]{5}/', $page->getContent()));

        // Shown once.
        $this->get($this->platform('/two-factor/recovery-codes'))->assertRedirect(route('platform.tenants.index'));
    }

    public function test_secrets_and_recovery_codes_are_not_stored_in_plain_text(): void
    {
        [$secret, $codes] = $this->enrollWithRecoveryCodes();

        $row = DB::table('platform_admins')->where('id', $this->admin->id)->first();

        $this->assertStringNotContainsString($secret, $row->two_factor_secret);
        foreach ($codes as $code) {
            $this->assertStringNotContainsString($code, $row->two_factor_recovery_codes);
        }
        $this->assertArrayNotHasKey('two_factor_secret', $this->admin->fresh()->toArray());
    }

    public function test_resetting_two_factor_ends_open_sessions_and_is_audited(): void
    {
        $this->enrollTwoFactor($this->admin);
        $this->actingAs($this->admin, 'platform')->get($this->platform('/tenants'))->assertOk();

        Artisan::call('platform:admin', ['email' => $this->admin->email, '--reset-2fa' => true]);

        $this->assertFalse($this->admin->fresh()->hasTwoFactor());
        $this->assertDatabaseHas('platform_audit_logs', ['action' => PlatformAuditLog::TWO_FACTOR_RESET, 'platform_admin_id' => $this->admin->id]);

        $this->actingAs($this->admin->fresh(), 'platform')
            ->get($this->platform('/tenants'))
            ->assertRedirect(route('platform.login'));
        $this->assertGuest('platform');
    }

    public function test_resetting_the_password_ends_open_sessions(): void
    {
        $secret = $this->enrollTwoFactor($this->admin);
        $this->passwordStep();
        $this->post($this->platform('/two-factor'), ['code' => $this->otp($secret)]);
        $this->get($this->platform('/tenants'))->assertOk();

        Artisan::call('platform:admin', ['email' => $this->admin->email, '--reset-password' => true]);

        // A new request loads the admin afresh, as a new process would.
        Auth::forgetGuards();

        $this->get($this->platform('/tenants'))
            ->assertRedirect(route('platform.login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest('platform');
    }

    public function test_the_session_survives_requests_while_the_password_is_unchanged(): void
    {
        $secret = $this->enrollTwoFactor($this->admin);
        $this->passwordStep();
        $this->post($this->platform('/two-factor'), ['code' => $this->otp($secret)]);

        Auth::forgetGuards();
        $this->get($this->platform('/tenants'))->assertOk();
        Auth::forgetGuards();
        $this->get($this->platform('/tenants'))->assertOk();
        $this->assertAuthenticatedAs($this->admin, 'platform');
    }
}
