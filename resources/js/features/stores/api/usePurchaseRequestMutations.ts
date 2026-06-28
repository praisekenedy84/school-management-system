import { useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '../../../api/client';
import type { ApiResource } from '../../../types/user';
import type {
    AmendPurchaseRequestRequest,
    ApprovePurchaseRequestRequest,
    FulfillPurchaseRequestRequest,
    PurchaseRequest,
    PurchaseRequestForm,
    RejectPurchaseRequestRequest,
} from '../types/stores';
import { INVENTORY_ITEMS_QUERY_KEY } from './useInventoryItems';
import { PURCHASE_REQUESTS_QUERY_KEY } from './usePurchaseRequests';
import { STOCK_MOVEMENTS_QUERY_KEY } from './useStockMovements';

/** POST /api/v1/purchase-requests */
export function useCreatePurchaseRequest() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (payload: PurchaseRequestForm) => {
            const { data } = await apiClient.post<ApiResource<PurchaseRequest>>('/purchase-requests', payload);
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: PURCHASE_REQUESTS_QUERY_KEY });
        },
    });
}

/** PUT /api/v1/purchase-requests/{purchaseRequest} */
export function useUpdatePurchaseRequest() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ id, payload }: { id: string; payload: PurchaseRequestForm }) => {
            const { data } = await apiClient.put<ApiResource<PurchaseRequest>>(`/purchase-requests/${id}`, payload);
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: PURCHASE_REQUESTS_QUERY_KEY });
        },
    });
}

/** POST /api/v1/purchase-requests/{purchaseRequest}/submit */
export function useSubmitPurchaseRequest() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (id: string) => {
            const { data } = await apiClient.post<ApiResource<PurchaseRequest>>(
                `/purchase-requests/${id}/submit`,
            );
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: PURCHASE_REQUESTS_QUERY_KEY });
        },
    });
}

/** POST /api/v1/purchase-requests/{purchaseRequest}/approve */
export function useApprovePurchaseRequest() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ id, payload }: { id: string; payload?: ApprovePurchaseRequestRequest }) => {
            const { data } = await apiClient.post<ApiResource<PurchaseRequest>>(
                `/purchase-requests/${id}/approve`,
                payload ?? {},
            );
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: PURCHASE_REQUESTS_QUERY_KEY });
        },
    });
}

/** POST /api/v1/purchase-requests/{purchaseRequest}/amend */
export function useAmendPurchaseRequest() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ id, payload }: { id: string; payload: AmendPurchaseRequestRequest }) => {
            const { data } = await apiClient.post<ApiResource<PurchaseRequest>>(
                `/purchase-requests/${id}/amend`,
                payload,
            );
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: PURCHASE_REQUESTS_QUERY_KEY });
        },
    });
}

/** POST /api/v1/purchase-requests/{purchaseRequest}/reject */
export function useRejectPurchaseRequest() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ id, payload }: { id: string; payload: RejectPurchaseRequestRequest }) => {
            const { data } = await apiClient.post<ApiResource<PurchaseRequest>>(
                `/purchase-requests/${id}/reject`,
                payload,
            );
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: PURCHASE_REQUESTS_QUERY_KEY });
        },
    });
}

/**
 * POST /api/v1/purchase-requests/{purchaseRequest}/fulfill — multipart when
 * attachments are present.
 */
export function useFulfillPurchaseRequest() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({
            id,
            payload,
            files = [],
        }: {
            id: string;
            payload: FulfillPurchaseRequestRequest;
            files?: File[];
        }) => {
            if (files.length === 0) {
                const { data } = await apiClient.post<ApiResource<PurchaseRequest>>(
                    `/purchase-requests/${id}/fulfill`,
                    payload,
                );
                return data.data;
            }

            const formData = new FormData();
            if (payload.supplier_name) {
                formData.append('supplier_name', payload.supplier_name);
            }
            if (payload.supplier_reference) {
                formData.append('supplier_reference', payload.supplier_reference);
            }
            formData.append('fulfillment_date', payload.fulfillment_date);
            if (payload.notes) {
                formData.append('notes', payload.notes);
            }
            payload.lines.forEach((line, index) => {
                formData.append(`lines[${index}][purchase_request_line_id]`, line.purchase_request_line_id);
                formData.append(`lines[${index}][received_quantity]`, String(line.received_quantity));
                formData.append(`lines[${index}][actual_unit_cost]`, String(line.actual_unit_cost));
                if (line.line_notes) {
                    formData.append(`lines[${index}][line_notes]`, line.line_notes);
                }
            });
            files.forEach((file) => {
                formData.append('attachments[]', file);
            });

            const { data } = await apiClient.post<ApiResource<PurchaseRequest>>(
                `/purchase-requests/${id}/fulfill`,
                formData,
            );
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: PURCHASE_REQUESTS_QUERY_KEY });
            queryClient.invalidateQueries({ queryKey: INVENTORY_ITEMS_QUERY_KEY });
            queryClient.invalidateQueries({ queryKey: STOCK_MOVEMENTS_QUERY_KEY });
        },
    });
}
