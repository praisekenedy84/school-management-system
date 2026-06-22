import { useMutation, useQuery, useQueryClient, type UseQueryResult } from '@tanstack/react-query';
import { apiClient } from '../../../api/client';
import type { PaginatedResponse } from '../../../types/pagination';
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
 * GET /api/v1/attendance?student_id= — one student's attendance history,
 * newest first. Used by the parent per-child drill-down
 * (AttendanceController::index's `student_id` branch); unlike
 * `useAttendanceForClass`'s bare-array roster mode, this branch is
 * `->paginate()`d server-side, so it returns the standard
 * `PaginatedResponse` envelope. A `parent` must pass their own ward's id —
 * the API 403s otherwise. Disabled until a studentId is provided.
 */
export function useAttendanceForStudent(
    studentId: string | undefined,
): UseQueryResult<PaginatedResponse<AttendanceRecord>> {
    return useQuery({
        queryKey: [...ATTENDANCE_QUERY_KEY, 'student-history', studentId],
        queryFn: async () => {
            const { data } = await apiClient.get<PaginatedResponse<AttendanceRecord>>('/attendance', {
                params: { student_id: studentId, per_page: 30 },
            });
            return data;
        },
        enabled: Boolean(studentId),
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
