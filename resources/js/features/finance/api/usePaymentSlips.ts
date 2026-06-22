import { useMutation, useQuery, useQueryClient, type UseQueryResult } from '@tanstack/react-query';
import { apiClient } from '../../../api/client';
import type { ApiResource } from '../../../types/user';
import type { PaginatedResponse } from '../../../types/pagination';
import type { PaymentSlip, PaymentSlipQuery, SubmitPaymentSlipRequest } from '../types/finance';

export const PAYMENT_SLIPS_QUERY_KEY = ['payment-slips'] as const;

/**
 * GET /api/v1/payment-slips?status=&student_id= — paginated. For a `parent`
 * role the API scopes results to their own wards' slips; for finance staff it
 * returns the school's full queue (PaymentSlipController::index).
 */
export function usePaymentSlips(query: PaymentSlipQuery = {}): UseQueryResult<PaginatedResponse<PaymentSlip>> {
    return useQuery({
        queryKey: [...PAYMENT_SLIPS_QUERY_KEY, 'list', query],
        queryFn: async () => {
            const { data } = await apiClient.get<PaginatedResponse<PaymentSlip>>('/payment-slips', {
                params: { per_page: 20, ...query },
            });
            return data;
        },
        staleTime: 15 * 1000,
    });
}

/** GET /api/v1/payment-slips/{paymentSlip} — single slip with logs/receipt eager-loaded. */
export function usePaymentSlip(id: string | undefined): UseQueryResult<PaymentSlip> {
    return useQuery({
        queryKey: [...PAYMENT_SLIPS_QUERY_KEY, 'detail', id],
        queryFn: async () => {
            const { data } = await apiClient.get<ApiResource<PaymentSlip>>(`/payment-slips/${id}`);
            return data.data;
        },
        enabled: Boolean(id),
    });
}

/**
 * POST /api/v1/payment-slips — multipart/form-data because slip_attachments
 * are real files. Built as FormData; axios then sets the multipart
 * Content-Type header (with the correct boundary) itself. Never set
 * Content-Type manually here — a hardcoded `multipart/form-data` header
 * without a boundary breaks the upload server-side.
 *
 * `allocation` is a JSON array per the API contract, but multipart bodies are
 * flat key/value — it's appended as `allocation[<i>][fee_type]` etc. so
 * Laravel's array-of-objects validation (`allocation.*.fee_type`) parses it
 * the same way the spec's example bracket-notation arrays do.
 */
export function useSubmitPaymentSlip() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({
            payload,
            files,
        }: {
            payload: SubmitPaymentSlipRequest;
            files: File[];
        }) => {
            const formData = new FormData();

            formData.append('student_id', payload.student_id);
            if (payload.payment_method_id) {
                formData.append('payment_method_id', payload.payment_method_id);
            }
            if (payload.bank_name) {
                formData.append('bank_name', payload.bank_name);
            }
            if (payload.branch_name) {
                formData.append('branch_name', payload.branch_name);
            }
            if (payload.teller_number) {
                formData.append('teller_number', payload.teller_number);
            }
            if (payload.transaction_reference) {
                formData.append('transaction_reference', payload.transaction_reference);
            }
            formData.append('depositor_name', payload.depositor_name);
            formData.append('deposit_date', payload.deposit_date);
            formData.append('total_amount', String(payload.total_amount));
            formData.append('currency', payload.currency ?? 'TZS');
            if (payload.notes) {
                formData.append('notes', payload.notes);
            }

            payload.allocation.forEach((line, index) => {
                formData.append(`allocation[${index}][fee_type]`, line.fee_type);
                formData.append(`allocation[${index}][amount]`, String(line.amount));
                formData.append(`allocation[${index}][academic_session_id]`, line.academic_session_id);
            });

            files.forEach((file) => {
                formData.append('slip_attachments[]', file);
            });

            const { data } = await apiClient.post<ApiResource<PaymentSlip>>('/payment-slips', formData);
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: PAYMENT_SLIPS_QUERY_KEY });
        },
    });
}
