<?php

declare(strict_types=1);

namespace App\Http\Requests\Academic;

use App\Models\Subject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subject = $this->route('subject');

        return $subject instanceof Subject
            ? $this->user()?->can('update', $subject) ?? false
            : $this->user()?->can('create', Subject::class) ?? false;
    }

    /**
     * A school-scoped user's own school_id always wins over anything they
     * submit (closes a privilege-escalation gap now that this field is
     * accepted at all); a tenant-wide admin (no school_id of their own)
     * must say which school a NEW subject belongs to.
     */
    protected function prepareForValidation(): void
    {
        if ($this->user()?->school_id !== null) {
            $this->merge(['school_id' => $this->user()->school_id]);
        }
    }

    public function rules(): array
    {
        $subject = $this->route('subject');
        $schoolId = $subject?->school_id ?? $this->input('school_id');

        $rules = [
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('subjects', 'name')
                    ->where('school_id', $schoolId)
                    ->ignore($subject?->id),
            ],
            'code' => ['nullable', 'string', 'max:50'],
        ];

        // school_id is fixed once a subject exists — only required on create.
        if ($subject === null) {
            $rules['school_id'] = [
                Rule::requiredIf($this->user()?->school_id === null),
                'uuid',
                Rule::exists('schools', 'id'),
            ];
        }

        return $rules;
    }
}
