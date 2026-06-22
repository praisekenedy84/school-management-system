<?php

declare(strict_types=1);

namespace App\Http\Requests\Academic;

use App\Models\AcademicSession;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AcademicSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $academicSession = $this->route('academicSession');

        return $academicSession instanceof AcademicSession
            ? $this->user()?->can('update', $academicSession) ?? false
            : $this->user()?->can('create', AcademicSession::class) ?? false;
    }

    /**
     * A school-scoped user's own school_id always wins over anything they
     * submit (closes a privilege-escalation gap now that this field is
     * accepted at all); a tenant-wide admin (no school_id of their own)
     * must say which school a NEW academic session belongs to.
     */
    protected function prepareForValidation(): void
    {
        if ($this->user()?->school_id !== null) {
            $this->merge(['school_id' => $this->user()->school_id]);
        }
    }

    public function rules(): array
    {
        $academicSession = $this->route('academicSession');
        $schoolId = $academicSession?->school_id ?? $this->input('school_id');

        $rules = [
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('academic_sessions', 'name')
                    ->where('school_id', $schoolId)
                    ->ignore($academicSession?->id),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'is_current' => ['nullable', 'boolean'],
        ];

        // school_id is fixed once a session exists — only required on create.
        if ($academicSession === null) {
            $rules['school_id'] = [
                Rule::requiredIf($this->user()?->school_id === null),
                'uuid',
                Rule::exists('schools', 'id'),
            ];
        }

        return $rules;
    }
}
