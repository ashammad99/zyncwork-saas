<?php

namespace App\Features\Auth\Services;

use App\Core\BaseService;
use App\Features\ActivityLog\Events\ActivityLogged;
use App\Features\Auth\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginService extends BaseService
{
    /** Failed attempts allowed per email and IP within DECAY_SECONDS. */
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    /**
     * Handle the login attempt.
     *
     * @throws ValidationException
     */
    public function attempt(array $credentials, bool $remember = false): bool
    {
        // Per email and IP (Laravel's standard login limiter): enough to stop
        // password guessing, without letting anyone lock a user out from elsewhere.
        $throttleKey = Str::transliterate(Str::lower((string) ($credentials['email'] ?? ''))).'|'.request()->ip();

        // Counted before the (slow) password check, in one atomic increment, so
        // guesses sent in parallel can't all get past the limit. A successful
        // sign-in clears the count.
        if (RateLimiter::hit($throttleKey, self::DECAY_SECONDS) > self::MAX_ATTEMPTS) {
            throw ValidationException::withMessages([
                'email' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($throttleKey)]),
            ]);
        }

        if (Auth::attempt($credentials, $remember)) {
            RateLimiter::clear($throttleKey);

            $user = Auth::user();

            $suspended = match (true) {
                ! $user->is_active => 'Your account has been suspended. Please contact an administrator.',
                (bool) $user->tenant?->isSuspended() => 'Your organisation\'s account has been suspended. Please contact support.',
                default => null,
            };

            if ($suspended) {
                Auth::logout();
                request()->session()->invalidate();
                request()->session()->regenerateToken();

                throw ValidationException::withMessages([
                    'email' => $suspended,
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
