import { useMutation, useQuery, useQueryClient, type UseQueryResult } from '@tanstack/react-query';
import { apiClient } from '../../../api/client';
import type { ApiResource } from '../../../types/user';
import type { PaginatedResponse } from '../../../types/pagination';
import type { Subject, SubjectRequest } from '../types/academic';

export const SUBJECTS_QUERY_KEY = ['subjects'] as const;

/** GET /api/v1/subjects */
export function useSubjects(): UseQueryResult<PaginatedResponse<Subject>> {
    return useQuery({
        queryKey: [...SUBJECTS_QUERY_KEY, 'list'],
        queryFn: async () => {
            const { data } = await apiClient.get<PaginatedResponse<Subject>>('/subjects', {
                params: { per_page: 100 },
            });
            return data;
        },
        staleTime: 60 * 1000,
    });
}

/** POST /api/v1/subjects */
export function useCreateSubject() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (payload: SubjectRequest) => {
            const { data } = await apiClient.post<ApiResource<Subject>>('/subjects', payload);
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: SUBJECTS_QUERY_KEY });
        },
    });
}

/** PUT /api/v1/subjects/{subject} */
export function useUpdateSubject() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ id, payload }: { id: string; payload: SubjectRequest }) => {
            const { data } = await apiClient.put<ApiResource<Subject>>(`/subjects/${id}`, payload);
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: SUBJECTS_QUERY_KEY });
        },
    });
}

/** DELETE /api/v1/subjects/{subject} */
export function useDeleteSubject() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (id: string) => {
            await apiClient.delete(`/subjects/${id}`);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: SUBJECTS_QUERY_KEY });
        },
    });
}
