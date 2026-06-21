import { useMutation, useQuery, useQueryClient, type UseQueryResult } from '@tanstack/react-query';
import { apiClient } from '../../../api/client';
import type { ApiResource } from '../../../types/user';
import type { PaginatedResponse } from '../../../types/pagination';
import type { CreateTeacherAssignmentRequest, TeacherAssignment } from '../types/academic';

export const TEACHER_ASSIGNMENTS_QUERY_KEY = ['teacher-assignments'] as const;

export interface TeacherAssignmentFilters {
    teacher_id?: string;
    class_id?: string;
}

/** GET /api/v1/teacher-assignments?teacher_id=&class_id= */
export function useTeacherAssignments(
    filters: TeacherAssignmentFilters = {},
): UseQueryResult<PaginatedResponse<TeacherAssignment>> {
    return useQuery({
        queryKey: [...TEACHER_ASSIGNMENTS_QUERY_KEY, 'list', filters],
        queryFn: async () => {
            const { data } = await apiClient.get<PaginatedResponse<TeacherAssignment>>(
                '/teacher-assignments',
                { params: filters },
            );
            return data;
        },
        staleTime: 60 * 1000,
    });
}

/** POST /api/v1/teacher-assignments */
export function useCreateTeacherAssignment() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (payload: CreateTeacherAssignmentRequest) => {
            const { data } = await apiClient.post<ApiResource<TeacherAssignment>>(
                '/teacher-assignments',
                payload,
            );
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: TEACHER_ASSIGNMENTS_QUERY_KEY });
        },
    });
}

/** DELETE /api/v1/teacher-assignments/{id} */
export function useDeleteTeacherAssignment() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (id: string) => {
            await apiClient.delete(`/teacher-assignments/${id}`);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: TEACHER_ASSIGNMENTS_QUERY_KEY });
        },
    });
}
