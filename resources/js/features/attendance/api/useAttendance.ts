import { useMutation, useQuery, useQueryClient, type UseQueryResult } from '@tanstack/react-query';
import { apiClient } from '../../../api/client';
import type { AttendanceRecord, AttendanceQuery, RecordAttendanceRequest } from '../types/attendance';

export const ATTENDANCE_QUERY_KEY = ['attendance'] as const;

/**
 * GET /api/v1/attendance?class_id=&attendance_date=&period= — "did I already
 * take attendance" lookup used to pre-fill the taker form. Returns a bare
 * JsonResource::collection (`{ data: AttendanceRecord[] }`), not paginated.
 * Disabled until both class_id and attendance_date are chosen.
 */
export function useAttendanceForClass(
    query: Partial<AttendanceQuery>,
): UseQueryResult<AttendanceRecord[]> {
    return useQuery({
        queryKey: [...ATTENDANCE_QUERY_KEY, 'list', query],
        queryFn: async () => {
            const { data } = await apiClient.get<{ data: AttendanceRecord[] }>('/attendance', {
                params: query,
            });
            return data.data;
        },
        enabled: Boolean(query.class_id && query.attendance_date),
    });
}

/**
 * POST /api/v1/attendance — idempotent batch capture for one (class, date,
 * period). Resubmitting the same key updates existing rows server-side.
 */
export function useRecordAttendance() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (payload: RecordAttendanceRequest) => {
            const { data } = await apiClient.post<{ data: AttendanceRecord[] }>('/attendance', payload);
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ATTENDANCE_QUERY_KEY });
        },
    });
}
