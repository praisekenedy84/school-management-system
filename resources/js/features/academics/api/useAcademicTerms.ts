import { useMutation, useQuery, useQueryClient, type UseQueryResult } from '@tanstack/react-query';
import { apiClient } from '../../../api/client';
import type { ApiResource } from '../../../types/user';
import type { AcademicTerm, AcademicTermRequest } from '../types/academic';
import { ACADEMIC_SESSIONS_QUERY_KEY } from './useAcademicSessions';

export function academicTermsQueryKey(sessionId: string) {
    return [...ACADEMIC_SESSIONS_QUERY_KEY, 'terms', sessionId] as const;
}

/** GET /api/v1/academic-sessions/{session}/terms */
export function useAcademicTerms(sessionId: string): UseQueryResult<AcademicTerm[]> {
    return useQuery({
        queryKey: academicTermsQueryKey(sessionId),
        queryFn: async () => {
            const { data } = await apiClient.get<{ data: AcademicTerm[] }>(
                `/academic-sessions/${sessionId}/terms`,
            );
            return data.data;
        },
        enabled: Boolean(sessionId),
        staleTime: 60 * 1000,
    });
}

/** POST /api/v1/academic-sessions/{session}/terms */
export function useCreateAcademicTerm(sessionId: string) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (payload: AcademicTermRequest) => {
            const { data } = await apiClient.post<ApiResource<AcademicTerm>>(
                `/academic-sessions/${sessionId}/terms`,
                payload,
            );
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: academicTermsQueryKey(sessionId) });
        },
    });
}

/** PUT /api/v1/academic-sessions/{session}/terms/{term} */
export function useUpdateAcademicTerm(sessionId: string) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ id, payload }: { id: string; payload: AcademicTermRequest }) => {
            const { data } = await apiClient.put<ApiResource<AcademicTerm>>(
                `/academic-sessions/${sessionId}/terms/${id}`,
                payload,
            );
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: academicTermsQueryKey(sessionId) });
        },
    });
}

/** DELETE /api/v1/academic-sessions/{session}/terms/{term} */
export function useDeleteAcademicTerm(sessionId: string) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (termId: string) => {
            await apiClient.delete(`/academic-sessions/${sessionId}/terms/${termId}`);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: academicTermsQueryKey(sessionId) });
        },
    });
}
