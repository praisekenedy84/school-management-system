import { useMutation, useQuery, useQueryClient, type UseQueryResult } from '@tanstack/react-query';
import { apiClient, ensureCsrfCookie, UNAUTHENTICATED_STATUS } from '../../../api/client';
import type { ApiResource, User } from '../../../types/user';
import type { LoginRequest } from '../types/auth';

export const AUTH_ME_QUERY_KEY = ['auth', 'me'] as const;

/**
 * GET /api/v1/me — returns the authenticated user, or null when there is no
 * active session (401 is expected here on first load and is not an error).
 */
export function useMeQuery(): UseQueryResult<User | null> {
    return useQuery({
        queryKey: AUTH_ME_QUERY_KEY,
        queryFn: async () => {
            try {
                const { data } = await apiClient.get<ApiResource<User>>('/me');
                return data.data;
            } catch (error: any) {
                if (error?.response?.status === UNAUTHENTICATED_STATUS) {
                    return null;
                }
                throw error;
            }
        },
        retry: false,
        staleTime: 5 * 60 * 1000,
    });
}

/**
 * Login flow: fetch the CSRF cookie first (GET /sanctum/csrf-cookie, root
 * path, not /api/v1), then POST /api/v1/login with the XSRF header axios
 * attaches automatically. On success, seed the `me` query cache with the
 * returned user so the UI updates immediately without an extra round trip.
 */
export function useLoginMutation() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (credentials: LoginRequest) => {
            await ensureCsrfCookie();
            const { data } = await apiClient.post<ApiResource<User>>('/login', credentials);
            return data.data;
        },
        onSuccess: (user) => {
            queryClient.setQueryData(AUTH_ME_QUERY_KEY, user);
        },
    });
}

/**
 * POST /api/v1/logout — clears the server session; we then clear the cached
 * user so route guards immediately treat the SPA as unauthenticated.
 */
export function useLogoutMutation() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async () => {
            await apiClient.post('/logout');
        },
        onSuccess: () => {
            queryClient.setQueryData(AUTH_ME_QUERY_KEY, null);
        },
    });
}
