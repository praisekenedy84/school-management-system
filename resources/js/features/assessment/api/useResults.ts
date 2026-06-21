import { useMutation, useQuery, useQueryClient, type UseQueryResult } from '@tanstack/react-query';
import { apiClient } from '../../../api/client';
import type { ApiResource } from '../../../types/user';
import type { PaginatedResponse } from '../../../types/pagination';
import type { EnterMarkRequest, ResultQuery, ResultRecord } from '../types/assessment';

export const RESULTS_QUERY_KEY = ['results'] as const;

/**
 * GET /api/v1/results?assessment_id=&student_id=&academic_session_id= —
 * latest-version-only by default (pass `all_versions: true` for full
 * history). Paginated like the other list endpoints.
 */
export function useResults(query: ResultQuery = {}): UseQueryResult<PaginatedResponse<ResultRecord>> {
    return useQuery({
        queryKey: [...RESULTS_QUERY_KEY, 'list', query],
        queryFn: async () => {
            const { data } = await apiClient.get<PaginatedResponse<ResultRecord>>('/results', {
                params: { per_page: 100, ...query },
            });
            return data;
        },
        staleTime: 15 * 1000,
    });
}

/**
 * POST /api/v1/results — enters (or corrects) a mark for one
 * (student, assessment) pair. Append-only: the API creates a new versioned
 * ResultRecord rather than mutating an existing one.
 */
export function useSaveResult() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (payload: EnterMarkRequest) => {
            const { data } = await apiClient.post<ApiResource<ResultRecord>>('/results', payload);
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: RESULTS_QUERY_KEY });
        },
    });
}
