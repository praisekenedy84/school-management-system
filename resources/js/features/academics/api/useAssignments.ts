import { useMutation, useQuery, useQueryClient, type UseQueryResult } from '@tanstack/react-query';
import { apiClient } from '../../../api/client';
import type { ApiResource } from '../../../types/user';
import type { PaginatedResponse } from '../../../types/pagination';
import type {
    Assignment,
    AssignmentFilters,
    CreateAssignmentRequest,
    UpdateAssignmentRequest,
} from '../types/academic';

export const ASSIGNMENTS_QUERY_KEY = ['assignments'] as const;

export function useAssignments(
    filters: AssignmentFilters = {},
): UseQueryResult<PaginatedResponse<Assignment>> {
    return useQuery({
        queryKey: [...ASSIGNMENTS_QUERY_KEY, 'list', filters],
        queryFn: async () => {
            const { data } = await apiClient.get<PaginatedResponse<Assignment>>('/assignments', {
                params: {
                    per_page: 50,
                    ...Object.fromEntries(
                        Object.entries(filters).filter(([, value]) => value !== '' && value != null),
                    ),
                },
            });
            return data;
        },
        staleTime: 30 * 1000,
    });
}

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

export function useUpdateAssignment() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ id, payload }: { id: string; payload: UpdateAssignmentRequest }) => {
            const { data } = await apiClient.put<ApiResource<Assignment>>(`/assignments/${id}`, payload);
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ASSIGNMENTS_QUERY_KEY });
        },
    });
}

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

export function useArchiveAssignment() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (id: string) => {
            const { data } = await apiClient.patch<ApiResource<Assignment>>(`/assignments/${id}/archive`);
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ASSIGNMENTS_QUERY_KEY });
        },
    });
}
