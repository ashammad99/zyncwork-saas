<?php

namespace App\Features\ActivityLog\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ActivityLogged
{
    use Dispatchable, SerializesModels;

    public $user;
    public $action;
    public $description;
    public $subjectType;

    public function __construct($user, string $action, string $description = '', string $subjectType = null)
    {
        $this->user        = $user;
        $this->action      = $action;
        $this->description = $description;
        $this->subjectType = $subjectType;
    }
}
