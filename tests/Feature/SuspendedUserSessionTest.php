<?php

namespace Tests\Feature;

use App\Features\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuspendedUserSessionTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Test User',
            'email' => uniqid('user_') . '@example.com',
            'password' => Hash::make('password'),
            'role' => 'employee',
            'is_active' => true,
        ], $overrides));
    }

    public function test_active_user_keeps_access(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->get('/timesheets')->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_suspended_mid_session_is_logged_out_on_next_request(): void
    {
        $user = $this->makeUser();
        $this->actingAs($user)->get('/timesheets')->assertOk();

        $user->update(['is_active' => false]);

        $this->get('/timesheets')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_suspended_user_inertia_request_gets_full_page_redirect_to_login(): void
    {
        $user = $this->makeUser(['is_active' => false]);
        $version = (string) app(\App\Core\Middleware\HandleInertiaRequests::class)->version(request());

        $this->actingAs($user)
            ->get('/timesheets', ['X-Inertia' => 'true', 'X-Inertia-Version' => $version])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location', route('login'));
        $this->assertGuest();
    }

    public function test_suspended_manager_and_admin_are_logged_out_too(): void
    {
        foreach (['manager', 'admin'] as $role) {
            $user = $this->makeUser(['role' => $role, 'is_active' => false]);

            $this->actingAs($user)->get('/timesheets')->assertRedirect(route('login'));
            $this->assertGuest();
        }
    }
}
