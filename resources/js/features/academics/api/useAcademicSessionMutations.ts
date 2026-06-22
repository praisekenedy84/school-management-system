import { useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '../../../api/client';
import type { ApiResource } from '../../../types/user';
import type { AcademicSession, AcademicSessionRequest } from '../types/academic';
import { ACADEMIC_SESSIONS_QUERY_KEY } from './useAcademicSessions';

/** POST /api/v1/academic-sessions */
export function useCreateAcademicSession() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (payload: AcademicSessionRequest) => {
            const { data } = await apiClient.post<ApiResource<AcademicSession>>('/academic-sessions', payload);
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ACADEMIC_SESSIONS_QUERY_KEY });
        },
    });
}

/** PUT /api/v1/academic-sessions/{academicSession} */
export function useUpdateAcademicSession() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ id, payload }: { id: string; payload: AcademicSessionRequest }) => {
            const { data } = await apiClient.put<ApiResource<AcademicSession>>(
                `/academic-sessions/${id}`,
                payload,
            );
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ACADEMIC_SESSIONS_QUERY_KEY });
        },
    });
}

/** DELETE /api/v1/academic-sessions/{academicSession} */
export function useDeleteAcademicSession() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (id: string) => {
            await apiClient.delete(`/academic-sessions/${id}`);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ACADEMIC_SESSIONS_QUERY_KEY });
        },
    });
}
