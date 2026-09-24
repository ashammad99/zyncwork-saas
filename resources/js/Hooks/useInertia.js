import { usePage } from "@inertiajs/react";

/**
 * Hook to access shared Inertia props
 */
export function useSharedProps() {
    const { props } = usePage();
    return props;
}

/**
 * Hook to access auth data
 */
export function useAuth() {
    const { auth } = usePage().props;
    return auth;
}

/**
 * Hook to get the authenticated user
 */
export function useUser() {
    const auth = useAuth();
    return auth?.user || null;
}

/**
 * Hook to check if user is authenticated
 */
export function useIsAuthenticated() {
    const auth = useAuth();
    return auth?.check || false;
}

/**
 * Hook to check if user is guest (not authenticated)
 */
export function useIsGuest() {
    return !useIsAuthenticated();
}

/**
 * Hook to check if user has a specific role
 * @param {string|string[]} roles - Role or array of roles to check
 * @returns {boolean}
 */
export function useHasRole(roles) {
    const user = useUser();
    if (!user) return false;

    // Support both singular 'role' and plural 'roles' from backend
    const userRoles = user.roles
        ? Array.isArray(user.roles)
            ? user.roles
            : [user.roles]
        : [user.role];

    const checkRoles = Array.isArray(roles) ? roles : [roles];

    return checkRoles.some((role) =>
        userRoles.some((userRole) =>
            typeof userRole === "string"
                ? userRole === role
                : userRole.name === role,
        ),
    );
}

/**
 * Hook to check if user has a specific permission
 * @param {string|string[]} permissions - Permission or array of permissions to check
 * @returns {boolean}
 */
export function useHasPermission(permissions) {
    const user = useUser();
    if (!user || !user.permissions) return false;

    const userPermissions = Array.isArray(user.permissions)
        ? user.permissions
        : [user.permissions];

    const checkPermissions = Array.isArray(permissions)
        ? permissions
        : [permissions];

    return checkPermissions.some((permission) =>
        userPermissions.some((userPermission) =>
            typeof userPermission === "string"
                ? userPermission === permission
                : userPermission.name === permission,
        ),
    );
}

/**
 * Hook to access flash messages
 */
export function useFlash() {
    const { flash } = usePage().props;
    return flash;
}

export function useFlashMessage(key = "message") {
    const flash = useFlash();
    return flash?.[key] ?? null;
}

export function useFlashSuccess() {
    return useFlashMessage("success");
}

export function useFlashError() {
    return useFlashMessage("error");
}

export function useFlashWarning() {
    return useFlashMessage("warning");
}

export function useFlashInfo() {
    return useFlashMessage("info");
}

/**
 * Hook to access validation errors
 */
export function useErrors() {
    const { errors } = usePage().props;
    return errors || {};
}

/**
 * Hook to check if a specific field has validation error
 * @param {string} field - Field name to check
 * @returns {string|null}
 */
export function useError(field) {
    const errors = useErrors();
    return errors[field]?.[0] || null;
}

/**
 * Hook to check if any validation errors exist
 */
export function useHasErrors() {
    const errors = useErrors();
    return Object.keys(errors).length > 0;
}

export function useFieldErrors(field) {
    const errors = useErrors();
    return errors?.[field] ?? [];
}

export function useFirstError(field) {
    if (field) {
        return useFieldErrors(field)?.[0] ?? null;
    }
    const all = Object.values(useErrors()).flat();
    return all[0] ?? null;
}

/**
 * Hook to access app config
 */
export function useAppConfig() {
    const { app } = usePage().props;
    return app;
}

/**
 * Hook to get CSRF token
 */
export function useCsrfToken() {
    const { csrf_token } = usePage().props;
    return csrf_token;
}

/**
 * Hook to get current route information
 */
export function useRoute() {
    const page = usePage();
    return {
        current: page.url,
        component: page.component,
        props: page.props,
        version: page.version,
    };
}

/**
 * Hook to check if current route matches
 * @param {string|string[]} routeNames - Route name(s) to check
 * @returns {boolean}
 */
export function useIsRoute(routeNames) {
    const { component } = useRoute();
    const routes = Array.isArray(routeNames) ? routeNames : [routeNames];
    return routes.some((route) => component.startsWith(route));
}
