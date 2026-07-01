/** Mirrors App\Http\Resources\AssessmentResource. */
export interface Assessment {
    id: string;
    school_id: string;
    subject_id: string;
    subject_name: string | null;
    academic_session_id: string;
    academic_session_name: string | null;
    name: string;
    category: string;
    category_label: string | null;
    weight: number;
    max_score: number;
    created_by: string | null;
    created_at: string | null;
    updated_at: string | null;
}

/** Body for POST/PUT /api/v1/assessments (App\Http\Requests\Assessment\AssessmentRequest). */
export interface AssessmentRequest {
    subject_id: string;
    academic_session_id: string;
    name: string;
    category: string;
    weight: number;
    max_score: number;
}

/** Mirrors App\Http\Resources\ResultRecordResource. */
export interface ResultRecord {
    id: string;
    school_id: string;
    student_id: string;
    student_name: string | null;
    academic_session_id: string;
    subject_id: string;
    subject_name: string | null;
    assessment_id: string;
    assessment_name: string | null;
    score: number | null;
    grade: string | null;
    version: number;
    is_published: boolean;
    published_by: string | null;
    published_at: string | null;
    entered_by: string | null;
    created_at: string | null;
}

/** Body for POST /api/v1/results (App\Http\Requests\Assessment\EnterMarkRequest). */
export interface EnterMarkRequest {
    student_id: string;
    assessment_id: string;
    score?: number | null;
    grade?: string | null;
}

/** Query params for GET /api/v1/results. */
export interface ResultQuery {
    assessment_id?: string;
    student_id?: string;
    academic_session_id?: string;
    all_versions?: boolean;
}

/** Mirrors App\Http\Resources\ReportCardResource. */
export interface ReportCard {
    id: string;
    school_id: string;
    student_id: string;
    academic_session_id: string;
    file_path: string | null;
    withheld?: boolean;
    withheld_reason?: string | null;
    generated_by: string | null;
    generated_at: string | null;
}

/** Body for POST /api/v1/students/{student}/report-card. */
export interface GenerateReportCardRequest {
    academic_session_id: string;
}

/** Body for POST /api/v1/report-cards/bulk. */
export interface BulkGenerateReportCardRequest {
    class_id: string;
    academic_session_id: string;
}

export interface ExcludedReportCardStudent {
    student_id: string;
    student_name: string;
    reason: string;
}

/** Response from POST /api/v1/report-cards/bulk. */
export interface BulkGenerateReportCardResponse {
    message: string;
    included_count: number;
    excluded_students: ExcludedReportCardStudent[];
    file_path: string;
    download_url: string;
}
