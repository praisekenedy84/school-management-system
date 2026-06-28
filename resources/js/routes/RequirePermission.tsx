import type { ReactNode } from 'react';
import { Alert, Box } from '@mui/material';
import { useAuth } from '../app/AuthProvider';
import { canAccessWithPermissions } from '../lib/permissions';

interface RequirePermissionProps {
    /** User needs at least one of these permissions. */
    permission: string | string[];
    children: ReactNode;
}

/**
 * Inline guard for a page region or route subtree. Prefer
 * `RequireAnyPermission` for full-page redirects.
 */
export function RequirePermission({ permission, children }: RequirePermissionProps) {
    const { user } = useAuth();
    const permissions = Array.isArray(permission) ? permission : [permission];
    const allowed = canAccessWithPermissions(user, permissions);

    if (!allowed) {
        return (
            <Box p={3}>
                <Alert severity="warning">You do not have permission to view this.</Alert>
            </Box>
        );
    }

    return <>{children}</>;
}
