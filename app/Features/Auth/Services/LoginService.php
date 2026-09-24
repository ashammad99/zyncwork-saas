<?php

namespace App\Features\Auth\Services;

use App\Core\BaseService;
use App\Features\ActivityLog\Events\ActivityLogged;
use App\Features\Auth\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginService extends BaseService
{
    /**
     * Handle the login attempt.
     *
     * @param array $credentials
     * @param bool $remember
     * @return bool
     * @throws ValidationException
     */
    public function attempt(array $credentials, bool $remember = false): bool
    {
        if (Auth::attempt($credentials, $remember)) {
            $user = Auth::user();

            if (!$user->is_active) {
                Auth::logout();
                request()->session()->invalidate();
                request()->session()->regenerateToken();

                throw ValidationException::withMessages([
                    'email' => 'Your account has been suspended. Please contact an administrator.',
                ]);
            }

            request()->session()->regenerate();
            User::where('id', Auth::id())->update([
                'last_login_at' => now(),
                'last_login_ip' => request()->ip(),
            ]);

            event(new ActivityLogged(Auth::user(), 'login', 'User logged in successfully.', 'security'));

            return true;
        }

        throw ValidationException::withMessages([
            'email' => __('auth.failed'),
        ]);
    }
}
