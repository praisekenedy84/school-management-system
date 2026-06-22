import { useMutation, useQuery, useQueryClient, type UseQueryResult } from '@tanstack/react-query';
import { apiClient } from '../../../api/client';
import type { ApiResource } from '../../../types/user';
import type { PaginatedResponse } from '../../../types/pagination';
import type { PaymentMethod, PaymentMethodRequest } from '../types/finance';

export const PAYMENT_METHODS_QUERY_KEY = ['payment-methods'] as const;

/** GET /api/v1/payment-methods — paginated. */
export function usePaymentMethods(): UseQueryResult<PaginatedResponse<PaymentMethod>> {
    return useQuery({
        queryKey: [...PAYMENT_METHODS_QUERY_KEY, 'list'],
        queryFn: async () => {
            const { data } = await apiClient.get<PaginatedResponse<PaymentMethod>>('/payment-methods', {
                params: { per_page: 50 },
            });
            return data;
        },
        staleTime: 60 * 1000,
    });
}

/** GET /api/v1/payment-methods/{paymentMethod} */
export function usePaymentMethod(id: string | undefined): UseQueryResult<PaymentMethod> {
    return useQuery({
        queryKey: [...PAYMENT_METHODS_QUERY_KEY, 'detail', id],
        queryFn: async () => {
            const { data } = await apiClient.get<ApiResource<PaymentMethod>>(`/payment-methods/${id}`);
            return data.data;
        },
        enabled: Boolean(id),
    });
}

/** POST /api/v1/payment-methods */
export function useCreatePaymentMethod() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (payload: PaymentMethodRequest) => {
            const { data } = await apiClient.post<ApiResource<PaymentMethod>>('/payment-methods', payload);
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: PAYMENT_METHODS_QUERY_KEY });
        },
    });
}

/** PUT /api/v1/payment-methods/{paymentMethod} */
export function useUpdatePaymentMethod() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ id, payload }: { id: string; payload: PaymentMethodRequest }) => {
            const { data } = await apiClient.put<ApiResource<PaymentMethod>>(`/payment-methods/${id}`, payload);
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: PAYMENT_METHODS_QUERY_KEY });
        },
    });
}

/** DELETE /api/v1/payment-methods/{paymentMethod} */
export function useDeletePaymentMethod() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (id: string) => {
            await apiClient.delete(`/payment-methods/${id}`);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: PAYMENT_METHODS_QUERY_KEY });
        },
    });
}
