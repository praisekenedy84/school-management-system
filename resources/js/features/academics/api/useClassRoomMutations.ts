import { useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '../../../api/client';
import type { ApiResource } from '../../../types/user';
import type { ClassRoom, ClassRoomRequest } from '../types/academic';
import { CLASSES_QUERY_KEY } from './useClasses';

/** POST /api/v1/classes */
export function useCreateClassRoom() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (payload: ClassRoomRequest) => {
            const { data } = await apiClient.post<ApiResource<ClassRoom>>('/classes', payload);
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: CLASSES_QUERY_KEY });
        },
    });
}

/** PUT /api/v1/classes/{classRoom} */
export function useUpdateClassRoom() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ id, payload }: { id: string; payload: ClassRoomRequest }) => {
            const { data } = await apiClient.put<ApiResource<ClassRoom>>(`/classes/${id}`, payload);
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: CLASSES_QUERY_KEY });
        },
    });
}

/** DELETE /api/v1/classes/{classRoom} */
export function useDeleteClassRoom() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (id: string) => {
            await apiClient.delete(`/classes/${id}`);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: CLASSES_QUERY_KEY });
        },
    });
}
