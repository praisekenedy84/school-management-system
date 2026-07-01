import { useMutation, useQuery, useQueryClient, type UseQueryResult } from '@tanstack/react-query';
import { apiClient } from '../../../api/client';
import type { ApiResource } from '../../../types/user';
import type { Stream, StreamRequest } from '../types/academic';
import { CLASSES_QUERY_KEY } from './useClasses';

export function classStreamsQueryKey(classRoomId: string) {
    return [...CLASSES_QUERY_KEY, 'streams', classRoomId] as const;
}

/** GET /api/v1/classes/{classRoom}/streams */
export function useClassStreams(classRoomId: string): UseQueryResult<Stream[]> {
    return useQuery({
        queryKey: classStreamsQueryKey(classRoomId),
        queryFn: async () => {
            const { data } = await apiClient.get<{ data: Stream[] }>(`/classes/${classRoomId}/streams`);
            return data.data;
        },
        enabled: Boolean(classRoomId),
        staleTime: 60 * 1000,
    });
}

/** POST /api/v1/classes/{classRoom}/streams */
export function useCreateStream(classRoomId: string) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (payload: StreamRequest) => {
            const { data } = await apiClient.post<ApiResource<Stream>>(
                `/classes/${classRoomId}/streams`,
                payload,
            );
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: classStreamsQueryKey(classRoomId) });
        },
    });
}

/** PUT /api/v1/classes/{classRoom}/streams/{stream} */
export function useUpdateStream(classRoomId: string) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ id, payload }: { id: string; payload: StreamRequest }) => {
            const { data } = await apiClient.put<ApiResource<Stream>>(
                `/classes/${classRoomId}/streams/${id}`,
                payload,
            );
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: classStreamsQueryKey(classRoomId) });
        },
    });
}

/** DELETE /api/v1/classes/{classRoom}/streams/{stream} — deactivates the stream. */
export function useDeactivateStream(classRoomId: string) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (streamId: string) => {
            const { data } = await apiClient.delete<ApiResource<Stream>>(
                `/classes/${classRoomId}/streams/${streamId}`,
            );
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: classStreamsQueryKey(classRoomId) });
        },
    });
}
