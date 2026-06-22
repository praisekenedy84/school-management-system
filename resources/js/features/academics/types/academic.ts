/** Mirrors App\Http\Resources\SchoolResource. */
export interface School {
    id: string;
    name: string;
    code: string | null;
}

/** Mirrors App\Http\Resources\ClassRoomResource. */
export interface ClassRoom {
    id: string;
    school_id: string;
    name: string;
    level: string | null;
}

/**
 * Body for POST/PUT /api/v1/classes (App\Http\Requests\Academic\ClassRoomRequest).
 * `school_id` is only read on create, and only matters for a tenant_admin
 * (no school of their own) — a school_admin's own school always wins
 * server-side regardless of what's sent.
 */
export interface ClassRoomRequest {
    name: string;
    level?: number | null;
    school_id?: string;
}

/** Mirrors App\Http\Resources\AcademicSessionResource. */
export interface AcademicSession {
    id: string;
    school_id: string;
    name: string;
    start_date: string | null;
    end_date: string | null;
    is_current: boolean;
}

/** Body for POST/PUT /api/v1/academic-sessions (App\Http\Requests\Academic\AcademicSessionRequest). */
export interface AcademicSessionRequest {
    name: string;
    start_date: string;
    end_date: string;
    is_current?: boolean;
    school_id?: string;
}

/** Mirrors App\Http\Resources\SubjectResource. */
export interface Subject {
    id: string;
    school_id: string;
    name: string;
    code: string | null;
    created_at: string | null;
    updated_at: string | null;
}

/** Body for POST/PUT /api/v1/subjects (App\Http\Requests\Academic\SubjectRequest). */
export interface SubjectRequest {
    name: string;
    code?: string | null;
    school_id?: string;
}

/** Mirrors App\Http\Resources\TeacherAssignmentResource. */
export interface TeacherAssignment {
    id: string;
    school_id: string;
    teacher_id: string;
    teacher_name: string | null;
    class_id: string;
    class_name: string | null;
    subject_id: string;
    subject_name: string | null;
    academic_session_id: string;
    academic_session_name: string | null;
    created_at: string | null;
}

/** Body for POST /api/v1/teacher-assignments (App\Http\Requests\Academic\TeacherAssignmentRequest). */
export interface CreateTeacherAssignmentRequest {
    teacher_id: string;
    class_id: string;
    subject_id: string;
    academic_session_id: string;
}

/** Mirrors App\Http\Resources\AssignmentResource. */
export interface Assignment {
    id: string;
    school_id: string;
    teacher_assignment_id: string;
    class_id: string | null;
    class_name: string | null;
    subject_name: string | null;
    teacher_name: string | null;
    title: string;
    description: string | null;
    due_at: string | null;
    published_at: string | null;
    is_published: boolean;
    created_by: string;
    created_at: string | null;
}

/** Body for POST /api/v1/assignments (App\Http\Requests\Academic\CreateAssignmentRequest). */
export interface CreateAssignmentRequest {
    teacher_assignment_id: string;
    title: string;
    description?: string | null;
    due_at?: string | null;
}
