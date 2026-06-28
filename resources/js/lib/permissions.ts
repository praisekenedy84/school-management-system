import type { User } from '../types/user';

/** True when the user holds a single Spatie permission (from `/me`). */
export function hasPermission(user: User | null | undefined, permission: string): boolean {
    return Boolean(user?.permissions.includes(permission));
}

/** True when the user holds at least one of the given permissions. */
export function hasAnyPermission(user: User | null | undefined, permissions: string[]): boolean {
    if (!user || permissions.length === 0) {
        return false;
    }

    return permissions.some((permission) => user.permissions.includes(permission));
}

/** True when the user holds every given permission. */
export function hasAllPermissions(user: User | null | undefined, permissions: string[]): boolean {
    if (!user || permissions.length === 0) {
        return false;
    }

    return permissions.every((permission) => user.permissions.includes(permission));
}

/**
 * Nav/route gate: `null` means any authenticated user; otherwise the user
 * needs at least one listed permission. Platform admins bypass permission
 * checks for tenant-scoped routes (they only see the Platform section).
 */
export function canAccessWithPermissions(
    user: User | null | undefined,
    permissions: string[] | null,
): boolean {
    if (!user) {
        return false;
    }

    if (permissions === null) {
        return true;
    }

    return hasAnyPermission(user, permissions);
}
