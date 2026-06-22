<?php

declare(strict_types=1);

namespace App\Http\Requests\Academic;

use App\Models\ClassRoom;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClassRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        $classRoom = $this->route('classRoom');

        return $classRoom instanceof ClassRoom
            ? $this->user()?->can('update', $classRoom) ?? false
            : $this->user()?->can('create', ClassRoom::class) ?? false;
    }

    /**
     * A school-scoped user's own school_id always wins over anything they
     * submit (closes a privilege-escalation gap now that this field is
     * accepted at all); a tenant-wide admin (no school_id of their own)
     * must say which school a NEW class belongs to.
     */
    protected function prepareForValidation(): void
    {
        if ($this->user()?->school_id !== null) {
            $this->merge(['school_id' => $this->user()->school_id]);
        }
    }

    public function rules(): array
    {
        $classRoom = $this->route('classRoom');
        $schoolId = $classRoom?->school_id ?? $this->input('school_id');

        $rules = [
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('classes', 'name')
                    ->where('school_id', $schoolId)
                    ->ignore($classRoom?->id),
            ],
            'level' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];

        // school_id is fixed once a class exists — only required on create.
        if ($classRoom === null) {
            $rules['school_id'] = [
                Rule::requiredIf($this->user()?->school_id === null),
                'uuid',
                Rule::exists('schools', 'id'),
            ];
        }

        return $rules;
    }
}
