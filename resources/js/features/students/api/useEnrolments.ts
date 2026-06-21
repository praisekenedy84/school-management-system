import { useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '../../../api/client';
import type { ApiResource } from '../../../types/user';
import type { Enrolment, PromoteEnrolmentRequest } from '../types/student';
import { STUDENTS_QUERY_KEY } from './useStudents';

/**
 * POST /api/v1/enrolments/{enrolment}/promote — moves a student to a new
 * class/session, carrying over residence_type when omitted. Returns the new
 * Enrolment. Invalidates the owning student's detail query so the new
 * enrolment shows up immediately.
 */
export function usePromoteEnrolment(studentId: string) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({
            enrolmentId,
            payload,
        }: {
            enrolmentId: string;
            payload: PromoteEnrolmentRequest;
        }) => {
            const { data } = await apiClient.post<ApiResource<Enrolment>>(
                `/enrolments/${enrolmentId}/promote`,
                payload,
            );
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: [...STUDENTS_QUERY_KEY, 'detail', studentId] });
        },
    });
}
