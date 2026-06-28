import { useMemo } from 'react';
import { useAuth } from '../app/AuthProvider';
import { ActionPermissions, type ActionPermissionKey } from '../config/permissions';
import { canAccessWithPermissions, hasAllPermissions, hasAnyPermission, hasPermission } from '../lib/permissions';

/**
 * Reactive permission helpers for conditional UI (buttons, tabs, etc.).
 * Mirrors the sidebar/route gates in `config/navigation.tsx`.
 */
export function usePermissions() {
    const { user } = useAuth();

    return useMemo(
        () => ({
            user,
            can: (permission: string) => hasPermission(user, permission),
            canAny: (permissions: string[]) => hasAnyPermission(user, permissions),
            canAll: (permissions: string[]) => hasAllPermissions(user, permissions),
            canAccess: (permissions: string[] | null) => canAccessWithPermissions(user, permissions),
            canAction: (action: ActionPermissionKey) =>
                hasAnyPermission(user, [...ActionPermissions[action]]),
        }),
        [user],
    );
}
