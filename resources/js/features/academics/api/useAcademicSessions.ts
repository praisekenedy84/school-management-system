import { useQuery, type UseQueryResult } from '@tanstack/react-query';
import { apiClient } from '../../../api/client';
import type { AcademicSession } from '../types/academic';

export const ACADEMIC_SESSIONS_QUERY_KEY = ['academic-sessions'] as const;

/**
 * GET /api/v1/academic-sessions — read-only lookup, same non-paginated
 * `{ data: AcademicSession[] }` envelope as `/classes` (see useClasses.ts).
 */
export function useAcademicSessions(): UseQueryResult<AcademicSession[]> {
    return useQuery({
        queryKey: [...ACADEMIC_SESSIONS_QUERY_KEY, 'list'],
        queryFn: async () => {
            const { data } = await apiClient.get<{ data: AcademicSession[] }>('/academic-sessions');
            return data.data;
        },
        staleTime: 5 * 60 * 1000,
    });
}
