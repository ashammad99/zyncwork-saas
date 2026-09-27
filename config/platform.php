<?php

return [

    /*
    | Host the platform console is served on. It must differ from the tenant
    | app's host: a separate origin keeps the platform session out of reach
    | of anything running in the tenant UI (which platform admins visit when
    | impersonating). Locally, *.localhost resolves without a hosts entry.
    */
    'domain' => env('PLATFORM_DOMAIN', 'platform.localhost'),

    // Applied to requests on the platform host only.
    'session' => [
        'cookie' => env('PLATFORM_SESSION_COOKIE', 'zyncwork_platform_session'),
        'lifetime' => (int) env('PLATFORM_SESSION_LIFETIME', 30),
    ],

    'impersonation' => [
        // Seconds the one-time hand-off token stays usable.
        'token_ttl' => 60,
        // Hard cap on a support session, in minutes, counted from hand-off.
        'max_minutes' => 60,
    ],

    // How platform staff are named in a tenant's own activity log.
    'actor_label' => 'Zyncwork support',
];
