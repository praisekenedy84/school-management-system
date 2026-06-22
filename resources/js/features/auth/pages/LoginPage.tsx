import { useEffect, useState } from 'react';
import { useForm } from 'react-hook-form';
import { useNavigate, useLocation } from 'react-router-dom';
import {
    Alert,
    Box,
    Button,
    Checkbox,
    Container,
    FormControlLabel,
    Paper,
    TextField,
    Typography,
} from '@mui/material';
import { useAuth } from '../../../app/AuthProvider';
import { getErrorMessage } from '../../../lib/getErrorMessage';
import { consumeAuthRedirectReason } from '../../../lib/authRedirectReason';
import type { LoginRequest } from '../types/auth';

/**
 * TODO: replace hardcoded copy with the EN/SW i18n catalog once it exists
 * (FRONTEND.md §5 / RULES.md §8). Out of scope for the Phase 0 shell.
 */
export function LoginPage() {
    const { login } = useAuth();
    const navigate = useNavigate();
    const location = useLocation();
    const [serverError, setServerError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [sessionNotice, setSessionNotice] = useState<string | null>(null);

    // Why am I here? Set by client.ts's 401 interceptor (a real session
    // expiry) or IdleSessionGuard (signed out for inactivity) — a one-shot
    // read so it never reappears on a later, unrelated visit to /login.
    useEffect(() => {
        setSessionNotice(consumeAuthRedirectReason());
    }, []);

    const {
        register,
        handleSubmit,
        formState: { errors },
    } = useForm<LoginRequest>({
        defaultValues: { email: '', password: '', remember: false },
    });

    const onSubmit = async (values: LoginRequest) => {
        setServerError(null);
        setSessionNotice(null);
        setIsSubmitting(true);
        try {
            await login(values);
            const redirectTo = (location.state as { from?: Location })?.from?.pathname ?? '/';
            navigate(redirectTo, { replace: true });
        } catch (error) {
            setServerError(getErrorMessage(error, 'Unable to log in. Check your credentials and try again.'));
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <Container component="main" maxWidth="xs">
            <Box display="flex" flexDirection="column" alignItems="center" mt={12}>
                <Paper elevation={3} sx={{ p: 4, width: '100%' }}>
                    <Typography component="h1" variant="h5" textAlign="center" gutterBottom>
                        Sign in
                    </Typography>

                    {sessionNotice && !serverError && (
                        <Alert severity="info" sx={{ mb: 2 }}>
                            {sessionNotice}
                        </Alert>
                    )}

                    {serverError && (
                        <Alert severity="error" sx={{ mb: 2 }}>
                            {serverError}
                        </Alert>
                    )}

                    <Box component="form" onSubmit={handleSubmit(onSubmit)} noValidate>
                        <TextField
                            margin="normal"
                            fullWidth
                            label="Email"
                            type="email"
                            autoComplete="email"
                            autoFocus
                            error={Boolean(errors.email)}
                            helperText={errors.email?.message}
                            {...register('email', { required: 'Email is required' })}
                        />
                        <TextField
                            margin="normal"
                            fullWidth
                            label="Password"
                            type="password"
                            autoComplete="current-password"
                            error={Boolean(errors.password)}
                            helperText={errors.password?.message}
                            {...register('password', { required: 'Password is required' })}
                        />
                        <FormControlLabel
                            control={<Checkbox {...register('remember')} />}
                            label="Remember me"
                        />
                        <Button
                            type="submit"
                            fullWidth
                            variant="contained"
                            size="large"
                            sx={{ mt: 2 }}
                            disabled={isSubmitting}
                        >
                            {isSubmitting ? 'Signing in…' : 'Sign in'}
                        </Button>
                    </Box>
                </Paper>
            </Box>
        </Container>
    );
}
