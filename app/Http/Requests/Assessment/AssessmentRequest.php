<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessment;

use App\Models\AcademicSession;
use App\Models\Assessment;
use App\Models\Scopes\SchoolScope;
use App\Models\Subject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assessment = $this->route('assessment');

        return $assessment === null
            ? $this->user()?->can('create', Assessment::class) ?? false
            : $this->user()?->can('update', $assessment) ?? false;
    }

    public function rules(): array
    {
        $assessmentId = $this->route('assessment')?->id;

        return [
            'subject_id' => ['required', 'uuid', Rule::exists('subjects', 'id')],
            'academic_session_id' => ['required', 'uuid', Rule::exists('academic_sessions', 'id')],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('assessments', 'name')
                    ->where('subject_id', $this->input('subject_id'))
                    ->where('academic_session_id', $this->input('academic_session_id'))
                    ->ignore($assessmentId),
            ],
            'category' => ['required', 'string', Rule::in(array_keys(config('assessment-categories', [])))],
            'weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'max_score' => ['required', 'numeric', 'min:1'],
        ];
    }

    /**
     * `subjects`/`academic_sessions` existence checks alone don't constrain
     * school — a school_admin could otherwise create an assessment pairing
     * a subject and session from two DIFFERENT campuses. Mirrors
     * TeacherAssignmentRequest::withValidator.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->hasAny(['subject_id', 'academic_session_id'])) {
                return;
            }

            // Bypass BelongsToSchool: must see the TRUE record regardless of
            // the acting user's own campus, or a cross-school id would
            // silently resolve to null and skip this check.
            $subject = Subject::withoutGlobalScope(SchoolScope::class)->find($this->input('subject_id'));
            $academicSession = AcademicSession::withoutGlobalScope(SchoolScope::class)->find($this->input('academic_session_id'));

            if ($subject === null || $academicSession === null) {
                return;
            }

            if ($subject->school_id !== $academicSession->school_id) {
                $validator->errors()->add('subject_id', 'The subject and academic session must belong to the same school.');
            }

            $actingSchoolId = $this->user()?->school_id;

            if ($actingSchoolId !== null && $subject->school_id !== $actingSchoolId) {
                $validator->errors()->add('subject_id', 'The subject and academic session must belong to your school.');
            }
        });
    }
}
