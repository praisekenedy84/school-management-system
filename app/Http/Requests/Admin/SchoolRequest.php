<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\School;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SchoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        $school = $this->route('school');

        return $school instanceof School
            ? $this->user()?->can('update', $school) ?? false
            : $this->user()?->can('create', School::class) ?? false;
    }

    public function rules(): array
    {
        $school = $this->route('school');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('schools', 'code')->ignore($school?->id),
            ],
            'locale' => ['sometimes', 'string', 'max:10'],
            'currency' => ['sometimes', 'string', 'max:3'],
            'timezone' => ['sometimes', 'string', 'max:64'],
            'calendar_type' => ['nullable', 'string', 'max:50'],
            'hostel_available' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
