<?php

return [

    /*
    | The business that operates Zyncwork, named on every legal page, the
    | contact page and the footer. Public information, so it lives here rather
    | than in .env. `php artisan site:check` fails while any value still says
    | PLACEHOLDER; run it before every deploy.
    */
    'operator' => [
        'name' => 'Ahmed Hammad',
        'address' => 'Gaza Strip, Palestine',
        'email' => 'ashammad99@gmail.com',
    ],

    // Markdown files for the legal pages (terms.md, privacy.md, refund-policy.md).
    // In them, :operator_name, :operator_address and :contact_email are replaced
    // with the values above. Raw HTML is shown as text, not rendered.
    'legal_path' => resource_path('legal/en'),

];
