import { useMutation, useQuery, useQueryClient, type UseQueryResult } from '@tanstack/react-query';
import { apiClient } from '../../../api/client';
import type { ApiResource } from '../../../types/user';
import type { HostelRoom, HostelRoomRequest } from '../types/hostel';

export const HOSTEL_ROOMS_QUERY_KEY = ['hostel-rooms'] as const;

export interface HostelRoomFilters {
    hostel_id?: string;
}

/** GET /api/v1/hostel-rooms?hostel_id= — non-paginated bare collection. */
export function useHostelRooms(filters: HostelRoomFilters = {}): UseQueryResult<HostelRoom[]> {
    return useQuery({
        queryKey: [...HOSTEL_ROOMS_QUERY_KEY, 'list', filters],
        queryFn: async () => {
            const { data } = await apiClient.get<{ data: HostelRoom[] }>('/hostel-rooms', { params: filters });
            return data.data;
        },
        staleTime: 30 * 1000,
    });
}

/** POST /api/v1/hostel-rooms */
export function useCreateHostelRoom() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (payload: HostelRoomRequest) => {
            const { data } = await apiClient.post<ApiResource<HostelRoom>>('/hostel-rooms', payload);
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: HOSTEL_ROOMS_QUERY_KEY });
        },
    });
}

/** PUT /api/v1/hostel-rooms/{hostelRoom} */
export function useUpdateHostelRoom() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ id, payload }: { id: string; payload: HostelRoomRequest }) => {
            const { data } = await apiClient.put<ApiResource<HostelRoom>>(`/hostel-rooms/${id}`, payload);
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: HOSTEL_ROOMS_QUERY_KEY });
        },
    });
}

/** DELETE /api/v1/hostel-rooms/{hostelRoom} */
export function useDeleteHostelRoom() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (id: string) => {
            await apiClient.delete(`/hostel-rooms/${id}`);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: HOSTEL_ROOMS_QUERY_KEY });
        },
    });
}
