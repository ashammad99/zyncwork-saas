<?php

namespace App\Features\Notification\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationCreated
{
    use Dispatchable, SerializesModels;

    public $user;
    public $title;
    public $message;
    public $category;

    public function __construct($user, string $title, string $message, string $category = 'system')
    {
        $this->user     = $user;
        $this->title    = $title;
        $this->message  = $message;
        $this->category = $category;
    }
}
