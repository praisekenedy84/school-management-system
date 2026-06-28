import { useQuery, type UseQueryResult } from '@tanstack/react-query';
import { apiClient } from '../../../api/client';
import type { ApiResource } from '../../../types/user';
import type { StoreRequisition, StoreRequisitionQuery } from '../types/stores';

export const STORE_REQUISITIONS_QUERY_KEY = ['store-requisitions'] as const;

/**
 * GET /api/v1/store-requisitions — non-paginated list. kitchen_staff without
 * elevated roles only see their own requisitions (server-scoped).
 */
export function useStoreRequisitions(query: StoreRequisitionQuery = {}): UseQueryResult<StoreRequisition[]> {
    return useQuery({
        queryKey: [...STORE_REQUISITIONS_QUERY_KEY, 'list', query],
        queryFn: async () => {
            const { data } = await apiClient.get<{ data: StoreRequisition[] }>('/store-requisitions', {
                params: query,
            });
            return data.data;
        },
        staleTime: 15 * 1000,
    });
}

/** GET /api/v1/store-requisitions/{storeRequisition} */
export function useStoreRequisition(id: string | undefined): UseQueryResult<StoreRequisition> {
    return useQuery({
        queryKey: [...STORE_REQUISITIONS_QUERY_KEY, 'detail', id],
        queryFn: async () => {
            const { data } = await apiClient.get<ApiResource<StoreRequisition>>(`/store-requisitions/${id}`);
            return data.data;
        },
        enabled: Boolean(id),
    });
}
