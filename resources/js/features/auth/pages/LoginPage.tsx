import { useEffect, useState } from 'react';
import { useForm } from 'react-hook-form';
import { useNavigate, useLocation } from 'react-router-dom';
import { Loader2 } from 'lucide-react';
import { useAuth } from '@/app/AuthProvider';
import { getErrorMessage } from '@/lib/getErrorMessage';
import { consumeAuthRedirectReason } from '@/lib/authRedirectReason';
import type { LoginRequest } from '../types/auth';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/**
 * Sign-in page built with shadcn/ui form primitives.
 */
export function LoginPage() {
    const { login } = useAuth();
    const navigate = useNavigate();
    const location = useLocation();
    const [serverError, setServerError] = useState<string | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [sessionNotice, setSessionNotice] = useState<string | null>(null);

    useEffect(() => {
        setSessionNotice(consumeAuthRedirectReason());
    }, []);

    const {
        register,
        handleSubmit,
        formState: { errors },
        setValue,
        watch,
    } = useForm<LoginRequest>({
        defaultValues: { email: '', password: '', remember: false },
    });

    const remember = watch('remember');

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
        <div className="flex min-h-screen items-center justify-center bg-background p-4">
            <Card className="w-full max-w-md shadow-lg">
                <CardHeader className="text-center">
                    <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-primary text-xl font-bold text-primary-foreground">
                        S
                    </div>
                    <CardTitle className="text-2xl">Sign in</CardTitle>
                    <CardDescription>Enter your credentials to access your school portal</CardDescription>
                </CardHeader>
                <CardContent>
                    {sessionNotice && !serverError && (
                        <Alert variant="info" className="mb-4">
                            <AlertDescription>{sessionNotice}</AlertDescription>
                        </Alert>
                    )}

                    {serverError && (
                        <Alert variant="destructive" className="mb-4">
                            <AlertDescription>{serverError}</AlertDescription>
                        </Alert>
                    )}

                    <form onSubmit={handleSubmit(onSubmit)} className="space-y-4" noValidate>
                        <div className="space-y-2">
                            <Label htmlFor="email">Email</Label>
                            <Input
                                id="email"
                                type="email"
                                autoComplete="email"
                                autoFocus
                                aria-invalid={Boolean(errors.email)}
                                {...register('email', { required: 'Email is required' })}
                            />
                            {errors.email && (
                                <p className="text-sm text-destructive">{errors.email.message}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="password">Password</Label>
                            <Input
                                id="password"
                                type="password"
                                autoComplete="current-password"
                                aria-invalid={Boolean(errors.password)}
                                {...register('password', { required: 'Password is required' })}
                            />
                            {errors.password && (
                                <p className="text-sm text-destructive">{errors.password.message}</p>
                            )}
                        </div>

                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="remember"
                                checked={remember}
                                onCheckedChange={(checked) => setValue('remember', checked === true)}
                            />
                            <Label htmlFor="remember" className="cursor-pointer font-normal">
                                Remember me
                            </Label>
                        </div>

                        <Button type="submit" className="w-full" size="lg" disabled={isSubmitting}>
                            {isSubmitting ? (
                                <>
                                    <Loader2 className="h-4 w-4 animate-spin" />
                                    Signing in…
                                </>
                            ) : (
                                'Sign in'
                            )}
                        </Button>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}
