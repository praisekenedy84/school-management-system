<?php

declare(strict_types=1);

namespace App\Http\Requests\Academic;

use App\Models\ClassRoom;
use App\Models\Scopes\SchoolScope;
use App\Models\Subject;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class TeacherAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', TeacherAssignment::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'teacher_id' => ['required', 'uuid', Rule::exists('users', 'id')],
            'class_id' => ['required', 'uuid', Rule::exists('classes', 'id')],
            'subject_id' => ['required', 'uuid', Rule::exists('subjects', 'id')],
            'academic_session_id' => [
                'required',
                'uuid',
                Rule::exists('academic_sessions', 'id'),
                // DB has a unique composite on (teacher_id, class_id, subject_id,
                // academic_session_id) — surface the conflict as a clean
                // validation error instead of a 500.
                Rule::unique('teacher_assignments', 'academic_session_id')
                    ->where('teacher_id', $this->input('teacher_id'))
                    ->where('class_id', $this->input('class_id'))
                    ->where('subject_id', $this->input('subject_id')),
            ],
        ];
    }

    /**
     * `users`/`classes`/`subjects` existence checks alone don't constrain
     * school — a school_admin could otherwise assign a teacher or class
     * from a different campus in the same tenant. Require all three to
     * share one school_id.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->hasAny(['teacher_id', 'class_id', 'subject_id'])) {
                return;
            }

            // Bypass BelongsToSchool: this is a system-level integrity check
            // run on behalf of the acting (possibly school-scoped) admin, so
            // it must see the TRUE record regardless of the caller's own
            // campus — otherwise a cross-school id silently resolves to
            // null and the check below never fires.
            $teacher = User::find($this->input('teacher_id'));
            $classRoom = ClassRoom::withoutGlobalScope(SchoolScope::class)->find($this->input('class_id'));
            $subject = Subject::withoutGlobalScope(SchoolScope::class)->find($this->input('subject_id'));

            if ($teacher === null || $classRoom === null || $subject === null) {
                return;
            }

            $schoolIds = collect([$teacher->school_id, $classRoom->school_id, $subject->school_id])->unique();

            if ($schoolIds->count() > 1) {
                $validator->errors()->add('class_id', 'The teacher, class, and subject must all belong to the same school.');
            }
        });
    }
}
