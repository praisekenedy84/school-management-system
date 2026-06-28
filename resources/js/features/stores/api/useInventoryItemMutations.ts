import { useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '../../../api/client';
import type { ApiResource } from '../../../types/user';
import type { InventoryItem, InventoryItemRequest } from '../types/stores';
import { INVENTORY_ITEMS_QUERY_KEY } from './useInventoryItems';

/** POST /api/v1/inventory-items */
export function useCreateInventoryItem() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (payload: InventoryItemRequest) => {
            const { data } = await apiClient.post<ApiResource<InventoryItem>>('/inventory-items', payload);
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: INVENTORY_ITEMS_QUERY_KEY });
        },
    });
}

/** PUT /api/v1/inventory-items/{inventoryItem} */
export function useUpdateInventoryItem() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ id, payload }: { id: string; payload: InventoryItemRequest }) => {
            const { data } = await apiClient.put<ApiResource<InventoryItem>>(`/inventory-items/${id}`, payload);
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: INVENTORY_ITEMS_QUERY_KEY });
        },
    });
}

/** DELETE /api/v1/inventory-items/{inventoryItem} — soft-deactivates server-side. */
export function useDeleteInventoryItem() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (id: string) => {
            await apiClient.delete(`/inventory-items/${id}`);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: INVENTORY_ITEMS_QUERY_KEY });
        },
    });
}
