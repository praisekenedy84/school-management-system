import { useMutation, useQuery, useQueryClient, type UseQueryResult } from '@tanstack/react-query';
import { apiClient } from '../../../api/client';
import type { ApiResource } from '../../../types/user';
import type {
    AdminUser,
    NavigationItemAdmin,
    NavigationSectionAdmin,
    PermissionCatalogEntry,
    PlatformSettings,
    PlatformSettingsRequest,
    RoleDefinition,
    SchoolAdmin,
    SchoolBillingRequest,
    SchoolBrandingRequest,
    SchoolRequest,
    SchoolSettingsRequest,
} from '../types/admin';
import { NAVIGATION_QUERY_KEY } from '../../../lib/useNavigation';

export const ADMIN_SCHOOLS_QUERY_KEY = ['admin', 'schools'] as const;
export const ADMIN_USERS_QUERY_KEY = ['admin', 'users'] as const;
export const ADMIN_ROLES_QUERY_KEY = ['admin', 'roles'] as const;
export const ROLE_DEFINITIONS_QUERY_KEY = ['admin', 'role-definitions'] as const;
export const PERMISSION_CATALOG_QUERY_KEY = ['admin', 'permission-catalog'] as const;
export const ADMIN_NAVIGATION_QUERY_KEY = ['admin', 'navigation'] as const;
export const PLATFORM_NAV_MANAGE_QUERY_KEY = ['platform', 'navigation-manage'] as const;
export const PLATFORM_SETTINGS_QUERY_KEY = ['platform', 'settings'] as const;

export function useAdminSchools(): UseQueryResult<SchoolAdmin[]> {
    return useQuery({
        queryKey: ADMIN_SCHOOLS_QUERY_KEY,
        queryFn: async () => {
            const { data } = await apiClient.get<{ data: SchoolAdmin[] }>('/admin/schools');
            return data.data;
        },
    });
}

export function useAdminSchool(id: string | null): UseQueryResult<SchoolAdmin> {
    return useQuery({
        queryKey: [...ADMIN_SCHOOLS_QUERY_KEY, id],
        queryFn: async () => {
            const { data } = await apiClient.get<ApiResource<SchoolAdmin>>(`/admin/schools/${id}`);
            return data.data;
        },
        enabled: id !== null,
    });
}

export function useCreateSchool() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (payload: SchoolRequest) => {
            const { data } = await apiClient.post<ApiResource<SchoolAdmin>>('/admin/schools', payload);
            return data.data;
        },
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ADMIN_SCHOOLS_QUERY_KEY }),
    });
}

export function useUpdateSchool() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ id, ...payload }: SchoolRequest & { id: string }) => {
            const { data } = await apiClient.put<ApiResource<SchoolAdmin>>(`/admin/schools/${id}`, payload);
            return data.data;
        },
        onSuccess: (_, variables) => {
            queryClient.invalidateQueries({ queryKey: ADMIN_SCHOOLS_QUERY_KEY });
            queryClient.invalidateQueries({ queryKey: [...ADMIN_SCHOOLS_QUERY_KEY, variables.id] });
        },
    });
}

export function useDeleteSchool() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (id: string) => {
            await apiClient.delete(`/admin/schools/${id}`);
        },
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ADMIN_SCHOOLS_QUERY_KEY }),
    });
}

export function useUpdateSchoolSettings() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ id, ...payload }: SchoolSettingsRequest & { id: string }) => {
            const { data } = await apiClient.patch<ApiResource<SchoolAdmin>>(
                `/admin/schools/${id}/settings`,
                payload,
            );
            return data.data;
        },
        onSuccess: (_, variables) => {
            queryClient.invalidateQueries({ queryKey: ADMIN_SCHOOLS_QUERY_KEY });
            queryClient.invalidateQueries({ queryKey: [...ADMIN_SCHOOLS_QUERY_KEY, variables.id] });
        },
    });
}

export function useUpdateSchoolBranding() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ id, ...payload }: SchoolBrandingRequest & { id: string }) => {
            const { data } = await apiClient.patch<ApiResource<SchoolAdmin>>(
                `/admin/schools/${id}/branding`,
                payload,
            );
            return data.data;
        },
        onSuccess: (_, variables) => {
            queryClient.invalidateQueries({ queryKey: ADMIN_SCHOOLS_QUERY_KEY });
            queryClient.invalidateQueries({ queryKey: [...ADMIN_SCHOOLS_QUERY_KEY, variables.id] });
        },
    });
}

export function useUpdateSchoolBilling() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ id, ...payload }: SchoolBillingRequest & { id: string }) => {
            const { data } = await apiClient.patch<ApiResource<SchoolAdmin>>(
                `/admin/schools/${id}/billing`,
                payload,
            );
            return data.data;
        },
        onSuccess: (_, variables) => {
            queryClient.invalidateQueries({ queryKey: ADMIN_SCHOOLS_QUERY_KEY });
            queryClient.invalidateQueries({ queryKey: [...ADMIN_SCHOOLS_QUERY_KEY, variables.id] });
        },
    });
}

export function useAdminUsers(): UseQueryResult<AdminUser[]> {
    return useQuery({
        queryKey: ADMIN_USERS_QUERY_KEY,
        queryFn: async () => {
            const { data } = await apiClient.get<{ data: AdminUser[] }>('/admin/users');
            return data.data;
        },
    });
}

export function useAssignableRoles(): UseQueryResult<string[]> {
    return useQuery({
        queryKey: ADMIN_ROLES_QUERY_KEY,
        queryFn: async () => {
            const { data } = await apiClient.get<{ data: string[] }>('/admin/roles');
            return data.data;
        },
    });
}

export function useRoleDefinitions(): UseQueryResult<RoleDefinition[]> {
    return useQuery({
        queryKey: ROLE_DEFINITIONS_QUERY_KEY,
        queryFn: async () => {
            const { data } = await apiClient.get<{ data: RoleDefinition[] }>('/admin/role-definitions');
            return data.data;
        },
    });
}

export function usePermissionCatalog(): UseQueryResult<PermissionCatalogEntry[]> {
    return useQuery({
        queryKey: PERMISSION_CATALOG_QUERY_KEY,
        queryFn: async () => {
            const { data } = await apiClient.get<{ data: PermissionCatalogEntry[] }>('/admin/permissions');
            return data.data;
        },
    });
}

export function useCreateRoleDefinition() {
    const queryClient = useQueryClient();
    return useMutation({
        mutationFn: async (payload: { name: string; permissions: string[] }) => {
            const { data } = await apiClient.post<ApiResource<RoleDefinition>>('/admin/role-definitions', payload);
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ROLE_DEFINITIONS_QUERY_KEY });
            queryClient.invalidateQueries({ queryKey: ADMIN_ROLES_QUERY_KEY });
        },
    });
}

export function useSyncRolePermissions() {
    const queryClient = useQueryClient();
    return useMutation({
        mutationFn: async ({ role, permissions }: { role: string; permissions: string[] }) => {
            const { data } = await apiClient.put<ApiResource<RoleDefinition>>(
                `/admin/role-definitions/${role}/permissions`,
                { permissions },
            );
            return data.data;
        },
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ROLE_DEFINITIONS_QUERY_KEY }),
    });
}

export function useDeleteRoleDefinition() {
    const queryClient = useQueryClient();
    return useMutation({
        mutationFn: async (role: string) => {
            await apiClient.delete(`/admin/role-definitions/${role}`);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ROLE_DEFINITIONS_QUERY_KEY });
            queryClient.invalidateQueries({ queryKey: ADMIN_ROLES_QUERY_KEY });
        },
    });
}

export function useSyncUserPermissions() {
    const queryClient = useQueryClient();
    return useMutation({
        mutationFn: async ({ userId, permissions }: { userId: string; permissions: string[] }) => {
            const { data } = await apiClient.put<ApiResource<AdminUser>>(`/admin/users/${userId}/permissions`, {
                permissions,
            });
            return data.data;
        },
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ADMIN_USERS_QUERY_KEY }),
    });
}

export function useAdminNavigation(): UseQueryResult<NavigationSectionAdmin[]> {
    return useQuery({
        queryKey: ADMIN_NAVIGATION_QUERY_KEY,
        queryFn: async () => {
            const { data } = await apiClient.get<{ data: NavigationSectionAdmin[] }>('/admin/navigation');
            return data.data;
        },
    });
}

export function useUpdateNavigationItem() {
    const queryClient = useQueryClient();
    return useMutation({
        mutationFn: async ({ id, ...payload }: Partial<NavigationItemAdmin> & { id: string }) => {
            const { data } = await apiClient.patch<ApiResource<NavigationItemAdmin>>(`/admin/navigation/items/${id}`, payload);
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ADMIN_NAVIGATION_QUERY_KEY });
            queryClient.invalidateQueries({ queryKey: NAVIGATION_QUERY_KEY });
        },
    });
}

export function useUpdateNavigationSection() {
    const queryClient = useQueryClient();
    return useMutation({
        mutationFn: async ({ id, ...payload }: { id: string; label?: string; is_active?: boolean }) => {
            const { data } = await apiClient.patch<ApiResource<NavigationSectionAdmin>>(
                `/admin/navigation/sections/${id}`,
                payload,
            );
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ADMIN_NAVIGATION_QUERY_KEY });
            queryClient.invalidateQueries({ queryKey: NAVIGATION_QUERY_KEY });
        },
    });
}

export function usePlatformNavigationManage(): UseQueryResult<NavigationSectionAdmin[]> {
    return useQuery({
        queryKey: PLATFORM_NAV_MANAGE_QUERY_KEY,
        queryFn: async () => {
            const { data } = await apiClient.get<{ data: NavigationSectionAdmin[] }>('/platform/navigation/manage');
            return data.data;
        },
    });
}

export function useUpdatePlatformNavigationItem() {
    const queryClient = useQueryClient();
    return useMutation({
        mutationFn: async ({ id, ...payload }: Partial<NavigationItemAdmin> & { id: string }) => {
            const { data } = await apiClient.patch<ApiResource<NavigationItemAdmin>>(
                `/platform/navigation/items/${id}`,
                payload,
            );
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: PLATFORM_NAV_MANAGE_QUERY_KEY });
            queryClient.invalidateQueries({ queryKey: NAVIGATION_QUERY_KEY });
        },
    });
}

export function useUpdateUserRoles() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ userId, roles }: { userId: string; roles: string[] }) => {
            const { data } = await apiClient.put<ApiResource<AdminUser>>(`/admin/users/${userId}/roles`, {
                roles,
            });
            return data.data;
        },
        onSuccess: () => queryClient.invalidateQueries({ queryKey: ADMIN_USERS_QUERY_KEY }),
    });
}

export function usePlatformSettings(): UseQueryResult<PlatformSettings> {
    return useQuery({
        queryKey: PLATFORM_SETTINGS_QUERY_KEY,
        queryFn: async () => {
            const { data } = await apiClient.get<ApiResource<PlatformSettings>>('/platform/settings');
            return data.data;
        },
    });
}

export function useUpdatePlatformSettings() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (payload: PlatformSettingsRequest) => {
            const { data } = await apiClient.patch<ApiResource<PlatformSettings>>('/platform/settings', payload);
            return data.data;
        },
        onSuccess: () => queryClient.invalidateQueries({ queryKey: PLATFORM_SETTINGS_QUERY_KEY }),
    });
}
