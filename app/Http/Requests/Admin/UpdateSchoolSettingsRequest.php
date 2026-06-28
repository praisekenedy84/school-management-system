<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\School;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSchoolSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $school = $this->route('school');

        return $school instanceof School
            && ($this->user()?->can('updateSettings', $school) ?? false);
    }

    public function rules(): array
    {
        return [
            'locale' => ['sometimes', 'string', 'max:10'],
            'currency' => ['sometimes', 'string', 'max:3'],
            'timezone' => ['sometimes', 'string', 'max:64'],
            'calendar_type' => ['nullable', 'string', 'max:50'],
            'grading_scale' => ['nullable', 'array'],
            'fee_terms' => ['nullable', 'array'],
            'hostel_available' => ['sometimes', 'boolean'],
        ];
    }
}
