import { useCallback, useEffect, useRef, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Button, Dialog, DialogActions, DialogContent, DialogContentText, DialogTitle } from '@mui/material';
import { useAuth } from './AuthProvider';
import { apiClient } from '../api/client';
import { setAuthRedirectReason } from '../lib/authRedirectReason';

/**
 * Laravel's session lifetime (config/session.php, 120 min by default) is a
 * sliding window — any authenticated request resets it. These thresholds
 * are a client-side proxy for "is anyone actually using this tab": warn
 * well before the server would ever expire the session on its own, so the
 * user always gets a chance to stay signed in instead of just hitting a
 * surprise 401 on their next click.
 */
const IDLE_WARNING_AFTER_MS = 20 * 60 * 1000;
const COUNTDOWN_SECONDS = 60;
const ACTIVITY_THROTTLE_MS = 5000;
const ACTIVITY_EVENTS = ['mousemove', 'mousedown', 'keydown', 'touchstart', 'scroll'] as const;

/**
 * Mounted once near the root, inside both <AuthProvider> and <BrowserRouter>.
 * Tracks real user activity (not API calls — a background poll wouldn't
 * prove anyone is at the keyboard) and, after IDLE_WARNING_AFTER_MS of
 * silence, asks whether to stay signed in. No response within
 * COUNTDOWN_SECONDS signs the user out and sends them to /login with a
 * reason. Renders nothing (and tracks nothing) while logged out.
 */
export function IdleSessionGuard() {
    const { isAuthenticated, logout } = useAuth();
    const navigate = useNavigate();
    const [showWarning, setShowWarning] = useState(false);
    const [secondsLeft, setSecondsLeft] = useState(COUNTDOWN_SECONDS);

    const showWarningRef = useRef(false);
    const lastActivityResetRef = useRef(0);
    const warningTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const countdownTimerRef = useRef<ReturnType<typeof setInterval> | null>(null);

    useEffect(() => {
        showWarningRef.current = showWarning;
    }, [showWarning]);

    const clearTimers = useCallback(() => {
        if (warningTimerRef.current) {
            clearTimeout(warningTimerRef.current);
            warningTimerRef.current = null;
        }
        if (countdownTimerRef.current) {
            clearInterval(countdownTimerRef.current);
            countdownTimerRef.current = null;
        }
    }, []);

    const scheduleWarning = useCallback(() => {
        if (warningTimerRef.current) {
            clearTimeout(warningTimerRef.current);
        }
        warningTimerRef.current = setTimeout(() => {
            setSecondsLeft(COUNTDOWN_SECONDS);
            setShowWarning(true);
        }, IDLE_WARNING_AFTER_MS);
    }, []);

    const signOutForInactivity = useCallback(async () => {
        clearTimers();
        setShowWarning(false);
        setAuthRedirectReason('You were signed out because you were inactive for too long.');

        try {
            await logout();
        } catch {
            // Best-effort server-side logout; the client-side state below is
            // what actually gates access, so a failed request here is fine.
        }

        navigate('/login', { replace: true });
    }, [clearTimers, logout, navigate]);

    // Ambient activity — ignored entirely while the warning is up; only the
    // dialog's own buttons resolve it, so a stray mouse twitch over a
    // background tab can't silently dismiss something the user never saw.
    const handleActivity = useCallback(() => {
        if (showWarningRef.current) {
            return;
        }

        const now = Date.now();
        if (now - lastActivityResetRef.current < ACTIVITY_THROTTLE_MS) {
            return;
        }
        lastActivityResetRef.current = now;
        scheduleWarning();
    }, [scheduleWarning]);

    useEffect(() => {
        if (!isAuthenticated) {
            clearTimers();
            setShowWarning(false);
            return;
        }

        scheduleWarning();
        ACTIVITY_EVENTS.forEach((event) => window.addEventListener(event, handleActivity));

        return () => {
            clearTimers();
            ACTIVITY_EVENTS.forEach((event) => window.removeEventListener(event, handleActivity));
        };
    }, [isAuthenticated, scheduleWarning, handleActivity, clearTimers]);

    useEffect(() => {
        if (!showWarning) {
            return;
        }

        countdownTimerRef.current = setInterval(() => {
            setSecondsLeft((prev) => {
                if (prev <= 1) {
                    void signOutForInactivity();
                    return 0;
                }
                return prev - 1;
            });
        }, 1000);

        return () => {
            if (countdownTimerRef.current) {
                clearInterval(countdownTimerRef.current);
            }
        };
    }, [showWarning, signOutForInactivity]);

    const handleStaySignedIn = async () => {
        setShowWarning(false);

        try {
            // Any authenticated request resets Laravel's session clock —
            // this one's just the cheapest one already on hand.
            await apiClient.get('/me');
        } catch {
            // A 401 here is handled by the global interceptor (client.ts),
            // which will redirect on its own.
        }

        scheduleWarning();
    };

    if (!isAuthenticated) {
        return null;
    }

    return (
        <Dialog open={showWarning} onClose={handleStaySignedIn}>
            <DialogTitle>Still there?</DialogTitle>
            <DialogContent>
                <DialogContentText>
                    You've been inactive for a while. For your security, you'll be signed out in {secondsLeft}{' '}
                    second{secondsLeft === 1 ? '' : 's'} unless you choose to stay signed in.
                </DialogContentText>
            </DialogContent>
            <DialogActions>
                <Button onClick={() => void signOutForInactivity()} color="inherit">
                    Sign out now
                </Button>
                <Button onClick={() => void handleStaySignedIn()} variant="contained" autoFocus>
                    Stay signed in
                </Button>
            </DialogActions>
        </Dialog>
    );
}
