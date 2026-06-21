<?php

declare(strict_types=1);

namespace App\Http\Requests\Academic;

use App\Models\Assignment;
use App\Models\TeacherAssignment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class CreateAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Assignment::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'teacher_assignment_id' => ['required', 'uuid', Rule::exists('teacher_assignments', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'due_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    /**
     * A teacher may only create an assignment against THEIR OWN teacher
     * assignment; school_admin/tenant_admin may create against any.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $user = $this->user();

            if ($user === null || $user->hasRole(['tenant_admin', 'school_admin'])) {
                return;
            }

            $teacherAssignmentId = $this->input('teacher_assignment_id');

            if ($teacherAssignmentId === null) {
                return;
            }

            $ownsAssignment = TeacherAssignment::query()
                ->where('id', $teacherAssignmentId)
                ->where('teacher_id', $user->id)
                ->exists();

            if (! $ownsAssignment) {
                $validator->errors()->add(
                    'teacher_assignment_id',
                    'You may only create assignments for your own teaching assignments.'
                );
            }
        });
    }
}
