import { useMutation, useQuery, useQueryClient, type UseQueryResult } from '@tanstack/react-query';
import { apiClient } from '../../../api/client';
import type { ApiResource } from '../../../types/user';
import type { GenerateReportCardRequest, ReportCard } from '../types/assessment';

export const REPORT_CARD_QUERY_KEY = ['report-card'] as const;

/**
 * GET /api/v1/students/{student}/report-card?academic_session_id= — returns
 * the ReportCard pointer once generated, or null on a 404 ("not generated
 * yet" is not an error to surface). Disabled until both ids are chosen.
 */
export function useReportCard(
    studentId: string | undefined,
    academicSessionId: string | undefined,
): UseQueryResult<ReportCard | null> {
    return useQuery({
        queryKey: [...REPORT_CARD_QUERY_KEY, studentId, academicSessionId],
        queryFn: async () => {
            try {
                const { data } = await apiClient.get<ApiResource<ReportCard>>(
                    `/students/${studentId}/report-card`,
                    { params: { academic_session_id: academicSessionId } },
                );
                return data.data;
            } catch (error: any) {
                if (error?.response?.status === 404) {
                    return null;
                }
                throw error;
            }
        },
        enabled: Boolean(studentId && academicSessionId),
    });
}

/**
 * POST /api/v1/students/{student}/report-card — queues PDF generation;
 * returns 202 with no ReportCard payload yet (the file renders
 * asynchronously on the `pdf` queue). Callers should poll/refetch
 * useReportCard after a short delay to see the generated pointer.
 */
export function useGenerateReportCard(studentId: string | undefined) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (payload: GenerateReportCardRequest) => {
            const { data } = await apiClient.post(`/students/${studentId}/report-card`, payload);
            return data as { message: string; student_id: string; academic_session_id: string };
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: REPORT_CARD_QUERY_KEY });
        },
    });
}
