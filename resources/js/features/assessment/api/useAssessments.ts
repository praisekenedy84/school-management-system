import { useMutation, useQuery, useQueryClient, type UseQueryResult } from '@tanstack/react-query';
import { apiClient } from '../../../api/client';
import type { ApiResource } from '../../../types/user';
import type { PaginatedResponse } from '../../../types/pagination';
import type { Assessment, AssessmentRequest } from '../types/assessment';
import { RESULTS_QUERY_KEY } from './useResults';

export const ASSESSMENTS_QUERY_KEY = ['assessments'] as const;

export interface AssessmentFilters {
    subject_id?: string;
    academic_session_id?: string;
}

/** GET /api/v1/assessments?subject_id=&academic_session_id= — paginated. */
export function useAssessments(filters: AssessmentFilters = {}): UseQueryResult<PaginatedResponse<Assessment>> {
    return useQuery({
        queryKey: [...ASSESSMENTS_QUERY_KEY, 'list', filters],
        queryFn: async () => {
            const { data } = await apiClient.get<PaginatedResponse<Assessment>>('/assessments', {
                params: { per_page: 50, ...filters },
            });
            return data;
        },
        staleTime: 30 * 1000,
    });
}

/** GET /api/v1/assessments/{assessment} */
export function useAssessment(id: string | undefined): UseQueryResult<Assessment> {
    return useQuery({
        queryKey: [...ASSESSMENTS_QUERY_KEY, 'detail', id],
        queryFn: async () => {
            const { data } = await apiClient.get<ApiResource<Assessment>>(`/assessments/${id}`);
            return data.data;
        },
        enabled: Boolean(id),
    });
}

/** POST /api/v1/assessments */
export function useCreateAssessment() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (payload: AssessmentRequest) => {
            const { data } = await apiClient.post<ApiResource<Assessment>>('/assessments', payload);
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ASSESSMENTS_QUERY_KEY });
        },
    });
}

/** PUT /api/v1/assessments/{assessment} */
export function useUpdateAssessment() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async ({ id, payload }: { id: string; payload: AssessmentRequest }) => {
            const { data } = await apiClient.put<ApiResource<Assessment>>(`/assessments/${id}`, payload);
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ASSESSMENTS_QUERY_KEY });
        },
    });
}

/** DELETE /api/v1/assessments/{assessment} */
export function useDeleteAssessment() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (id: string) => {
            await apiClient.delete(`/assessments/${id}`);
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ASSESSMENTS_QUERY_KEY });
        },
    });
}

/**
 * POST /api/v1/assessments/{assessment}/publish — no body; publishes every
 * latest-version ResultRecord for the assessment. Invalidates both
 * assessments (in case a published flag surfaces there later) and results
 * (so MarkEntryPage immediately shows the published badge).
 */
export function usePublishAssessment() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (id: string) => {
            const { data } = await apiClient.post(`/assessments/${id}/publish`);
            return data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: ASSESSMENTS_QUERY_KEY });
            queryClient.invalidateQueries({ queryKey: RESULTS_QUERY_KEY });
        },
    });
}
