/** Mirrors App\Http\Resources\GuardianResource. */
export interface Guardian {
    id: string;
    name: string;
    email: string;
    phone: string | null;
    relationship: string | null;
    is_primary: boolean;
}

/** Mirrors App\Http\Resources\EnrolmentResource. */
export interface Enrolment {
    id: string;
    school_id: string;
    student_id: string;
    class_id: string;
    class_name: string | null;
    stream_id: string | null;
    stream_name: string | null;
    academic_session_id: string;
    academic_session_name: string | null;
    residence_type: 'day' | 'boarding';
    status: string;
    enrolled_at: string | null;
    created_at: string | null;
    updated_at: string | null;
}

/** Mirrors App\Http\Resources\StudentResource. */
export interface Student {
    id: string;
    school_id: string;
    admission_number: string;
    first_name: string;
    last_name: string;
    full_name: string;
    date_of_birth: string | null;
    gender: string | null;
    residence_type: 'day' | 'boarding';
    status: string;
    admitted_at: string | null;
    photo_path: string | null;
    current_enrolment: Enrolment | null;
    enrolments: Enrolment[];
    guardians: Guardian[];
    created_at: string | null;
    updated_at: string | null;
}

/** Body for POST /api/v1/students (App\Http\Requests\Sis\AdmitStudentRequest). */
export interface AdmitStudentRequest {
    admission_number: string;
    first_name: string;
    last_name: string;
    date_of_birth?: string | null;
    gender?: string | null;
    status?: string | null;
    admitted_at?: string | null;
    photo_path?: string | null;
    class_id: string;
    stream_id?: string | null;
    academic_session_id: string;
    residence_type: 'day' | 'boarding';
    enrolled_at?: string | null;
}

/** Body for POST /api/v1/students/{student}/guardians (App\Http\Requests\Sis\LinkGuardianRequest). */
export interface LinkGuardianRequest {
    guardian_id: string;
    relationship?: string | null;
    is_primary?: boolean;
}

/** Body for POST /api/v1/enrolments/{enrolment}/promote (App\Http\Requests\Sis\PromoteEnrolmentRequest). */
export interface PromoteEnrolmentRequest {
    class_id: string;
    stream_id?: string | null;
    academic_session_id: string;
    residence_type?: 'day' | 'boarding' | null;
    enrolled_at?: string | null;
}
