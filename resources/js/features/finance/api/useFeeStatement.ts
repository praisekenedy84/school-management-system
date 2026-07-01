import { useQuery, type UseQueryResult } from '@tanstack/react-query';
import { apiClient } from '../../../api/client';
import type { FeeStatement } from '../types/finance';

export const FEE_STATEMENT_QUERY_KEY = ['fee-statement'] as const;

/** GET /api/v1/students/{studentId}/fee-statement?academic_session_id= */
export function useFeeStatement(
    studentId: string | undefined,
    academicSessionId: string | undefined,
): UseQueryResult<FeeStatement> {
    return useQuery({
        queryKey: [...FEE_STATEMENT_QUERY_KEY, studentId, academicSessionId],
        queryFn: async () => {
            const { data } = await apiClient.get<{ data: FeeStatement }>(
                `/students/${studentId}/fee-statement`,
                { params: { academic_session_id: academicSessionId } },
            );
            return data.data;
        },
        enabled: Boolean(studentId && academicSessionId),
        staleTime: 30 * 1000,
    });
}
