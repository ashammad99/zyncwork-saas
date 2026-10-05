<?php

namespace App\Features\Notification\User\Controllers;

use App\Core\BaseController;
use App\Features\Notification\User\Models\Notification;
use Illuminate\Support\Facades\Auth;

class NotificationController extends BaseController
{
    public function index()
    {
        return inertia($this->portalPage('notification/page'), [
            'notifications' => Notification::where('user_id', Auth::id())->latest()->paginate(10),
        ]);
    }

    public function readAll()
    {
        Notification::where('user_id', Auth::id())->update(['read_at' => now()]);

        return back();
    }

    public function read($id)
    {
        $notification = Notification::where('user_id', Auth::id())->find($id);

        if ($notification) {
            $notification->update(['read_at' => now()]);
        }

        return inertia($this->portalPage('notification/[id]/page'), [
            'notification' => $notification,
        ]);
    }

    /**
     * Resolve the page path for the current user's portal.
     */
    protected function portalPage(string $suffix): string
    {
        $portal = match (Auth::user()->role) {
            'manager' => 'manager',
            'employee' => 'employee',
            default => 'user',
        };

        return "(portals)/{$portal}/{$suffix}";
    }
}
