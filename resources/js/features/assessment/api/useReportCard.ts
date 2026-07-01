import { useMutation, useQuery, useQueryClient, type UseQueryResult } from '@tanstack/react-query';
import { apiClient } from '../../../api/client';
import { downloadBlobResponse } from '../../../lib/downloadFile';
import type { ApiResource } from '../../../types/user';
import type {
    BulkGenerateReportCardRequest,
    BulkGenerateReportCardResponse,
    GenerateReportCardRequest,
    ReportCard,
} from '../types/assessment';

export const REPORT_CARD_QUERY_KEY = ['report-card'] as const;

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
                if (error?.response?.status === 403 && error?.response?.data?.withheld) {
                    return {
                        id: '',
                        school_id: '',
                        student_id: studentId!,
                        academic_session_id: academicSessionId!,
                        file_path: null,
                        withheld: true,
                        withheld_reason: error.response.data?.message ?? 'Report card unavailable.',
                        generated_by: null,
                        generated_at: null,
                    };
                }
                throw error;
            }
        },
        enabled: Boolean(studentId && academicSessionId),
    });
}

export function useGenerateReportCard(studentId: string | undefined) {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (payload: GenerateReportCardRequest) => {
            const { data } = await apiClient.post(`/students/${studentId}/report-card`, payload);
            return data as { message: string; data?: ReportCard; withheld?: boolean };
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: REPORT_CARD_QUERY_KEY });
        },
    });
}

export function useBulkGenerateReportCards() {
    return useMutation({
        mutationFn: async (payload: BulkGenerateReportCardRequest) => {
            const { data } = await apiClient.post<BulkGenerateReportCardResponse>('/report-cards/bulk', payload);
            return data;
        },
    });
}

export async function downloadStudentReportCard(
    studentId: string,
    academicSessionId: string,
    filename = 'report-card.pdf',
): Promise<void> {
    const response = await apiClient.get(`/students/${studentId}/report-card/download`, {
        params: { academic_session_id: academicSessionId },
        responseType: 'blob',
    });
    downloadBlobResponse(response, filename);
}

export async function downloadClassReportCards(
    classId: string,
    academicSessionId: string,
    filename = 'class-report-cards.pdf',
): Promise<void> {
    const response = await apiClient.get('/report-cards/class-download', {
        params: { class_id: classId, academic_session_id: academicSessionId },
        responseType: 'blob',
    });
    downloadBlobResponse(response, filename);
}
