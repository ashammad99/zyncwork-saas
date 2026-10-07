<?php

namespace App\Core\Middleware;

use App\Features\Auth\Models\User;
use App\Features\Notification\Admin\Models\Notification;
use App\Features\Notification\User\Models\Notification as UserNotification;
use App\Features\Platform\Models\PlatformImpersonation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;

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
            // Tenant users only: on the platform host $request->user() is a PlatformAdmin (see 'platform').
            'auth' => fn () => [
                'check' => $this->tenantUser($request) !== null,
                'user' => ($user = $this->tenantUser($request)) ? array_merge(
                    $user->only(['id', 'name', 'email', 'phone', 'role', 'email_verified_at', 'created_at']),
                    [
                        // Secure Profile Image URL
                        'profile_image' => $user->profile_image
                            ? ($user->role === 'admin'
                                ? '/admin/profile-image/'.$user->profile_image
                                : '/user/profile-image/'.$user->profile_image)
                            : null,
                        // Include roles/permissions if the relationships exist on the User model
                        'roles' => method_exists($user, 'roles') ? $user->roles : [],
                        'permissions' => method_exists($user, 'permissions') ? $user->permissions : [],
                        'unread_notifications_count' => $this->notificationQuery($request)?->whereNull('read_at')->count() ?? 0,
                    ]
                ) : null,
            ],
            'notifications' => fn () => $this->notificationQuery($request)?->latest()->limit(5)->get() ?? [],

            // Platform console only: the signed-in platform admin.
            'platform' => fn () => ($admin = Auth::guard('platform')->user())
                ? $admin->only(['id', 'name', 'email', 'capabilities'])
                : null,

            // Tenant host during a support session — drives the banner in MainLayout.
            'impersonation' => fn () => $this->impersonation($request),

            // Flash messages — powers useFlash(), useFlashSuccess(), Toast component
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'info' => $request->session()->get('info'),
                'message' => $request->session()->get('message'),
            ],

            // CSRF token — powers useCsrfToken()
            // 'csrf_token' => csrf_token(),

            // App config — powers useAppConfig()
            'app' => fn () => [
                'name' => config('app.name'),
                'env' => config('app.env'),
            ],
        ]);
    }

    /**
     * Notifications live in two custom tables split by role.
     */
    protected function notificationQuery(Request $request)
    {
        $user = $this->tenantUser($request);

        if (! $user) {
            return null;
        }

        $model = $user->role === 'admin'
            ? Notification::class
            : UserNotification::class;

        return $model::where('user_id', $user->id);
    }

    /** The signed-in tenant user, or null (guests, and platform admins on the platform host). */
    protected function tenantUser(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }

    /** Who is acting and until when, while a platform admin is in a support session. */
    protected function impersonation(Request $request): ?array
    {
        $id = $request->hasSession() ? $request->session()->get('impersonation_id') : null;
        $impersonation = $id ? PlatformImpersonation::with('platformAdmin:id,name')->find($id) : null;

        if (! $impersonation || ! $impersonation->isLive()) {
            return null;
        }

        return [
            'admin_name' => $impersonation->platformAdmin?->name,
            'user_name' => $this->tenantUser($request)?->name,
            'expires_at' => $impersonation->expires_at->toIso8601String(),
        ];
    }
}
