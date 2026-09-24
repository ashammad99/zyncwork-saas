<?php

namespace App\Features\Auth\Services;

use App\Core\BaseService;
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
     *
     * @param string $email
     * @return string
     */
    public function sendResetLink(string $email): string
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
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
            mailSubject: 'Reset Your Password',
            contentView: 'emails.auth.forgot-password-body',
            data: [
                'title' => 'Password Reset Request',
                'body' => 'You are receiving this email because we received a password reset request for your account. If you did not request a password reset, no further action is required.',
                'actionText' => 'Reset Password',
                'actionUrl' => route('password.reset', ['token' => $token, 'email' => $email]),
            ]
        ));

        return Password::RESET_LINK_SENT;
    }

    /**
     * Reset the given user's password.
     *
     * @param array $credentials
     * @return string
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

                event(new ActivityLogged($user, 'password_reset', 'User password reset successfully.', 'security'));
            }
        );
    }
}
