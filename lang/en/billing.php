<?php

// Billing email text. English only for now; a translation goes in lang/<locale>/billing.php.

return [

    'email_changed' => [
        'subject' => 'Your :app billing email was changed',
        'heading' => 'Billing email changed',
        'body' => ':name changed where :company receives billing emails. From now on they go to :email, not to this address.',
        'not_you' => "If you didn't expect this change, contact an administrator of :company.",
    ],

];
