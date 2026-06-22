import { useQuery, type UseQueryResult } from '@tanstack/react-query';
import { apiClient } from '../../../api/client';
import type { DashboardSummary, WardSummary } from '../types/dashboard';

/** GET /api/v1/dashboard/summary — school-staff cross-module counts. */
export function useDashboardSummary(enabled: boolean): UseQueryResult<DashboardSummary> {
    return useQuery({
        queryKey: ['dashboard', 'summary'],
        queryFn: async () => {
            const { data } = await apiClient.get<{ data: DashboardSummary }>('/dashboard/summary');
            return data.data;
        },
        enabled,
    });
}

/** GET /api/v1/dashboard/wards — parent per-child summary. */
export function useDashboardWards(enabled: boolean): UseQueryResult<WardSummary[]> {
    return useQuery({
        queryKey: ['dashboard', 'wards'],
        queryFn: async () => {
            const { data } = await apiClient.get<{ data: WardSummary[] }>('/dashboard/wards');
            return data.data;
        },
        enabled,
    });
}
