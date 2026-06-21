import type { ReactNode } from 'react';
import { Alert, Box } from '@mui/material';
import { useAuth } from '../app/AuthProvider';

interface RequirePermissionProps {
    permission: string;
    children: ReactNode;
}

/**
 * Conditional render guard for a single permission (RULES.md §8 — permissions
 * drive route guards + conditional UI; the API still authorizes server-side).
 * No feature routes need this in Phase 0; it exists now so feature work in
 * later phases has a consistent place to gate on `user.permissions`.
 */
export function RequirePermission({ permission, children }: RequirePermissionProps) {
    const { user } = useAuth();
    const allowed = Boolean(user?.permissions.includes(permission));

    if (!allowed) {
        return (
            <Box p={3}>
                {/* TODO: source from the EN/SW i18n catalog once it exists. */}
                <Alert severity="warning">You do not have permission to view this.</Alert>
            </Box>
        );
    }

    return <>{children}</>;
}
