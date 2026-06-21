import { Box, Chip, Paper, Stack, Typography } from '@mui/material';
import { useAuth } from '../../../app/AuthProvider';

/**
 * Trivial authenticated landing page for Phase 0 — proves the auth round
 * trip end-to-end (GET /api/v1/me -> AuthProvider -> guarded route).
 * Replace with the real cross-module dashboard in Phase 5.
 */
export function DashboardPage() {
    const { user } = useAuth();

    if (!user) {
        return null;
    }

    return (
        <Paper sx={{ p: 3, maxWidth: 480 }}>
            <Typography variant="h5" gutterBottom>
                Welcome, {user.name}
            </Typography>
            <Typography variant="body1" color="text.secondary" gutterBottom>
                {user.email}
            </Typography>

            <Box mt={2}>
                <Typography variant="subtitle2" gutterBottom>
                    Roles
                </Typography>
                <Stack direction="row" spacing={1} flexWrap="wrap">
                    {user.roles.length > 0 ? (
                        user.roles.map((role) => <Chip key={role} label={role} size="small" />)
                    ) : (
                        <Typography variant="body2" color="text.secondary">
                            No roles assigned
                        </Typography>
                    )}
                </Stack>
            </Box>
        </Paper>
    );
}
