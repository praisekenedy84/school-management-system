import { useMutation, useQuery, useQueryClient, type UseQueryResult } from '@tanstack/react-query';
import { apiClient } from '../../../api/client';
import type { ApiResource } from '../../../types/user';
import type { PaginatedResponse } from '../../../types/pagination';
import type {
    AdmitStudentRequest,
    LinkGuardianRequest,
    Student,
} from '../types/student';

export const STUDENTS_QUERY_KEY = ['students'] as const;

export interface StudentFilters {
    search?: string;
    class_id?: string;
    stream_id?: string;
    gender?: string;
    residence_type?: string;
    academic_session_id?: string;
    status?: string;
}

/** GET /api/v1/students — paginated, school-scoped, with optional filters. */
export function useStudents(
    page: number = 1,
    filters: StudentFilters = {},
    perPage?: number,
): UseQueryResult<PaginatedResponse<Student>> {
    return useQuery({
        queryKey: [...STUDENTS_QUERY_KEY, 'list', page, perPage, filters],
        queryFn: async () => {
            const { data } = await apiClient.get<PaginatedResponse<Student>>('/students', {
                params: {
                    page,
                    per_page: perPage,
                    ...Object.fromEntries(
                        Object.entries(filters).filter(([, value]) => value !== '' && value != null),
                    ),
                },
            });
            return data;
        },
        staleTime: 60 * 1000,
    });
}

/** GET /api/v1/students/{student} — single student with enrolments + guardians eager loaded. */
export function useStudent(id: string | undefined): UseQueryResult<Student> {
    return useQuery({
        queryKey: [...STUDENTS_QUERY_KEY, 'detail', id],
        queryFn: async () => {
            const { data } = await apiClient.get<ApiResource<Student>>(`/students/${id}`);
            return data.data;
        },
        enabled: Boolean(id),
    });
}

/** POST /api/v1/students — admits a student + creates the first enrolment atomically. */
export function useAdmitStudent() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (payload: AdmitStudentRequest) => {
            const { data } = await apiClient.post<ApiResource<Student>>('/students', payload);
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: STUDENTS_QUERY_KEY });
        },
    });
}

/** POST /api/v1/students/{student}/guardians — links an existing user as a guardian. */
export function useLinkGuardian(studentId: string) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (payload: LinkGuardianRequest) => {
            const { data } = await apiClient.post<ApiResource<Student>>(
                `/students/${studentId}/guardians`,
                payload,
            );
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: [...STUDENTS_QUERY_KEY, 'detail', studentId] });
        },
    });
}

/** DELETE /api/v1/students/{student}/guardians/{guardian} — unlinks a guardian. */
export function useUnlinkGuardian(studentId: string) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (guardianId: string) => {
            await apiClient.delete(`/students/${studentId}/guardians/${guardianId}`);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: [...STUDENTS_QUERY_KEY, 'detail', studentId] });
        },
    });
}
