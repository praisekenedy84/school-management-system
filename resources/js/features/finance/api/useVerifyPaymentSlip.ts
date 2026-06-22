import { useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '../../../api/client';
import type { ApiResource } from '../../../types/user';
import type { PaymentSlip, VerifyPaymentSlipRequest } from '../types/finance';
import { PAYMENT_SLIPS_QUERY_KEY } from './usePaymentSlips';

/**
 * POST /api/v1/payment-slips/{id}/verify — idempotent server-side (calling
 * twice on an already-verified slip returns 422); gated to
 * finance_manager/accountant/school_admin/tenant_admin via PaymentSlipPolicy.
 * Generates the receipt as part of the same transaction.
 */
export function useVerifyPaymentSlip() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ id, payload }: { id: string; payload: VerifyPaymentSlipRequest }) => {
            const { data } = await apiClient.post<ApiResource<PaymentSlip>>(
                `/payment-slips/${id}/verify`,
                payload,
            );
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: PAYMENT_SLIPS_QUERY_KEY });
        },
    });
}
