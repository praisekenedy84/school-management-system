import { QueryClient } from '@tanstack/react-query';

/**
 * The single QueryClient instance, in its own module so both `App.tsx`
 * (for `<QueryClientProvider>`) and `client.ts` (for the session-expired
 * interceptor) can reach it without a circular import between them.
 */
export const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            refetchOnWindowFocus: false,
            retry: 1,
        },
    },
});

/**
 * Query key for `GET /api/v1/me`, defined here (rather than in
 * `features/auth`) so the cross-cutting 401 interceptor in `client.ts` can
 * clear it without importing from a feature folder. `features/auth/api/useAuth.ts`
 * imports this same constant — there is only one copy.
 */
export const AUTH_ME_QUERY_KEY = ['auth', 'me'] as const;
