import { useMutation, useQuery, useQueryClient, type UseQueryResult } from '@tanstack/react-query';
import { apiClient } from '../../../api/client';
import type { ApiResource } from '../../../types/user';
import type { PaginatedResponse } from '../../../types/pagination';
import type { Assignment, CreateAssignmentRequest } from '../types/academic';

export const ASSIGNMENTS_QUERY_KEY = ['assignments'] as const;

/**
 * GET /api/v1/assignments — server-side visibility filtering. The API only
 * returns what the current user (teacher/class_teacher/parent/student/admin)
 * is permitted to see; no client-side filtering needed.
 */
export function useAssignments(): UseQueryResult<PaginatedResponse<Assignment>> {
    return useQuery({
        queryKey: [...ASSIGNMENTS_QUERY_KEY, 'list'],
        queryFn: async () => {
            const { data } = await apiClient.get<PaginatedResponse<Assignment>>('/assignments', {
                params: { per_page: 50 },
            });
            return data;
        },
        staleTime: 30 * 1000,
    });
}

/** GET /api/v1/assignments/{assignment} */
export function useAssignment(id: string | undefined): UseQueryResult<Assignment> {
    return useQuery({
        queryKey: [...ASSIGNMENTS_QUERY_KEY, 'detail', id],
        queryFn: async () => {
            const { data } = await apiClient.get<ApiResource<Assignment>>(`/assignments/${id}`);
            return data.data;
        },
        enabled: Boolean(id),
    });
}

/** POST /api/v1/assignments */
export function useCreateAssignment() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (payload: CreateAssignmentRequest) => {
            const { data } = await apiClient.post<ApiResource<Assignment>>('/assignments', payload);
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ASSIGNMENTS_QUERY_KEY });
        },
    });
}

/** PATCH /api/v1/assignments/{assignment}/publish — no body. */
export function usePublishAssignment() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (id: string) => {
            const { data } = await apiClient.patch<ApiResource<Assignment>>(`/assignments/${id}/publish`);
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ASSIGNMENTS_QUERY_KEY });
        },
    });
}
