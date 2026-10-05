<?php

return [

    'invitations' => [
        // Days an emailed invite link stays usable.
        'expire_days' => 7,
        // Per tenant: new invites and resends together, so the app can't be used to send bulk email.
        'per_hour' => 50,
    ],

    'signup' => [
        // Per IP.
        'per_hour' => 5,
        // A signup whose admin hasn't verified by then is released, freeing the
        // email address (onboarding:release-unverified-signups, run hourly).
        'verify_within_hours' => 24,
    ],

    'verification' => [
        // Per email address, whatever triggers them (signup, resend, email change).
        'emails_per_day' => 10,
    ],

];
