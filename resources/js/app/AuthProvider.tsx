import { createContext, useCallback, useContext, useMemo, type ReactNode } from 'react';
import { useLoginMutation, useLogoutMutation, useMeQuery } from '../features/auth/api/useAuth';
import type { LoginRequest } from '../features/auth/types/auth';
import type { User } from '../types/user';

interface AuthContextValue {
    user: User | null;
    isLoading: boolean;
    isAuthenticated: boolean;
    login: (credentials: LoginRequest) => Promise<User>;
    logout: () => Promise<void>;
}

const AuthContext = createContext<AuthContextValue | undefined>(undefined);

export function AuthProvider({ children }: { children: ReactNode }) {
    const meQuery = useMeQuery();
    const loginMutation = useLoginMutation();
    const logoutMutation = useLogoutMutation();

    const login = useCallback(
        (credentials: LoginRequest) => loginMutation.mutateAsync(credentials),
        [loginMutation],
    );

    const logout = useCallback(() => logoutMutation.mutateAsync(), [logoutMutation]);

    const value = useMemo<AuthContextValue>(
        () => ({
            user: meQuery.data ?? null,
            isLoading: meQuery.isLoading,
            isAuthenticated: Boolean(meQuery.data),
            login,
            logout,
        }),
        [meQuery.data, meQuery.isLoading, login, logout],
    );

    return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
    const context = useContext(AuthContext);
    if (!context) {
        throw new Error('useAuth must be used within an AuthProvider');
    }
    return context;
}
