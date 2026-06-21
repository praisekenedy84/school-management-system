<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessment;

use App\Models\Assessment;
use App\Models\ResultRecord;
use App\Models\Scopes\SchoolScope;
use App\Models\Student;
use App\Models\TeacherAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class EnterMarkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ResultRecord::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'uuid', Rule::exists('students', 'id')],
            'assessment_id' => ['required', 'uuid', Rule::exists('assessments', 'id')],
            'score' => ['nullable', 'numeric', 'min:0', function ($attribute, $value, $fail) {
                if ($value === null) {
                    return;
                }

                $assessment = Assessment::withoutGlobalScope(SchoolScope::class)->find($this->input('assessment_id'));

                if ($assessment !== null && (float) $value > (float) $assessment->max_score) {
                    $fail("The score must not be greater than the assessment's max score ({$assessment->max_score}).");
                }
            }],
            'grade' => ['nullable', 'string', 'max:5'],
        ];
    }

    /**
     * Marks are entered per (student, assessment) — not per class — so a
     * teacher's authority to enter a mark is checked against whether they
     * hold ANY `TeacherAssignment` for the assessment's subject + academic
     * session (for any class), not a specific `teacher_assignment_id` like
     * `CreateAssignmentRequest` does. Admins (school_admin/tenant_admin)
     * and academic_director bypass this ownership check.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('assessment_id')) {
                return;
            }

            // Bypass BelongsToSchool: must see the TRUE record even if it
            // belongs to a different campus than the acting user, or a
            // cross-school id would silently resolve to null and skip
            // these checks (the exact bug Phase 1 fixed for guardians,
            // teacher assignments, admission, and promotion).
            $assessment = Assessment::withoutGlobalScope(SchoolScope::class)->find($this->input('assessment_id'));

            if ($assessment === null) {
                return;
            }

            if (! $validator->errors()->has('student_id')) {
                $student = Student::withoutGlobalScope(SchoolScope::class)->find($this->input('student_id'));

                if ($student?->school_id !== $assessment->school_id) {
                    $validator->errors()->add('student_id', 'The student must belong to the assessment\'s school.');

                    return;
                }
            }

            $user = $this->user();

            if ($user === null || $user->hasRole(['tenant_admin', 'school_admin', 'academic_director'])) {
                return;
            }

            $hasAssignment = TeacherAssignment::query()
                ->where('teacher_id', $user->id)
                ->where('subject_id', $assessment->subject_id)
                ->where('academic_session_id', $assessment->academic_session_id)
                ->exists();

            if (! $hasAssignment) {
                $validator->errors()->add(
                    'assessment_id',
                    'You may only enter marks for assessments in a subject/session you are assigned to teach.'
                );
            }
        });
    }
}
