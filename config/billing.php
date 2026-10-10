<?php

return [

    // Days a new tenant (or an existing one when billing shipped) can use the trial plan.
    'trial_days' => 14,

    // Days a past_due tenant keeps working before it lapses. Set by the provider webhooks (not built yet).
    'grace_days' => 7,

    // plans.key of the plan every new tenant starts on.
    'trial_plan' => 'trial',

];
