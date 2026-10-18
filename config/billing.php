<?php

return [

    // Days a new tenant (or an existing one when billing shipped) can use the trial plan.
    'trial_days' => 14,

    // Days a past_due tenant keeps working before it lapses. Set by the provider webhooks (not built yet).
    'grace_days' => 7,

    // plans.key of the plan every new tenant starts on.
    'trial_plan' => 'trial',

    // billing:send-reminders sends no "trial ended" email for a trial that ended
    // more than this many days ago (when the command first runs, or after cron was down).
    'trial_expired_notice_days' => 3,

];
