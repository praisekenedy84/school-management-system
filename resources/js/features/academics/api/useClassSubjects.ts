import { useMutation, useQuery, useQueryClient, type UseQueryResult } from '@tanstack/react-query';
import { apiClient } from '../../../api/client';
import type { Subject } from '../types/academic';
import { CLASSES_QUERY_KEY } from './useClasses';

export function classSubjectsQueryKey(classRoomId: string) {
    return [...CLASSES_QUERY_KEY, 'subjects', classRoomId] as const;
}

/** GET /api/v1/classes/{classRoom}/subjects */
export function useClassSubjects(classRoomId: string): UseQueryResult<Subject[]> {
    return useQuery({
        queryKey: classSubjectsQueryKey(classRoomId),
        queryFn: async () => {
            const { data } = await apiClient.get<{ data: Subject[] }>(`/classes/${classRoomId}/subjects`);
            return data.data;
        },
        enabled: Boolean(classRoomId),
        staleTime: 60 * 1000,
    });
}

/** POST /api/v1/classes/{classRoom}/subjects */
export function useAttachClassSubject(classRoomId: string) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (subjectId: string) => {
            const { data } = await apiClient.post<{ data: Subject[] }>(
                `/classes/${classRoomId}/subjects`,
                { subject_id: subjectId },
            );
            return data.data;
        },
        onSuccess: (subjects) => {
            queryClient.setQueryData(classSubjectsQueryKey(classRoomId), subjects);
        },
    });
}

/** DELETE /api/v1/classes/{classRoom}/subjects/{subject} — returns no body, so optimistically drop locally. */
export function useDetachClassSubject(classRoomId: string) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (subjectId: string) => {
            await apiClient.delete(`/classes/${classRoomId}/subjects/${subjectId}`);
            return subjectId;
        },
        onSuccess: (subjectId) => {
            queryClient.setQueryData<Subject[]>(classSubjectsQueryKey(classRoomId), (current) =>
                (current ?? []).filter((subject) => subject.id !== subjectId),
            );
        },
    });
}
