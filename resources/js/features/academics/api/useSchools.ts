import { useQuery, type UseQueryResult } from '@tanstack/react-query';
import { apiClient } from '../../../api/client';
import type { ApiResource } from '../../../types/user';
import type { School } from '../types/academic';

export const SCHOOLS_QUERY_KEY = ['schools'] as const;

/** GET /api/v1/schools — feeds the tenant-admin "which school" picker. */
export function useSchools(): UseQueryResult<School[]> {
    return useQuery({
        queryKey: SCHOOLS_QUERY_KEY,
        queryFn: async () => {
            const { data } = await apiClient.get<ApiResource<School[]>>('/schools');
            return data.data;
        },
        staleTime: 5 * 60 * 1000,
    });
}
