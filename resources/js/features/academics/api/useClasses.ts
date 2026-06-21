import { useQuery, type UseQueryResult } from '@tanstack/react-query';
import { apiClient } from '../../../api/client';
import type { ClassRoom } from '../types/academic';

export const CLASSES_QUERY_KEY = ['classes'] as const;

/**
 * GET /api/v1/classes — read-only lookup (not paginated; the controller
 * returns the school's full class list via a bare JsonResource::collection,
 * so the envelope is `{ data: ClassRoom[] }`, unlike the paginated list
 * endpoints elsewhere in the app). Used to populate class pickers instead of
 * the free-text UUID inputs Phase 1 shipped (see StudentAdmissionPage /
 * PromoteEnrolmentForm TODOs — this hook is what those still need wiring to).
 */
export function useClasses(): UseQueryResult<ClassRoom[]> {
    return useQuery({
        queryKey: [...CLASSES_QUERY_KEY, 'list'],
        queryFn: async () => {
            const { data } = await apiClient.get<{ data: ClassRoom[] }>('/classes');
            return data.data;
        },
        staleTime: 5 * 60 * 1000,
    });
}
