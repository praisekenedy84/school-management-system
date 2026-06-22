import { useMutation, useQuery, useQueryClient, type UseQueryResult } from '@tanstack/react-query';
import { apiClient } from '../../../api/client';
import type { ApiResource } from '../../../types/user';
import type { DecideLeaveRequest, HostelLeaveRequest, RequestLeaveRequest } from '../types/hostel';

export const HOSTEL_LEAVE_REQUESTS_QUERY_KEY = ['hostel-leave-requests'] as const;

/**
 * GET /api/v1/hostel-leave-requests — non-paginated bare collection,
 * auto-scoped server-side: staff sees every request, a `parent` only sees
 * their own wards' requests (HostelLeaveRequestController::index).
 */
export function useHostelLeaveRequests(): UseQueryResult<HostelLeaveRequest[]> {
    return useQuery({
        queryKey: [...HOSTEL_LEAVE_REQUESTS_QUERY_KEY, 'list'],
        queryFn: async () => {
            const { data } = await apiClient.get<{ data: HostelLeaveRequest[] }>('/hostel-leave-requests');
            return data.data;
        },
        staleTime: 15 * 1000,
    });
}

/**
 * POST /api/v1/hostel-leave-requests — a parent may only use an allocation
 * belonging to their own ward (enforced server-side); staff/hostel_manager
 * may request on anyone's behalf.
 */
export function useRequestHostelLeave() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (payload: RequestLeaveRequest) => {
            const { data } = await apiClient.post<ApiResource<HostelLeaveRequest>>(
                '/hostel-leave-requests',
                payload,
            );
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: HOSTEL_LEAVE_REQUESTS_QUERY_KEY });
        },
    });
}

/** POST /api/v1/hostel-leave-requests/{id}/approve — hostel_manager/school_admin/tenant_admin only. */
export function useApproveHostelLeave() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ id, payload }: { id: string; payload: DecideLeaveRequest }) => {
            const { data } = await apiClient.post<ApiResource<HostelLeaveRequest>>(
                `/hostel-leave-requests/${id}/approve`,
                payload,
            );
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: HOSTEL_LEAVE_REQUESTS_QUERY_KEY });
        },
    });
}

/** POST /api/v1/hostel-leave-requests/{id}/reject — hostel_manager/school_admin/tenant_admin only. */
export function useRejectHostelLeave() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ id, payload }: { id: string; payload: DecideLeaveRequest }) => {
            const { data } = await apiClient.post<ApiResource<HostelLeaveRequest>>(
                `/hostel-leave-requests/${id}/reject`,
                payload,
            );
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: HOSTEL_LEAVE_REQUESTS_QUERY_KEY });
        },
    });
}
