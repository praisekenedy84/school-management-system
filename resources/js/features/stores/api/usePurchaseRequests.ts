import { useQuery, type UseQueryResult } from '@tanstack/react-query';
import { apiClient } from '../../../api/client';
import type { ApiResource } from '../../../types/user';
import type { PurchaseFulfillment, PurchaseRequest, PurchaseRequestQuery } from '../types/stores';

export const PURCHASE_REQUESTS_QUERY_KEY = ['purchase-requests'] as const;

/** GET /api/v1/purchase-requests — non-paginated list. */
export function usePurchaseRequests(query: PurchaseRequestQuery = {}): UseQueryResult<PurchaseRequest[]> {
    return useQuery({
        queryKey: [...PURCHASE_REQUESTS_QUERY_KEY, 'list', query],
        queryFn: async () => {
            const { data } = await apiClient.get<{ data: PurchaseRequest[] }>('/purchase-requests', {
                params: query,
            });
            return data.data;
        },
        staleTime: 15 * 1000,
    });
}

/** GET /api/v1/purchase-requests/{purchaseRequest} */
export function usePurchaseRequest(id: string | undefined): UseQueryResult<PurchaseRequest> {
    return useQuery({
        queryKey: [...PURCHASE_REQUESTS_QUERY_KEY, 'detail', id],
        queryFn: async () => {
            const { data } = await apiClient.get<ApiResource<PurchaseRequest>>(`/purchase-requests/${id}`);
            return data.data;
        },
        enabled: Boolean(id),
    });
}

/** GET /api/v1/purchase-requests/{purchaseRequest}/fulfillment */
export function usePurchaseFulfillment(purchaseRequestId: string | undefined): UseQueryResult<PurchaseFulfillment> {
    return useQuery({
        queryKey: [...PURCHASE_REQUESTS_QUERY_KEY, 'fulfillment', purchaseRequestId],
        queryFn: async () => {
            const { data } = await apiClient.get<ApiResource<PurchaseFulfillment>>(
                `/purchase-requests/${purchaseRequestId}/fulfillment`,
            );
            return data.data;
        },
        enabled: Boolean(purchaseRequestId),
    });
}
