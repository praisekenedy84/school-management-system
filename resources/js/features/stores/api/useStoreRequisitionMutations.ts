import { useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '../../../api/client';
import type { ApiResource } from '../../../types/user';
import type {
    AddRequisitionToPurchaseRequest,
    ApproveStoreRequisitionRequest,
    CloseRequisitionLineRequest,
    IssueStoreRequisitionRequest,
    PurchaseRequest,
    RejectStoreRequisitionRequest,
    StoreRequisition,
    StoreRequisitionRequest,
} from '../types/stores';
import { INVENTORY_ITEMS_QUERY_KEY } from './useInventoryItems';
import { PURCHASE_REQUESTS_QUERY_KEY } from './usePurchaseRequests';
import { STORE_REQUISITIONS_QUERY_KEY } from './useStoreRequisitions';
import { STOCK_MOVEMENTS_QUERY_KEY } from './useStockMovements';

/** POST /api/v1/store-requisitions */
export function useCreateStoreRequisition() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (payload: StoreRequisitionRequest) => {
            const { data } = await apiClient.post<ApiResource<StoreRequisition>>('/store-requisitions', payload);
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: STORE_REQUISITIONS_QUERY_KEY });
        },
    });
}

/** PUT /api/v1/store-requisitions/{storeRequisition} */
export function useUpdateStoreRequisition() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ id, payload }: { id: string; payload: StoreRequisitionRequest }) => {
            const { data } = await apiClient.put<ApiResource<StoreRequisition>>(
                `/store-requisitions/${id}`,
                payload,
            );
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: STORE_REQUISITIONS_QUERY_KEY });
        },
    });
}

/** POST /api/v1/store-requisitions/{storeRequisition}/submit */
export function useSubmitStoreRequisition() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (id: string) => {
            const { data } = await apiClient.post<ApiResource<StoreRequisition>>(
                `/store-requisitions/${id}/submit`,
            );
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: STORE_REQUISITIONS_QUERY_KEY });
        },
    });
}

/** POST /api/v1/store-requisitions/{storeRequisition}/approve */
export function useApproveStoreRequisition() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ id, payload }: { id: string; payload?: ApproveStoreRequisitionRequest }) => {
            const { data } = await apiClient.post<ApiResource<StoreRequisition>>(
                `/store-requisitions/${id}/approve`,
                payload ?? {},
            );
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: STORE_REQUISITIONS_QUERY_KEY });
        },
    });
}

/** POST /api/v1/store-requisitions/{storeRequisition}/reject */
export function useRejectStoreRequisition() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ id, payload }: { id: string; payload: RejectStoreRequisitionRequest }) => {
            const { data } = await apiClient.post<ApiResource<StoreRequisition>>(
                `/store-requisitions/${id}/reject`,
                payload,
            );
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: STORE_REQUISITIONS_QUERY_KEY });
        },
    });
}

/** POST /api/v1/store-requisitions/{storeRequisition}/issue */
export function useIssueStoreRequisition() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ id, payload }: { id: string; payload: IssueStoreRequisitionRequest }) => {
            const { data } = await apiClient.post<ApiResource<StoreRequisition>>(
                `/store-requisitions/${id}/issue`,
                payload,
            );
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: STORE_REQUISITIONS_QUERY_KEY });
            queryClient.invalidateQueries({ queryKey: INVENTORY_ITEMS_QUERY_KEY });
            queryClient.invalidateQueries({ queryKey: STOCK_MOVEMENTS_QUERY_KEY });
        },
    });
}

/** POST /api/v1/store-requisitions/{storeRequisition}/close-line */
export function useCloseRequisitionLine() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ id, payload }: { id: string; payload: CloseRequisitionLineRequest }) => {
            const { data } = await apiClient.post<ApiResource<StoreRequisition>>(
                `/store-requisitions/${id}/close-line`,
                payload,
            );
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: STORE_REQUISITIONS_QUERY_KEY });
        },
    });
}

/** POST /api/v1/store-requisitions/{storeRequisition}/add-to-purchase */
export function useAddRequisitionToPurchase() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ id, payload }: { id: string; payload: AddRequisitionToPurchaseRequest }) => {
            const { data } = await apiClient.post<ApiResource<PurchaseRequest>>(
                `/store-requisitions/${id}/add-to-purchase`,
                payload,
            );
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: STORE_REQUISITIONS_QUERY_KEY });
            queryClient.invalidateQueries({ queryKey: PURCHASE_REQUESTS_QUERY_KEY });
        },
    });
}

/** POST /api/v1/store-requisitions/{storeRequisition}/cancel */
export function useCancelStoreRequisition() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (id: string) => {
            const { data } = await apiClient.post<ApiResource<StoreRequisition>>(
                `/store-requisitions/${id}/cancel`,
            );
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: STORE_REQUISITIONS_QUERY_KEY });
        },
    });
}
