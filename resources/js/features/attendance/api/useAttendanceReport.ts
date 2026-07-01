import { useQuery, type UseQueryResult } from '@tanstack/react-query';
import { apiClient } from '../../../api/client';
import type { AttendanceRecord } from '../types/attendance';
import type { PaginatedResponse } from '../../../types/pagination';

export interface AttendanceSummary {
    total_students: number;
    total_records: number;
    present: number;
    absent: number;
    late: number;
    excused: number;
    attendance_percentage: number;
}

export interface AttendanceReportFilters {
    class_id?: string;
    attendance_date?: string;
    date_from?: string;
    date_to?: string;
    search?: string;
    page?: number;
}

export interface AttendanceReportResponse extends PaginatedResponse<AttendanceRecord> {
    summary: AttendanceSummary;
}

/** GET /api/v1/attendance/report */
export function useAttendanceReport(
    filters: AttendanceReportFilters,
): UseQueryResult<AttendanceReportResponse> {
    return useQuery({
        queryKey: ['attendance', 'report', filters],
        queryFn: async () => {
            const { data } = await apiClient.get<AttendanceReportResponse>('/attendance/report', {
                params: {
                    per_page: 30,
                    ...Object.fromEntries(
                        Object.entries(filters).filter(([, value]) => value !== '' && value != null),
                    ),
                },
            });
            return data;
        },
        staleTime: 30 * 1000,
    });
}
