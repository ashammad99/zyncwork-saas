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
    ],

];
