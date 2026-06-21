<?php

declare(strict_types=1);

namespace App\Http\Requests\Attendance;

use App\Models\AcademicSession;
use App\Models\AttendanceRecord;
use App\Models\ClassRoom;
use App\Models\Scopes\SchoolScope;
use App\Models\Student;
use App\Models\TeacherAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RecordAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', AttendanceRecord::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'class_id' => ['required', 'uuid', Rule::exists('classes', 'id')],
            'academic_session_id' => ['required', 'uuid', Rule::exists('academic_sessions', 'id')],
            'attendance_date' => ['required', 'date'],
            'period' => ['nullable', 'string', 'max:50'],
            'records' => ['required', 'array', 'min:1'],
            'records.*.student_id' => ['required', 'uuid', Rule::exists('students', 'id')],
            'records.*.status' => ['required', 'string', Rule::in(['present', 'absent', 'late', 'excused'])],
            'records.*.note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * `classes`/`academic_sessions`/`students` existence checks alone don't
     * constrain school — a school_admin (or an unconstrained tenant_admin)
     * could otherwise record attendance against a class/session/student
     * belonging to a DIFFERENT campus. Validate internal consistency
     * (class, session, and every submitted student all share one
     * school_id) rather than only comparing against the acting user's own
     * school_id — that check alone is a no-op for tenant_admin (whose
     * school_id is null) and was exactly how Phase 1's cross-school bugs
     * slipped through. Mirrors AdmitStudentRequest/TeacherAssignmentRequest.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->hasAny(['class_id', 'academic_session_id'])) {
                return;
            }

            // Bypass BelongsToSchool: must see the TRUE records even if they
            // belong to a different campus than the acting user, or a
            // cross-school id would silently resolve to null and skip this check.
            $classRoom = ClassRoom::withoutGlobalScope(SchoolScope::class)->find($this->input('class_id'));
            $academicSession = AcademicSession::withoutGlobalScope(SchoolScope::class)->find($this->input('academic_session_id'));

            if ($classRoom === null || $academicSession === null) {
                return;
            }

            if ($academicSession->school_id !== $classRoom->school_id) {
                $validator->errors()->add('class_id', 'The class and academic session must belong to the same school.');

                return;
            }

            $studentIds = collect($this->input('records', []))->pluck('student_id')->filter()->unique();

            if ($studentIds->isEmpty()) {
                return;
            }

            $mismatchedStudentCount = Student::withoutGlobalScope(SchoolScope::class)
                ->whereIn('id', $studentIds)
                ->where('school_id', '!=', $classRoom->school_id)
                ->count();

            if ($mismatchedStudentCount > 0) {
                $validator->errors()->add('records', 'Every student must belong to the same school as the class.');

                return;
            }

            // `create` is granted to every teacher (RULES.md §5: attendance.take
            // is scope:class), but without this check ANY teacher could
            // upsert (effectively overwrite) attendance for a class they
            // don't teach. Admins/academic_director bypass — they
            // legitimately manage the whole school.
            $user = $this->user();

            if ($user === null || $user->hasRole(['tenant_admin', 'school_admin', 'academic_director'])) {
                return;
            }

            $teachesThisClass = TeacherAssignment::query()
                ->where('teacher_id', $user->id)
                ->where('class_id', $classRoom->id)
                ->where('academic_session_id', $academicSession->id)
                ->exists();

            if (! $teachesThisClass) {
                $validator->errors()->add('class_id', 'You may only record attendance for a class/session you are assigned to teach.');
            }
        });
    }
}
