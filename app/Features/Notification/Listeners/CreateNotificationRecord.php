<?php

namespace App\Features\Notification\Listeners;

use App\Features\Notification\Admin\Models\Notification as AdminNotification;
use App\Features\Notification\User\Models\Notification as UserNotification;
use App\Features\Notification\Events\NotificationCreated;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateNotificationRecord implements ShouldQueue
{
    use InteractsWithQueue, SerializesModels;
    /**
     * Create a notification record when a notification event is dispatched.
     */
    public function handle(NotificationCreated $event): void
    {
        $user     = $event->user;
        $category = $event->category;
        $title    = $event->title;
        $message  = $event->message;

        if ($user->role === 'admin') {
            AdminNotification::create([
                'user_id'     => $user->id,
                'category'    => $category,
                'title'       => $title,
                'description' => $message,
            ]);
        } else {
            UserNotification::create([
                'user_id'     => $user->id,
                'category'    => $category,
                'title'       => $title,
                'description' => $message,
            ]);
        }
    }
}
