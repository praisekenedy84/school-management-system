import { useQuery, useMutation, useQueryClient, type UseQueryResult } from '@tanstack/react-query';
import { apiClient } from '../../../api/client';

export interface GradingBand {
    min_percent: number;
    grade: string;
    label?: string;
}

export interface GradingScale {
    school_id: string;
    bands: GradingBand[];
}

export const GRADING_SCALE_QUERY_KEY = ['grading-scale'] as const;

export function useGradingScale(): UseQueryResult<GradingScale> {
    return useQuery({
        queryKey: GRADING_SCALE_QUERY_KEY,
        queryFn: async () => {
            const { data } = await apiClient.get<{ data: GradingScale }>('/grading-scale');
            return data.data;
        },
        staleTime: 5 * 60 * 1000,
    });
}

export function useUpdateGradingScale() {
    const queryClient = useQueryClient();

    return useMutation({
        mutationFn: async (bands: GradingBand[]) => {
            const { data } = await apiClient.put<{ data: GradingScale }>('/grading-scale', { bands });
            return data.data;
        },
        onSuccess: () => {
            queryClient.invalidateQueries({ queryKey: GRADING_SCALE_QUERY_KEY });
        },
    });
}

/** Client-side grade preview matching server GradingScaleService. */
export function gradeForScore(score: number | null, maxScore: number, bands: GradingBand[]): string {
    if (score === null || maxScore <= 0) return '';
    const percent = (score / maxScore) * 100;
    const sorted = [...bands].sort((a, b) => b.min_percent - a.min_percent);
    for (const band of sorted) {
        if (percent >= band.min_percent) return band.grade;
    }
    return '';
}
