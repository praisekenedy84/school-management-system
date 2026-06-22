import { useMutation, useQuery, useQueryClient, type UseQueryResult } from '@tanstack/react-query';
import { apiClient } from '../../../api/client';
import type { ApiResource } from '../../../types/user';
import type { Hostel, HostelRequest } from '../types/hostel';

export const HOSTELS_QUERY_KEY = ['hostels'] as const;

/**
 * GET /api/v1/hostels — read-only lookup, non-paginated bare collection
 * (`{ data: Hostel[] }`), same envelope shape as `/classes` (see
 * academics/api/useClasses.ts) since HostelController::index returns a plain
 * HostelResource::collection(), not a paginator.
 */
export function useHostels(): UseQueryResult<Hostel[]> {
    return useQuery({
        queryKey: [...HOSTELS_QUERY_KEY, 'list'],
        queryFn: async () => {
            const { data } = await apiClient.get<{ data: Hostel[] }>('/hostels');
            return data.data;
        },
        staleTime: 60 * 1000,
    });
}

/** POST /api/v1/hostels */
export function useCreateHostel() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (payload: HostelRequest) => {
            const { data } = await apiClient.post<ApiResource<Hostel>>('/hostels', payload);
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: HOSTELS_QUERY_KEY });
        },
    });
}

/** PUT /api/v1/hostels/{hostel} */
export function useUpdateHostel() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ id, payload }: { id: string; payload: HostelRequest }) => {
            const { data } = await apiClient.put<ApiResource<Hostel>>(`/hostels/${id}`, payload);
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: HOSTELS_QUERY_KEY });
        },
    });
}

/** DELETE /api/v1/hostels/{hostel} */
export function useDeleteHostel() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (id: string) => {
            await apiClient.delete(`/hostels/${id}`);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: HOSTELS_QUERY_KEY });
        },
    });
}
