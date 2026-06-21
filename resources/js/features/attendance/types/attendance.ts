/** One of the four statuses the backend accepts (RecordAttendanceRequest). */
export type AttendanceStatus = 'present' | 'absent' | 'late' | 'excused';

/** Mirrors App\Http\Resources\AttendanceRecordResource. */
export interface AttendanceRecord {
    id: string;
    school_id: string;
    student_id: string;
    student_name: string | null;
    class_id: string;
    academic_session_id: string;
    attendance_date: string | null;
    period: string | null;
    status: AttendanceStatus;
    note: string | null;
    recorded_by: string | null;
    created_at: string | null;
    updated_at: string | null;
}

/** Query params for GET /api/v1/attendance. */
export interface AttendanceQuery {
    class_id: string;
    attendance_date: string;
    period?: string | null;
}

/** One row of the `records[]` array in POST /api/v1/attendance. */
export interface AttendanceRecordInput {
    student_id: string;
    status: AttendanceStatus;
    note?: string | null;
}

/** Body for POST /api/v1/attendance (App\Http\Requests\Attendance\RecordAttendanceRequest). */
export interface RecordAttendanceRequest {
    class_id: string;
    academic_session_id: string;
    attendance_date: string;
    period?: string | null;
    records: AttendanceRecordInput[];
}
