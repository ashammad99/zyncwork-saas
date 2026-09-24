<?php

namespace App\Core\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use App\Features\Notification\Admin\Models\Notification as AdminNotification;
use App\Features\Notification\User\Models\Notification as UserNotification;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * These props are available on every Inertia page via usePage().props
     * and power all hooks in resources/js/Hooks/useInertia.js
     *
     * @see https://inertiajs.com/shared-data
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [

            // Auth data — powers useAuth(), useUser(), useIsAuthenticated(), useHasRole(), useHasPermission()
            'auth' => fn() => [
                'check' => $request->user() !== null,
                'user'  => $request->user() ? array_merge(
                    $request->user()->only(['id', 'name', 'email', 'phone','role', 'email_verified_at', 'created_at']),
                    [
                        // Secure Profile Image URL
                        'profile_image' => $request->user()->profile_image
                            ? ($request->user()->role === 'admin'
                                ? '/admin/profile-image/' . $request->user()->profile_image
                                : '/user/profile-image/' . $request->user()->profile_image)
                            : null,
                        // Include roles/permissions if the relationships exist on the User model
                        'roles'                      => method_exists($request->user(), 'roles') ? $request->user()->roles : [],
                        'permissions'                => method_exists($request->user(), 'permissions') ? $request->user()->permissions : [],
                        'unread_notifications_count' => $this->notificationQuery($request)?->whereNull('read_at')->count() ?? 0,
                    ]
                ) : null,
            ],
            'notifications' => fn() => $this->notificationQuery($request)?->latest()->limit(5)->get() ?? [],

            // Flash messages — powers useFlash(), useFlashSuccess(), Toast component
            'flash' => fn() => [
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'info'    => $request->session()->get('info'),
                'message' => $request->session()->get('message'),
            ],

            // CSRF token — powers useCsrfToken()
            //'csrf_token' => csrf_token(),

            // App config — powers useAppConfig()
            'app' => fn() => [
                'name' => config('app.name'),
                'env'  => config('app.env'),
            ],
        ]);
    }
    /**
     * Notifications live in two custom tables split by role.
     */
    protected function notificationQuery(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        $model = $user->role === 'admin'
            ? \App\Features\Notification\Admin\Models\Notification::class
            : \App\Features\Notification\User\Models\Notification::class;

        return $model::where('user_id', $user->id);
    }
}
