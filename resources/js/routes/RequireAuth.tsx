import type { ReactNode } from 'react';
import { Navigate, useLocation } from 'react-router-dom';
import { Box, CircularProgress } from '@mui/material';
import { useAuth } from '../app/AuthProvider';

/**
 * Route guard: redirects to /login when there is no authenticated user.
 * This is UX only — the API is the source of truth for authorization
 * (RULES.md §8); every guarded request is still checked server-side.
 */
export function RequireAuth({ children }: { children: ReactNode }) {
    const { isAuthenticated, isLoading } = useAuth();
    const location = useLocation();

    if (isLoading) {
        return (
            <Box display="flex" justifyContent="center" alignItems="center" minHeight="100vh">
                <CircularProgress />
            </Box>
        );
    }

    if (!isAuthenticated) {
        return <Navigate to="/login" replace state={{ from: location }} />;
    }

    return <>{children}</>;
}
