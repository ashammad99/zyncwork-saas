<?php

namespace App\Features\Auth\Services;

use App\Core\BaseService;
use App\Core\Tenancy\TenantContext;
use App\Features\ActivityLog\Events\ActivityLogged;
use App\Features\Auth\Models\User;
use App\Mail\GeneralMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ForgotPasswordService extends BaseService
{
    /**
     * Send a reset link to the given user.
     */
    public function sendResetLink(string $email): string
    {
        // Guest request: no tenant yet. Emails are unique platform-wide.
        $user = User::withoutTenancy()->where('email', $email)->first();

        if (! $user) {
            return Password::INVALID_USER;
        }

        // Generate a token
        $token = Str::random(64);

        // Store in password_reset_tokens
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'email' => $email,
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        // Send Email via Queue using GeneralMail
        Mail::to($email)->send(new GeneralMail(
            mailSubject: __('emails.reset_password.subject'),
            contentView: 'emails.auth.forgot-password-body',
            data: [
                'actionUrl' => route('password.reset', ['token' => $token, 'email' => $email]),
            ]
        ));

        return Password::RESET_LINK_SENT;
    }

    /**
     * Reset the given user's password.
     */
    public function resetPassword(array $credentials): string
    {
        return Password::broker()->reset(
            $credentials,
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();

                $user->setRememberToken(Str::random(60));

                // Guest request: log against the user's own tenant.
                app(TenantContext::class)->run($user->tenant_id, fn () => event(new ActivityLogged($user, 'password_reset', 'User password reset successfully.', 'security'))
                );
            }
        );
    }
}
