<?php

namespace App\Features\Billing\Exceptions;

use App\Core\Exceptions\BaseException;

/** The tenant can't take on another user. The message is safe to show to the tenant's admin. */
class SeatLimitReached extends BaseException
{
    /** Every seat is in use. */
    public const SEATS_FULL = 'seats_full';

    /** The trial has ended and no plan was chosen. */
    public const TRIAL_ENDED = 'trial_ended';

    /** past_due, canceled or none. */
    public const PLAN_INACTIVE = 'plan_inactive';

    /** No subscription row at all (should not happen). */
    public const NO_PLAN = 'no_plan';

    public function __construct(string $message, public readonly string $reason)
    {
        parent::__construct($message);
    }
}
