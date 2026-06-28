import { useQuery, type UseQueryResult } from '@tanstack/react-query';
import { apiClient } from '../../../api/client';
import type { InventoryItem, InventoryItemQuery, InventoryValuation } from '../types/stores';

export const INVENTORY_ITEMS_QUERY_KEY = ['inventory-items'] as const;

/** GET /api/v1/inventory-items — non-paginated catalog list. */
export function useInventoryItems(query: InventoryItemQuery = {}): UseQueryResult<InventoryItem[]> {
    return useQuery({
        queryKey: [...INVENTORY_ITEMS_QUERY_KEY, 'list', query],
        queryFn: async () => {
            const { data } = await apiClient.get<{ data: InventoryItem[] }>('/inventory-items', {
                params: query,
            });
            return data.data;
        },
        staleTime: 60 * 1000,
    });
}

/** GET /api/v1/inventory-items/low-stock */
export function useLowStockInventoryItems(): UseQueryResult<InventoryItem[]> {
    return useQuery({
        queryKey: [...INVENTORY_ITEMS_QUERY_KEY, 'low-stock'],
        queryFn: async () => {
            const { data } = await apiClient.get<{ data: InventoryItem[] }>('/inventory-items/low-stock');
            return data.data;
        },
        staleTime: 30 * 1000,
    });
}

/** GET /api/v1/inventory-items/valuation — PRD §5 stock valuation summary. */
export function useInventoryValuation(): UseQueryResult<InventoryValuation> {
    return useQuery({
        queryKey: [...INVENTORY_ITEMS_QUERY_KEY, 'valuation'],
        queryFn: async () => {
            const { data } = await apiClient.get<{ data: InventoryValuation }>('/inventory-items/valuation');
            return data.data;
        },
        staleTime: 60 * 1000,
    });
}
