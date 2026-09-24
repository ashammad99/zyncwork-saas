<?php

namespace App\Features\ActivityLog\Listeners;

use App\Features\ActivityLog\Events\ActivityLogged;
use App\Features\ActivityLog\Models\ActivityLog;

class CreateActivityLogRecord
{
    /**
     * Create an activity log record when an ActivityLogged event is dispatched.
     * Synchronous (no ShouldQueue) so logs are always captured immediately.
     */
    public function handle(ActivityLogged $event): void
    {
        ActivityLog::create([
            'user_id'      => $event->user->id,
            'action'       => $event->action,
            'description'  => $event->description,
            'subject_type' => $event->subjectType,
            'ip_address'   => request()->ip(),
            'user_agent'   => request()->userAgent(),
        ]);
    }
}
