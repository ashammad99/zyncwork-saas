<?php

// Billing email text (see BillingNotifier and BillingEmailService). English only for now; a translation goes in lang/<locale>/billing.php.

return [

    'email_changed' => [
        'subject' => 'Your :app billing email was changed',
        'heading' => 'Billing email changed',
        'body' => ':name changed where :company receives billing emails. From now on they go to :email, not to this address.',
        'not_you' => "If you didn't expect this change, contact an administrator of :company.",
    ],

    'view_billing' => 'View billing',

    // A date in the tenant's timezone (or UTC), e.g. "28 October 2026 (UTC)".
    'date_in_zone' => ':date (:zone)',

    'trial_ending' => [
        'subject' => 'Your :app trial ends on :date',
        'heading' => 'Your trial ends soon',
        'body' => 'The :app trial for :company ends on :date.',
        'after' => "After that, everyone keeps working, but you can't add new users until you choose a plan.",
    ],

    'trial_expired' => [
        'subject' => 'Your :app trial has ended',
        'heading' => 'Your trial has ended',
        'body' => 'The :app trial for :company ended on :date.',
        'after' => "Everyone keeps working, but you can't add new users until you choose a plan.",
    ],

    'seat_limit' => [
        'subject' => 'All :app seats for :company are in use',
        'heading' => 'All seats are in use',
        'body' => ':company is using all :seats seats (active users and pending invitations), so an admin could not add a new user just now.',
        'fix' => 'To free a seat, deactivate a user or revoke a pending invitation.',
    ],

    'invitation_blocked' => [
        'subject' => 'An invitation to :company could not be accepted',
        'heading' => 'An invitation could not be accepted',
        'body' => ':email tried to accept their invitation to :company, but :reason',
        'still_valid' => 'Their invitation stays valid until :date. Once this is sorted out, they can use the same link again.',
        'reasons' => [
            'seats_full' => 'every seat is in use.',
            'trial_ended' => 'the trial has ended.',
            'plan_inactive' => "the plan isn't active.",
            'no_plan' => 'the organisation has no plan.',
        ],
    ],

];
