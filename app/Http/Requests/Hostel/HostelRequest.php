<?php

declare(strict_types=1);

namespace App\Http\Requests\Hostel;

use App\Models\Hostel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HostelRequest extends FormRequest
{
    public function authorize(): bool
    {
        $hostel = $this->route('hostel');

        return $hostel
            ? ($this->user()?->can('update', $hostel) ?? false)
            : ($this->user()?->can('create', Hostel::class) ?? false);
    }

    /**
     * A school-scoped user's own school_id always wins over anything they
     * submit (closes a privilege-escalation gap now that this field is
     * accepted at all); a tenant-wide admin (no school_id of their own)
     * must say which school a NEW hostel belongs to.
     */
    protected function prepareForValidation(): void
    {
        if ($this->user()?->school_id !== null) {
            $this->merge(['school_id' => $this->user()->school_id]);
        }
    }

    public function rules(): array
    {
        $hostel = $this->route('hostel');

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['required', 'string', Rule::in(['male', 'female', 'mixed'])],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];

        // school_id is fixed once a hostel exists — only required on create.
        if (! $hostel) {
            $rules['school_id'] = [
                Rule::requiredIf($this->user()?->school_id === null),
                'uuid',
                Rule::exists('schools', 'id'),
            ];
        }

        return $rules;
    }
}
