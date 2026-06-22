import { useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '../../../api/client';
import type { ApiResource } from '../../../types/user';
import type { PaymentSlip, RejectPaymentSlipRequest } from '../types/finance';
import { PAYMENT_SLIPS_QUERY_KEY } from './usePaymentSlips';

/**
 * POST /api/v1/payment-slips/{id}/reject — same `verify` authorization gate
 * as useVerifyPaymentSlip (PaymentSlipPolicy::verify covers both actions).
 */
export function useRejectPaymentSlip() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ id, payload }: { id: string; payload: RejectPaymentSlipRequest }) => {
            const { data } = await apiClient.post<ApiResource<PaymentSlip>>(
                `/payment-slips/${id}/reject`,
                payload,
            );
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: PAYMENT_SLIPS_QUERY_KEY });
        },
    });
}
