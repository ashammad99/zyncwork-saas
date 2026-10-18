<?php

namespace Tests\Feature;

use App\Mail\GeneralMail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every email view renders through the shared layout with its text from
 * lang/en: no translation key shows up raw, and the starter kit's placeholder
 * footer (fake address, tagline) is gone.
 */
class EmailTemplatesTest extends TestCase
{
    /** @return array<string, array{string, array<string, mixed>, array<int, string>}> */
    public static function views(): array
    {
        return [
            'verify email' => ['emails.auth.verify-email-body', [
                'name' => 'Rana', 'actionUrl' => 'https://app.test/verify/1', 'expiresInMinutes' => 60,
            ], ['Hi Rana, please confirm this is your email address.', 'Verify my email', 'expires in 60 minutes']],
            'reset password' => ['emails.auth.forgot-password-body', [
                'actionUrl' => 'https://app.test/reset/abc',
            ], ['Reset your password', 'Reset password', 'Your password stays the same.']],
            'invitation' => ['emails.auth.invitation-body', [
                'companyName' => 'Acme', 'inviterName' => 'Sami', 'role' => 'employee',
                'actionUrl' => 'https://app.test/invitations/abc', 'expiresAt' => 'Oct 21, 2026',
            ], ['Join Acme', 'Sami has invited you to join Acme on', 'as an employee', 'expires on Oct 21, 2026']],
            'trial ending' => ['emails.billing.trial-ending', [
                'companyName' => 'Acme', 'date' => '28 October 2026', 'actionUrl' => 'https://app.test/admin/billing',
            ], ['Your trial ends soon', 'trial for Acme ends on 28 October 2026.', 'View billing', 'until you choose a plan']],
            'trial expired' => ['emails.billing.trial-expired', [
                'companyName' => 'Acme', 'date' => '28 October 2026', 'actionUrl' => 'https://app.test/admin/billing',
            ], ['Your trial has ended', 'trial for Acme ended on 28 October 2026.', 'View billing']],
            'seat limit' => ['emails.billing.seat-limit', [
                'companyName' => 'Acme', 'seats' => 10, 'actionUrl' => 'https://app.test/admin/billing',
            ], ['All seats are in use', 'Acme is using all 10 seats', 'revoke a pending invitation']],
            'invitation blocked' => ['emails.billing.invitation-blocked', [
                'companyName' => 'Acme', 'invitedEmail' => 'new@example.com', 'reason' => 'every seat is in use.',
                'expiresAt' => '21 October 2026', 'actionUrl' => 'https://app.test/admin/billing',
            ], ['new@example.com tried to accept their invitation to Acme, but every seat is in use.', 'stays valid until 21 October 2026']],
        ];
    }

    #[DataProvider('views')]
    public function test_email_renders_with_translated_text(string $view, array $data, array $expected): void
    {
        $html = (new GeneralMail('Subject', $view, $data))->render();

        foreach ($expected as $text) {
            $this->assertStringContainsString(e($text), $html);
        }

        $this->assertDoesNotMatchRegularExpression('/\b(emails|billing|export)\.[a-z_]+\.[a-z_.]+/', strip_tags($html), 'A translation key was rendered raw.');
        $this->assertStringContainsString('All rights reserved.', $html);
        $this->assertStringNotContainsString('Built with passion', $html);
        $this->assertStringNotContainsString('Developer Lane', $html);
    }

    public function test_invitation_names_each_role_with_its_article(): void
    {
        foreach (['admin' => 'as an admin', 'manager' => 'as a manager', 'employee' => 'as an employee'] as $role => $text) {
            $html = (new GeneralMail('Subject', 'emails.auth.invitation-body', [
                'companyName' => 'Acme', 'inviterName' => 'Sami', 'role' => $role,
                'actionUrl' => 'https://app.test/i/1', 'expiresAt' => 'Oct 21, 2026',
            ]))->render();

            $this->assertStringContainsString($text, $html);
        }
    }
}
