import { useMutation, useQuery, useQueryClient, type UseQueryResult } from '@tanstack/react-query';
import { apiClient } from '../../../api/client';
import type { ApiResource } from '../../../types/user';
import type { PaginatedResponse } from '../../../types/pagination';
import type { FeeStructure, FeeStructureRequest } from '../types/finance';

export const FEE_STRUCTURES_QUERY_KEY = ['fee-structures'] as const;

export interface FeeStructureFilters {
    academic_session_id?: string;
    class_id?: string;
}

/** GET /api/v1/fee-structures?academic_session_id=&class_id= — paginated. */
export function useFeeStructures(
    filters: FeeStructureFilters = {},
): UseQueryResult<PaginatedResponse<FeeStructure>> {
    return useQuery({
        queryKey: [...FEE_STRUCTURES_QUERY_KEY, 'list', filters],
        queryFn: async () => {
            const { data } = await apiClient.get<PaginatedResponse<FeeStructure>>('/fee-structures', {
                params: { per_page: 50, ...filters },
            });
            return data;
        },
        staleTime: 30 * 1000,
    });
}

/** GET /api/v1/fee-structures/{feeStructure} */
export function useFeeStructure(id: string | undefined): UseQueryResult<FeeStructure> {
    return useQuery({
        queryKey: [...FEE_STRUCTURES_QUERY_KEY, 'detail', id],
        queryFn: async () => {
            const { data } = await apiClient.get<ApiResource<FeeStructure>>(`/fee-structures/${id}`);
            return data.data;
        },
        enabled: Boolean(id),
    });
}

/** POST /api/v1/fee-structures */
export function useCreateFeeStructure() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (payload: FeeStructureRequest) => {
            const { data } = await apiClient.post<ApiResource<FeeStructure>>('/fee-structures', payload);
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: FEE_STRUCTURES_QUERY_KEY });
        },
    });
}

/** PUT /api/v1/fee-structures/{feeStructure} */
export function useUpdateFeeStructure() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ id, payload }: { id: string; payload: FeeStructureRequest }) => {
            const { data } = await apiClient.put<ApiResource<FeeStructure>>(`/fee-structures/${id}`, payload);
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: FEE_STRUCTURES_QUERY_KEY });
        },
    });
}

/** DELETE /api/v1/fee-structures/{feeStructure} */
export function useDeleteFeeStructure() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (id: string) => {
            await apiClient.delete(`/fee-structures/${id}`);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: FEE_STRUCTURES_QUERY_KEY });
        },
    });
}
