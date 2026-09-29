<?php

namespace Tests\Feature;

use App\Core\Tenancy\TenantContext;
use App\Features\Auth\Models\User;
use App\Features\Departments\Admin\Models\Department;
use App\Features\Tenancy\Models\Tenant;
use App\Mail\GeneralMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Tests\TestCase;

/**
 * An unverified tenant user can only verify or sign out: EnsureEmailIsVerified
 * refuses everything else on the server, not just in the UI (Phase 1C).
 */
class EmailVerificationTest extends TestCase
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

    private function makeUser(string $role = 'admin', bool $verified = false, array $overrides = []): User
    {
        $user = User::create(array_merge([
            'name' => "Tenant {$role}",
            'email' => uniqid("{$role}_").'@example.com',
            'password' => 'password',
            'role' => $role,
            'is_active' => true,
        ], $overrides));

        if ($verified) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return $user;
    }

    private function verificationUrl(User $user, ?string $hash = null, int $minutes = 60): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes($minutes), [
            'id' => $user->id,
            'hash' => $hash ?? sha1($user->email),
        ]);
    }

    // ── The block ─────────────────────────────────────────────────────────

    public function test_an_unverified_user_is_sent_to_the_verify_screen(): void
    {
        $this->actingAs($this->makeUser());

        $this->get('/dashboard')->assertRedirect(route('verification.notice'));
        $this->get('/admin/users')->assertRedirect(route('verification.notice'));
        // With the current asset version, so Inertia's own version check doesn't answer first.
        $this->get('/dashboard', ['X-Inertia' => 'true', 'X-Inertia-Version' => (string) Inertia::getVersion()])->assertStatus(409)
            ->assertHeader('X-Inertia-Location', route('verification.notice'));
    }

    public function test_an_unverified_user_cannot_invite_or_create_anything(): void
    {
        $admin = $this->makeUser();
        $department = Department::create(['name' => 'Ops']);
        $this->actingAs($admin);

        $this->post('/admin/invitations', ['email' => 'new@example.com', 'role' => 'employee'])->assertForbidden();
        $this->post('/admin/projects', ['name' => 'Real project', 'department_id' => $department->id])->assertForbidden();
        $this->post('/admin/tasks', ['title' => 'Real task'])->assertForbidden();
        $this->post('/admin/departments', ['name' => 'New department'])->assertForbidden();
        $this->postJson('/admin/invitations', ['email' => 'new@example.com', 'role' => 'employee'])->assertForbidden();

        $this->assertSame(0, DB::table('user_invitations')->count());
        $this->assertSame(0, DB::table('projects')->count());
        $this->assertSame(0, DB::table('tasks')->count());
        $this->assertSame(1, DB::table('departments')->count());
        Mail::assertNothingQueued();
    }

    public function test_the_verify_screen_resend_and_logout_still_work(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $this->get('/email/verify')->assertOk()->assertSee($user->email);

        $this->post('/email/verification-notification')->assertRedirect()->assertSessionHas('status');
        Mail::assertQueued(GeneralMail::class, fn (GeneralMail $mail) => $mail->hasTo($user->email));

        $this->post('/auth/logout');
        $this->assertGuest('web');
    }

    public function test_verified_users_are_not_affected(): void
    {
        $this->actingAs($this->makeUser('admin', verified: true));

        $this->get('/dashboard')->assertOk();
        $this->get('/email/verify')->assertRedirect(route('dashboard'));
    }

    // ── The link ──────────────────────────────────────────────────────────

    public function test_the_link_verifies_the_signed_in_user(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user);

        $this->get($this->verificationUrl($user))->assertRedirect(route('dashboard'));

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        // The guard keeps its in-memory user between test requests; a real request reloads it.
        $this->actingAs($user->fresh())->get('/dashboard')->assertOk();
    }

    public function test_the_link_works_without_signing_in_but_never_signs_anyone_in(): void
    {
        $user = $this->makeUser();

        $this->get($this->verificationUrl($user))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status');

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $this->assertGuest('web');
    }

    public function test_a_tampered_or_expired_link_is_refused(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser('employee');

        // A signed link whose hash doesn't match the user's email.
        $this->get($this->verificationUrl($user, hash: sha1('someone-else@example.com')))->assertForbidden();

        // Someone else's id swapped into a valid link breaks the signature.
        $this->get(str_replace("/verify/{$user->id}/", "/verify/{$other->id}/", $this->verificationUrl($user)))->assertForbidden();

        // Past its expiry.
        $url = $this->verificationUrl($user);
        Carbon::setTestNow(now()->addMinutes(61));
        $this->get($url)->assertForbidden();

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
        $this->assertFalse($other->fresh()->hasVerifiedEmail());
    }

    public function test_resending_is_throttled(): void
    {
        $this->actingAs($this->makeUser());

        foreach (range(1, 6) as $i) {
            $this->post('/email/verification-notification')->assertRedirect();
        }

        $this->post('/email/verification-notification')->assertStatus(429);
    }

    // ── Changing an email ─────────────────────────────────────────────────

    public function test_changing_your_own_email_requires_verifying_it(): void
    {
        $user = $this->makeUser('employee', verified: true);
        $this->actingAs($user);

        $this->put('/user/profile', ['name' => $user->name, 'email' => 'new-address@example.com'])->assertRedirect();

        $user->refresh();
        $this->assertSame('new-address@example.com', $user->email);
        $this->assertFalse($user->hasVerifiedEmail());
        Mail::assertQueued(GeneralMail::class, fn (GeneralMail $mail) => $mail->hasTo('new-address@example.com'));

        $this->get('/dashboard')->assertRedirect(route('verification.notice'));
    }

    public function test_an_admin_changing_someones_email_makes_them_verify_it(): void
    {
        $admin = $this->makeUser('admin', verified: true);
        $employee = $this->makeUser('employee', verified: true);

        $this->actingAs($admin)->put("/admin/users/{$employee->id}", [
            'name' => $employee->name,
            'email' => 'changed@example.com',
            'role' => 'employee',
            'is_active' => true,
            'daily_capacity_hours' => 8,
        ])->assertRedirect();

        $this->assertFalse($employee->fresh()->hasVerifiedEmail());
        Mail::assertQueued(GeneralMail::class, fn (GeneralMail $mail) => $mail->hasTo('changed@example.com'));
        // The admin is unaffected.
        $this->get('/admin/users')->assertOk();
    }

    public function test_the_profile_refuses_an_email_that_is_already_taken(): void
    {
        $user = $this->makeUser('employee', verified: true);
        $sameTenant = $this->makeUser('employee', verified: true);
        $otherTenant = \App\Features\Tenancy\Models\Tenant::create(['name' => 'Other Co']);
        $elsewhere = app(TenantContext::class)->run($otherTenant->id, fn () => $this->makeUser('employee', verified: true));

        foreach ([$sameTenant->email, strtoupper($elsewhere->email)] as $taken) {
            // A validation message, not a database error.
            $this->actingAs($user)->put('/user/profile', ['name' => $user->name, 'email' => $taken])->assertInvalid(['email']);
        }

        $this->assertSame($user->email, $user->fresh()->email);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());

        // Keeping your own address is still fine.
        $this->actingAs($user)->put('/user/profile', ['name' => 'Renamed', 'email' => $user->email])->assertValid();
    }

    public function test_other_profile_changes_keep_the_email_verified(): void
    {
        $user = $this->makeUser('employee', verified: true);
        $this->actingAs($user);

        $this->put('/user/profile', ['name' => 'New Name', 'email' => $user->email])->assertRedirect();

        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        Mail::assertNothingQueued();
    }

    // ── Existing accounts ─────────────────────────────────────────────────

    public function test_the_migration_marks_existing_users_verified(): void
    {
        $user = $this->makeUser();
        $noCreatedAt = $this->makeUser('employee');
        DB::table('users')->where('id', $noCreatedAt->id)->update(['created_at' => null]);

        (require database_path('migrations/2026_09_29_100001_mark_existing_users_verified.php'))->up();

        $this->assertEquals($user->created_at, $user->fresh()->email_verified_at);
        $this->assertNotNull($noCreatedAt->fresh()->email_verified_at);
    }

    public function test_another_tenants_user_can_be_verified_by_their_own_link(): void
    {
        $other = Tenant::create(['name' => 'Other Co']);
        $user = app(TenantContext::class)->run($other->id, fn () => $this->makeUser());

        $this->get($this->verificationUrl($user))->assertRedirect(route('login'));

        $this->assertTrue(User::withoutTenancy()->find($user->id)->hasVerifiedEmail());
    }
}
