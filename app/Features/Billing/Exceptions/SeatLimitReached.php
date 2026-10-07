<?php

namespace App\Features\Billing\Exceptions;

use App\Core\Exceptions\BaseException;

/** The tenant can't take on another user. The message is safe to show to the tenant's admin. */
class SeatLimitReached extends BaseException {}
