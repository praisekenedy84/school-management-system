import { useQuery, type UseQueryResult } from '@tanstack/react-query';
import { apiClient } from '../../../api/client';
import type { StockMovement, StockMovementQuery } from '../types/stores';

export const STOCK_MOVEMENTS_QUERY_KEY = ['stock-movements'] as const;

/** GET /api/v1/stock-movements — non-paginated movement ledger. */
export function useStockMovements(query: StockMovementQuery = {}): UseQueryResult<StockMovement[]> {
    return useQuery({
        queryKey: [...STOCK_MOVEMENTS_QUERY_KEY, 'list', query],
        queryFn: async () => {
            const { data } = await apiClient.get<{ data: StockMovement[] }>('/stock-movements', {
                params: query,
            });
            return data.data;
        },
        staleTime: 15 * 1000,
    });
}
