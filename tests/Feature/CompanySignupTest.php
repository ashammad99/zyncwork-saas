<?php

namespace Tests\Feature;

use App\Core\Tenancy\TenantContext;
use App\Features\ActivityLog\Models\ActivityLog;
use App\Features\Auth\Models\User;
use App\Features\Tenancy\Models\Tenant;
use App\Mail\GeneralMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Public company signup: a new tenant plus its first admin, who starts
 * unverified and gets a verification email (Phase 1C).
 */
class CompanySignupTest extends TestCase
{
    use RefreshDatabase;

    protected bool $usersStartVerified = false;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function signupData(array $overrides = []): array
    {
        return array_merge([
            'company_name' => 'Acme Inc',
            'name' => 'Jane Founder',
            'email' => 'jane@acme.test',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ], $overrides);
    }

    public function test_signup_creates_a_tenant_and_its_first_admin(): void
    {
        $this->post('/auth/register', $this->signupData())
            ->assertRedirect(route('verification.notice'));

        $tenant = Tenant::where('name', 'Acme Inc')->sole();
        $user = User::withoutTenancy()->where('email', 'jane@acme.test')->sole();

        $this->assertSame($tenant->id, $user->tenant_id);
        $this->assertSame('admin', $user->role);
        $this->assertTrue($user->is_active);
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertTrue(Hash::check('secret-password', $user->password));
        $this->assertAuthenticatedAs($user, 'web');

        // The new tenant is on its own: nothing landed in the test's default tenant.
        $this->assertSame(1, User::withoutTenancy()->where('tenant_id', $tenant->id)->count());
        $this->assertSame(0, User::withoutTenancy()->where('tenant_id', $this->tenant->id)->count());

        $this->assertSame(['tenant_created'], app(TenantContext::class)->run($tenant->id, fn () => ActivityLog::pluck('action')->all()));
    }

    public function test_signup_queues_a_verification_email(): void
    {
        $this->post('/auth/register', $this->signupData());

        Mail::assertQueued(GeneralMail::class, fn (GeneralMail $mail) => $mail->hasTo('jane@acme.test')
            && $mail->contentView === 'emails.auth.verify-email-body'
            && str_contains($mail->data['actionUrl'], '/email/verify/'));
    }

    public function test_the_email_is_stored_lowercased(): void
    {
        $this->post('/auth/register', $this->signupData(['email' => '  Jane@ACME.test ']));

        $this->assertTrue(User::withoutTenancy()->where('email', 'jane@acme.test')->exists());
    }

    public function test_an_email_already_used_anywhere_on_the_platform_is_refused(): void
    {
        $other = Tenant::create(['name' => 'Other Co']);
        app(TenantContext::class)->run($other->id, fn () => User::create([
            'name' => 'Existing', 'email' => 'jane@acme.test', 'password' => 'password', 'role' => 'employee', 'is_active' => true,
        ]));

        $this->post('/auth/register', $this->signupData(['email' => 'JANE@acme.test']))->assertInvalid(['email']);

        $this->assertFalse(Tenant::where('name', 'Acme Inc')->exists());
        $this->assertGuest('web');
    }

    public function test_every_field_is_required_and_the_password_confirmed(): void
    {
        $this->post('/auth/register', [])->assertInvalid(['company_name', 'name', 'email', 'password']);
        $this->post('/auth/register', $this->signupData(['password_confirmation' => 'different']))->assertInvalid(['password']);
        $this->post('/auth/register', $this->signupData(['password' => 'short', 'password_confirmation' => 'short']))->assertInvalid(['password']);

        $this->assertFalse(Tenant::where('name', 'Acme Inc')->exists());
    }

    public function test_a_filled_honeypot_creates_nothing(): void
    {
        $this->post('/auth/register', $this->signupData(['website' => 'http://spam.test']))
            ->assertRedirect(route('login'));

        $this->assertFalse(Tenant::where('name', 'Acme Inc')->exists());
        $this->assertGuest('web');
        Mail::assertNothingQueued();
    }

    public function test_signups_are_limited_per_ip(): void
    {
        foreach (range(1, config('onboarding.signup.per_hour')) as $i) {
            $this->post('/auth/register', $this->signupData(['email' => "person{$i}@acme.test"]));
            $this->post('/auth/logout');
        }

        $this->post('/auth/register', $this->signupData(['email' => 'one-more@acme.test']))->assertStatus(429);
        $this->assertFalse(User::withoutTenancy()->where('email', 'one-more@acme.test')->exists());
    }

    public function test_a_signed_in_user_cannot_reach_signup(): void
    {
        $user = User::create(['name' => 'Someone', 'email' => 'someone@example.com', 'password' => 'password', 'role' => 'employee', 'is_active' => true]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($user)->get('/auth/register')->assertRedirect();
        $this->actingAs($user)->post('/auth/register', $this->signupData())->assertRedirect();
        $this->assertFalse(Tenant::where('name', 'Acme Inc')->exists());
    }

    public function test_the_signup_page_renders_and_login_links_to_it(): void
    {
        $this->get('/auth/register')->assertOk()->assertSee('Company name')->assertSee('name="website"', false);
        $this->get('/auth/login')->assertOk()->assertSee(route('register'));
    }
}
