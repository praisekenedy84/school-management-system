<?php

declare(strict_types=1);

namespace App\Http\Requests\Sis;

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class LinkGuardianRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Student $student */
        $student = $this->route('student');

        return $this->user()?->can('update', $student) ?? false;
    }

    public function rules(): array
    {
        return [
            'guardian_id' => ['required', 'uuid', Rule::exists('users', 'id')],
            'relationship' => ['nullable', 'string', 'max:50'],
            'is_primary' => ['nullable', 'boolean'],
        ];
    }

    /**
     * `users` has no school scope to lean on (User deliberately skips
     * BelongsToSchool — see its docblock), so without this check any
     * existing user id — a teacher, an admin, or a guardian belonging to a
     * DIFFERENT school in the same tenant — could be linked as a student's
     * guardian. Require the target to actually hold the `parent` role and
     * belong to the same school as the student.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $guardianId = $this->input('guardian_id');

            if (! $guardianId || $validator->errors()->has('guardian_id')) {
                return;
            }

            /** @var Student $student */
            $student = $this->route('student');

            $guardian = User::find($guardianId);

            if ($guardian === null) {
                return;
            }

            if (! $guardian->hasRole('parent')) {
                $validator->errors()->add('guardian_id', 'The selected user is not a parent/guardian.');

                return;
            }

            if ($guardian->school_id !== $student->school_id) {
                $validator->errors()->add('guardian_id', 'The selected guardian does not belong to this student\'s school.');
            }
        });
    }
}
