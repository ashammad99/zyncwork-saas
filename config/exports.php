<?php

return [

    // Hours a download link stays valid (the file itself is kept longer, see keep_days).
    'link_hours' => 24,

    // Days a finished export file is kept before exports:prune deletes it.
    'keep_days' => 7,

    // Exports one tenant may request in any 24 hours.
    'per_day' => 3,

    // A queued or running export older than this is marked failed by exports:prune,
    // so a crashed job can't block the tenant's next export for good.
    'stale_minutes' => 60,

    // Seconds the build job may run before the worker kills it.
    'job_timeout' => 600,

];
