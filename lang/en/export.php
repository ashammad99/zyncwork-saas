<?php

// Data export email text. English only for now; a translation goes in lang/<locale>/export.php.

return [

    'ready' => [
        'subject' => 'Your :app data export is ready',
        'heading' => 'Your data export is ready',
        'body' => 'Hi :name, the export you requested is ready to download.',
        'button' => 'Download export',
        'expiry' => 'The link works for :hours hours and only for you, signed in. The file is deleted after :days days; you can start a new export at any time from the Billing page.',
    ],

    'failed' => [
        'subject' => 'Your :app data export could not be completed',
        'heading' => 'Your data export could not be completed',
        'body' => 'Hi :name, something went wrong while preparing your export. Please try again from the Billing page. If it fails again, contact support.',
    ],

];
