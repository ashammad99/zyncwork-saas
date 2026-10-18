<?php

// Text for the shared email layout and the account emails (verification,
// password reset, invitation). English only for now; a translation goes in
// lang/<locale>/emails.php.

return [

    'layout' => [
        'rights' => '© :year :app. All rights reserved.',
        'unsubscribe' => 'Unsubscribe from these emails',
    ],

    'copy_link' => 'If the button doesn\'t work, copy and paste this link into your browser:',

    'verify_email' => [
        'subject' => 'Verify your email address',
        'heading' => 'Verify your email address',
        'body' => 'Hi :name, please confirm this is your email address. You can use your account as soon as it\'s confirmed.',
        'button' => 'Verify my email',
        'expiry' => 'This link expires in :minutes minutes. If you didn\'t create an account or change your email, you can ignore this message.',
    ],

    'reset_password' => [
        'subject' => 'Reset your password',
        'heading' => 'Reset your password',
        'body' => 'We received a request to reset the password for your account. Use the button below to choose a new one.',
        'button' => 'Reset password',
        'not_you' => 'If you didn\'t ask for this, you can ignore this email. Your password stays the same.',
    ],

    'invitation' => [
        'subject' => 'You\'re invited to join :company on :app',
        'heading' => 'Join :company',
        'body' => ':inviter has invited you to join :company on :app as :role. Use the button below to set up your account.',
        'button' => 'Accept invitation',
        'expiry' => 'This invitation expires on :date and can be used once. If you weren\'t expecting it, you can ignore this email.',
        'roles' => [
            'admin' => 'an admin',
            'manager' => 'a manager',
            'employee' => 'an employee',
        ],
    ],

];
