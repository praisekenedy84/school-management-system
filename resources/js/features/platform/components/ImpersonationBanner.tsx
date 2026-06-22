import { Alert, Button } from '@mui/material';
import { useNavigate } from 'react-router-dom';
import { LogOut } from 'lucide-react';
import { useAuth } from '../../../app/AuthProvider';
import { useStopImpersonation } from '../api/usePlatform';
import { getErrorMessage } from '../../../lib/getErrorMessage';

/**
 * Persistent banner shown above the app shell while a Platform Admin is
 * impersonating a tenant user — the one required signal that this is not
 * really that user's own session (full read+write impersonation, confirmed
 * scope, so nothing else in the UI looks any different).
 */
export function ImpersonationBanner() {
    const { user } = useAuth();
    const navigate = useNavigate();
    const stopImpersonation = useStopImpersonation();

    if (!user?.impersonation) {
        return null;
    }

    const handleStop = async () => {
        try {
            await stopImpersonation.mutateAsync();
            navigate('/', { replace: true });
        } catch {
            // getErrorMessage'd surface isn't actionable here (the only
            // realistic failure is "already not impersonating") — the
            // button stays available to retry.
        }
    };

    return (
        <Alert
            severity="warning"
            variant="filled"
            sx={{ borderRadius: 1, mb: 2 }}
            action={
                <Button
                    color="inherit"
                    size="small"
                    startIcon={<LogOut size={16} />}
                    onClick={handleStop}
                    disabled={stopImpersonation.isPending}
                >
                    Return to Platform Admin
                </Button>
            }
        >
            Viewing as <strong>{user.name}</strong> ({user.email}) — impersonated by{' '}
            <strong>{user.impersonation.platform_admin_name}</strong>
            {stopImpersonation.isError && ` — ${getErrorMessage(stopImpersonation.error)}`}
        </Alert>
    );
}
