import type { ReactNode } from 'react';
import { Navigate } from 'react-router-dom';
import { useAuth } from '../app/AuthProvider';
import { canAccessWithPermissions } from '../lib/permissions';

interface RequireAnyPermissionProps {
    permissions: string[];
    children: ReactNode;
    /** Where to send users who lack every listed permission. */
    redirectTo?: string;
}

/**
 * Route guard: user must hold at least one of the listed Spatie permissions.
 * Redirects to the dashboard by default (UX only — the API still authorizes).
 */
export function RequireAnyPermission({
    permissions,
    children,
    redirectTo = '/',
}: RequireAnyPermissionProps) {
    const { user } = useAuth();
    const allowed = canAccessWithPermissions(user, permissions);

    if (!allowed) {
        return <Navigate to={redirectTo} replace />;
    }

    return <>{children}</>;
}
