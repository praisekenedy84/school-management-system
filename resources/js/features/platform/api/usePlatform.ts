import { useMutation, useQuery, useQueryClient, type UseQueryResult } from '@tanstack/react-query';
import { apiClient } from '../../../api/client';
import { AUTH_ME_QUERY_KEY } from '../../../api/queryClient';
import { mergeImpersonation } from '../../auth/api/useAuth';
import type { ApiResource, User } from '../../../types/user';
import type { PaginatedResponse } from '../../../types/pagination';
import type { AuditLogEntry, AuditLogFilters, CreateTenantRequest, Tenant, TenantUser } from '../types/platform';

export const TENANTS_QUERY_KEY = ['platform', 'tenants'] as const;
export const AUDIT_LOGS_QUERY_KEY = ['platform', 'audit-logs'] as const;

/** GET /api/v1/platform/tenants */
export function useTenants(): UseQueryResult<Tenant[]> {
    return useQuery({
        queryKey: TENANTS_QUERY_KEY,
        queryFn: async () => {
            const { data } = await apiClient.get<{ data: Tenant[] }>('/platform/tenants');
            return data.data;
        },
        staleTime: 30 * 1000,
    });
}

/** POST /api/v1/platform/tenants */
export function useCreateTenant() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (payload: CreateTenantRequest) => {
            const { data } = await apiClient.post<ApiResource<Tenant>>('/platform/tenants', payload);
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: TENANTS_QUERY_KEY });
        },
    });
}

/** GET /api/v1/platform/tenants/{tenant}/users — the impersonation picker. */
export function useTenantUsers(tenantId: string | null) {
    return useQuery({
        queryKey: [...TENANTS_QUERY_KEY, tenantId, 'users'],
        queryFn: async () => {
            const { data } = await apiClient.get<{ data: TenantUser[] }>(`/platform/tenants/${tenantId}/users`);
            return data.data;
        },
        enabled: tenantId !== null,
    });
}

/**
 * POST /api/v1/platform/impersonate — full read+write impersonation
 * (confirmed scope). On success, seed the `/me` cache with the impersonated
 * identity (mirrors useLoginMutation) and broadly invalidate every other
 * cached query, since literally everything tenant-scoped is now stale under
 * a different identity.
 */
export function useStartImpersonation() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ tenantId, userId }: { tenantId: string; userId: string }) => {
            const { data } = await apiClient.post<ApiResource<User>>('/platform/impersonate', {
                tenant_id: tenantId,
                user_id: userId,
            });
            return mergeImpersonation(data);
        },
        onSuccess: (user) => {
            queryClient.setQueryData(AUTH_ME_QUERY_KEY, user);
            queryClient.invalidateQueries({ predicate: (query) => query.queryKey[0] !== 'auth' });
        },
    });
}

/**
 * POST /api/v1/platform/impersonate/stop — backend returns a
 * PlatformAdminResource, not a UserResource, but it's deliberately shaped to
 * satisfy the same `User` TS interface (no roles/permissions, `type:
 * 'platform_admin'`) so this hook can reuse it without a second type.
 */
export function useStopImpersonation() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async () => {
            const { data } = await apiClient.post<ApiResource<User>>('/platform/impersonate/stop');
            return mergeImpersonation(data);
        },
        onSuccess: (user) => {
            queryClient.setQueryData(AUTH_ME_QUERY_KEY, user);
            queryClient.invalidateQueries({ predicate: (query) => query.queryKey[0] !== 'auth' });
        },
    });
}

/** GET /api/v1/platform/audit-logs */
export function useAuditLogs(filters: AuditLogFilters): UseQueryResult<PaginatedResponse<AuditLogEntry>> {
    return useQuery({
        queryKey: [...AUDIT_LOGS_QUERY_KEY, filters],
        queryFn: async () => {
            const { data } = await apiClient.get<PaginatedResponse<AuditLogEntry>>('/platform/audit-logs', {
                params: filters,
            });
            return data;
        },
        staleTime: 10 * 1000,
    });
}
