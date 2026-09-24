<?php

namespace App\Core\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * Checks if the authenticated user has one of the required roles.
     * Supports both string role property and roles relationship (array of objects with 'name').
     *
     * Usage in routes:
     *   Route::middleware('role:admin')           → single role
     *   Route::middleware('role:admin,moderator')  → any of these roles
     *
     * @param  string  ...$roles  Comma-separated role names
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        // Collect the user's roles into a flat array of strings
        $userRoles = [];

        // Support: $user->role (single string)
        if (isset($user->role) && is_string($user->role)) {
            $userRoles[] = $user->role;
        }

        // Support: $user->roles (relationship — collection of objects with 'name')
        if (method_exists($user, 'roles') && $user->relationLoaded('roles')) {
            foreach ($user->roles as $role) {
                $userRoles[] = is_string($role) ? $role : ($role->name ?? '');
            }
        } elseif (isset($user->roles) && is_array($user->roles)) {
            foreach ($user->roles as $role) {
                $userRoles[] = is_string($role) ? $role : ($role['name'] ?? '');
            }
        }

        // Check if user has any of the required roles
        foreach ($roles as $role) {
            if (in_array($role, $userRoles, true)) {
                return $next($request);
            }
        }

        abort(403, 'Unauthorized. Required role: ' . implode(' or ', $roles));
    }
}
