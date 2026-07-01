import { useMutation, useQuery, useQueryClient, type UseQueryResult } from '@tanstack/react-query';
import { apiClient } from '../../../api/client';
import type { ApiResource } from '../../../types/user';
import type { AllocateHostelRequest, HostelAllocation, UpdateHostelAllocationRequest } from '../types/hostel';

export const HOSTEL_ALLOCATIONS_QUERY_KEY = ['hostel-allocations'] as const;

export interface HostelAllocationFilters {
    student_id?: string;
    meal_plan_id?: string;
}

/**
 * GET /api/v1/hostel-allocations?student_id= — non-paginated bare collection.
 * For a `parent` caller the backend already auto-scopes results to their own
 * wards (HostelAllocationController::index), so no client-side filtering by
 * ward is needed — whatever comes back for a parent IS their own data.
 */
export function useHostelAllocations(filters: HostelAllocationFilters = {}): UseQueryResult<HostelAllocation[]> {
    return useQuery({
        queryKey: [...HOSTEL_ALLOCATIONS_QUERY_KEY, 'list', filters],
        queryFn: async () => {
            const { data } = await apiClient.get<{ data: HostelAllocation[] }>('/hostel-allocations', {
                params: filters,
            });
            return data.data;
        },
        staleTime: 30 * 1000,
    });
}

/**
 * POST /api/v1/hostel-allocations — the backend enforces room capacity,
 * gender match, one-active-allocation-per-session, and an optional
 * per-school fee-status gate; any violation surfaces as a 422. Don't
 * pre-validate these invariants client-side, just relay the server message
 * via getErrorMessage().
 */
export function useAllocateHostel() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (payload: AllocateHostelRequest) => {
            const { data } = await apiClient.post<ApiResource<HostelAllocation>>('/hostel-allocations', payload);
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: HOSTEL_ALLOCATIONS_QUERY_KEY });
            queryClient.invalidateQueries({ queryKey: ['hostel-rooms'] });
        },
    });
}

/** POST /api/v1/hostel-allocations/{id}/end — no body. */
export function useEndHostelAllocation() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (id: string) => {
            const { data } = await apiClient.post<ApiResource<HostelAllocation>>(`/hostel-allocations/${id}/end`);
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: HOSTEL_ALLOCATIONS_QUERY_KEY });
            queryClient.invalidateQueries({ queryKey: ['hostel-rooms'] });
        },
    });
}

/** PUT /api/v1/hostel-allocations/{id} — update meal plan on an active allocation. */
export function useUpdateHostelAllocation() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ id, payload }: { id: string; payload: UpdateHostelAllocationRequest }) => {
            const { data } = await apiClient.put<ApiResource<HostelAllocation>>(`/hostel-allocations/${id}`, payload);
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: HOSTEL_ALLOCATIONS_QUERY_KEY });
        },
    });
}
